<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_rollover;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/cla/lib.php');

/**
 * Rollover stuff
 */
class Rollover
{
    /** Various Status Codes */
    const STATUS_NONE = -1;
    const STATUS_SCHEDULED = 0;
    const STATUS_BACKED_UP = 1;
    const STATUS_COMPLETE = 2;
    const STATUS_ERROR = 3;
    const STATUS_IN_PROGRESS = 4;

    /** Rollover UUID */
    private $uuid;

    /** Rollover ID */
    private $id;

    /** Rollover settings */
    private $settings;

    /**
     * Begin a rollover.
     */
    public function __construct($settings) {
        $this->uuid = uniqid('rollover-');
        $this->settings = $settings;
        $this->id = $this->settings['id'];

        // Ensure we have the settings we need.
        if (!isset($this->settings['tocourse'])) {
            throw new \moodle_exception('Must specify ID to roll into!');
        }

        if (!isset($this->settings['folder'])) {
            throw new \moodle_exception('Must specify folder to roll from!');
        }

        static::setup_folder();
    }

    /**
     * Schedule a rollover.
     */
    public static function schedule($fromdist, $fromid, $toid, $options = null) {
        global $CFG, $USER, $SHAREDB;

        if (empty($options)) {
            $options = new \stdClass();
        }

        $context = \context_course::instance($toid);
        if (!has_capability('moodle/course:update', $context)) {
            throw new \moodle_exception('You cannot rollover into that course!');
        }

        $obj = new \stdClass();
        $obj->from_env = $CFG->kent->environment;
        $obj->from_dist = $fromdist;
        $obj->from_course = $fromid;
        $obj->to_env = $CFG->kent->environment;
        $obj->to_dist = $CFG->kent->distribution;
        $obj->to_course = $toid;

        // Check if the to_course exists in here already.
        $prod = $SHAREDB->get_record('rollovers', (array)$obj);
        if ($prod && $prod->status != 3) {
            throw new \moodle_exception('A rollover is already scheduled for that course!');
        }

        // Now insert this into the DB.
        $obj->created = date('Y-m-d H:i:s');
        $obj->updated = date('Y-m-d H:i:s');
        $obj->status = self::STATUS_SCHEDULED;
        $obj->options = json_encode($options);
        $obj->requester = $USER->username;

        return $SHAREDB->insert_record('rollovers', $obj);
    }

    /**
     * Static backup method.
     */
    public static function backup($settings) {
        global $CFG;

        static::setup_folder();

        $controller = new backup\controllers\rollover($settings['id'], $settings);
        $controller->execute_plan();

        $result = $controller->get_results();
        $file = $result['backup_destination'];

        if ($file->get_contenthash()) {
            $packer = get_file_packer('application/vnd.moodle.backup');

            $destination = $CFG->dataroot . '/rollover/' . $file->get_contenthash();

            $file->extract_to_pathname($packer, $destination);
            $file->delete();

            return $destination;
        }

        return null;
    }

    /**
     * Setup.
     */
    private static function setup_folder() {
        global $CFG;

        // Ensure we have a valid backup directory.
        if (!file_exists($CFG->tempdir . '/backup')) {
            if (!mkdir($CFG->tempdir . '/backup')) {
                throw new \moodle_exception('Could not create backup directory!');
            }
        }

        // Ensure we have a valid rollover directory.
        if (!file_exists($CFG->dataroot . '/rollover')) {
            if (!mkdir($CFG->dataroot . '/rollover')) {
                throw new \moodle_exception('Could not create rollover directory!');
            }
        }
    }

    /**
     * Do the rollover.
     */
    public function go() {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            $this->migrate_data();
            $this->manipulate_data();
            $this->import();
            $this->post_import();
        } catch (\moodle_exception $e) {
            $DB->rollback_delegated_transaction($transaction, $e);
        }

        $transaction->allow_commit();
    }

    /**
     * Move the data folder to our backup folder.
     */
    private function migrate_data() {
        global $CFG;

        $to = escapeshellcmd($CFG->tempdir . '/backup/' . $this->uuid);

        // Work out the from location.
        $from = $this->settings['folder'];
        if (strpos($from, '/data/moodledata') === 0) {
            $from = str_replace('/data/moodledata/', '/mnt/doodle/archivedata/', $from);
        }
        $from = escapeshellcmd($from);

        // We can only copy from archive.
        $func = strpos($from, 'archivedata') === false ? 'mv' : 'cp -R';

        exec("$func $from $to", $out, $return);

        if ($return != 0) {
            throw new \moodle_exception('Could not move backup folder!');
        }
    }

    /**
     * Manipulate data prior to rollover.
     */
    private function manipulate_data() {
        global $CFG;

        $xml = $CFG->tempdir . '/backup/' . $this->uuid . '/moodle_backup.xml';

        $doc = new \DOMDocument();
        if (!$doc->load($xml)) {
            throw new \moodle_exception('Could not load backup file <' . $xml . '>');
        }

        $xpath = new \DOMXPath($doc);

        // Remove all turnitintool activities.
        $this->remove_module($xpath, 'turnitintool');
        $this->remove_module($xpath, 'turnitintooltwo');

        // Set CLA objects to 'rolledover' state.
        $this->manipulate_fields($xpath, 'cla', 'rolled_over', 1);
        $this->manipulate_fields($xpath, 'cla', 'rolled_over_inactive', 1);

        // Add CLA notes.
        $this->add_cla_notes($xpath);

        if ($doc->save($xml) === false) {
            throw new \moodle_exception('Could not overwrite backup file <' . $xml . '>');
        }
    }

    /**
     * Remove a specified modules from this rollover.
     */
    private function remove_module($xpath, $name) {
        // Remove all $name activities.
        $query = "/moodle_backup/information/contents/activities/activity[modulename/text()='$name']";
        $this->remove_nodes($xpath, $query);

        // Remove all $name settings.
        $query = "/moodle_backup/information/settings/setting[activity/text()[contains(.,'$name')]]";
        $this->remove_nodes($xpath, $query);
    }

    /**
     * Remove a specified activity by moduleid from this rollover.
     */
    private function remove_activity_byid($xpath, $id) {
        // Remove all $id activities.
        $query = "/moodle_backup/information/contents/activities/activity[moduleid/text()='{$id}']";
        $this->remove_nodes($xpath, $query);

        // Remove all $id settings.
        $query = "/moodle_backup/information/settings/setting[level = 'activity' and activity/text()[contains(.,'_{$id}')]]";
        $this->remove_nodes($xpath, $query);
    }

    /**
     * Go through every module, of a given name and call a
     * function with the xpath of the module's XML.
     */
    private function manipulate_module($xpath, $module, $callback) {
        global $CFG;

        $query = "/moodle_backup/information/contents/activities/activity[modulename/text()='$module']/moduleid";
        $nodes = $xpath->query($query);
        foreach ($nodes as $node) {
            $id = $node->nodeValue;

            $xml = "{$CFG->tempdir}/backup/{$this->uuid}/activities/{$module}_{$id}/{$module}.xml";

            $doc = new \DOMDocument();
            if (!$doc->load($xml)) {
                throw new \moodle_exception('Could not load module file <' . $xml . '>');
            }

            $mxpath = new \DOMXPath($doc);

            $callback($doc, $mxpath, $id);

            if ($doc->save($xml) === false) {
                throw new \moodle_exception('Could not overwrite module file <' . $xml . '>');
            }
        }
    }

    /**
     * Modules have their own special tables which are stored as XML files in rollover.
     * This function lets you manipulate a particular field.
     */
    private function manipulate_fields($xpath, $module, $field, $value) {
        $this->manipulate_module($xpath, $module, function($doc, $mxpath, $moduleid) use ($module, $field, $value) {
            $query = "/activity/{$module}/{$field}";
            $nodes = $mxpath->query($query);
            foreach ($nodes as $node) {
                $node->nodeValue = $value;
            }
        });
    }

    /**
     * Add notes to CLA items.
     */
    private function add_cla_notes($xpath) {
        $event = $this->settings['event'];
        $this->manipulate_module($xpath, 'cla', function($doc, $mxpath, $moduleid) use ($event) {
            global $CFG;

            // Grab an ID.
            $maxid = 0;
            $notes = $mxpath->query('/activity/cla/notes/note');
            foreach ($notes as $note) {
                $id = $note->attributes->getNamedItem('id');
                $maxid = max($maxid, $id->nodeValue);
            }

            $fromdist = $event->from_dist;
            $urlbase = $CFG->kent->paths[$fromdist];
            $url = $urlbase . '/mod/cla/admin.php?claid=' . $moduleid . '&page=search';

            $notetext = 'This request has been rolled over from a Moodle ' . $fromdist . ' resource: ';
            $notetext .= \html_writer::tag('a', 'Moodle ' . $fromdist, array(
                'href' => $url
            ));

            // Add to the notes list.
            $maxid++;
            $notes = $mxpath->query('/activity/cla/notes');
            foreach ($notes as $notepath) {
                $userid = $doc->createElement('userid', 2);
                $text = $doc->createElement('text', $notetext);
                $timestamp = $doc->createElement('timestamp', time());

                $note = $doc->createElement('note');
                $note->appendChild($userid);
                $note->appendChild($text);
                $note->appendChild($timestamp);

                $child = $notepath->appendChild($note);
                $child->setAttribute("id", $maxid);
            }
        });
    }

    /**
     * Manipulate a DOM Document - remove a path.
     */
    private function remove_nodes($xpath, $query) {
        $nodes = $xpath->query($query);

        // Remove them.
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Run the import.
     */
    private function import() {
        // Clear out the existing course.
        remove_course_contents($this->settings['tocourse']);

        $controller = new \restore_controller(
            $this->uuid,
            $this->settings['tocourse'],
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            2,
            \backup::TARGET_EXISTING_ADDING
        );

        if (!$controller->execute_precheck()) {
            throw new \moodle_exception("Plan pre-check failed. " . var_dump($controller->get_precheck_results()));
        }

        $controller->execute_plan();
    }

    /**
     * Run stuff after import is complete.
     */
    private function post_import() {
        cla_post_rollover($this->id);
    }
}
