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
    const STATUS_WAITING_SCHEDULE = 5;
    const STATUS_DELETED = 10;
    const STATUS_RESTORE_SCHEDULED = 32;

    /** Rollover UUID */
    private $uuid;

    /** Rollover object */
    private $record;

    /**
     * Begin a rollover.
     */
    public function __construct($rollover) {
        $this->uuid = uniqid('rollover-');
        $this->record = $rollover;
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
            throw new \moodle_exception('You do not have access to that course.');
        }

        $obj = new \stdClass();
        $obj->from_env = $CFG->kent->environment;
        $obj->from_dist = $fromdist;
        $obj->from_course = $fromid;
        $obj->to_env = $CFG->kent->environment;
        $obj->to_dist = $CFG->kent->distribution;
        $obj->to_course = $toid;

        // Check if the to_course exists in here already.
        $prod = $SHAREDB->get_record('shared_rollovers', (array)$obj);
        if ($prod && $prod->status != self::STATUS_DELETED) {
            throw new \moodle_exception('A rollover is already scheduled for that course.');
        }

        // Courses cannot rollover into themselves.
        if ($obj->from_env == $obj->to_env &&
            $obj->from_dist == $obj->to_dist &&
            $obj->from_course == $obj->to_course) {
            throw new \moodle_exception('You cannot rollover a course over into itself.');
        }

        // Now insert this into the DB.
        $obj->created = date('Y-m-d H:i:s');
        $obj->updated = date('Y-m-d H:i:s');
        $obj->status = self::STATUS_WAITING_SCHEDULE;
        $obj->options = json_encode($options);
        $obj->requester = $USER->username;

        $result = $SHAREDB->insert_record('shared_rollovers', $obj);

        if (!$result) {
            return false;
        }

        // Add notification.
        $message = '<i class="fa fa-info-circle"></i> A rollover has been scheduled on this course.';
        $kc = new \local_kent\Course($result);
        $kc->replace_notification($context->id, 'rollover', $message, 'info', false, false);

        // Fire event.
        $event = \local_rollover\event\rollover_scheduled::create(array(
            'objectid' => $result,
            'courseid' => $toid,
            'context' => $context
        ));
        $event->trigger();

        return $result;
    }

    /**
     * Undo a rollover
     */
    public static function undo($id) {
        global $CFG, $USER, $SHAREDB;

        $obj = new \stdClass();
        $obj->id = $id;
        $obj->to_env = $CFG->kent->environment;
        $obj->to_dist = $CFG->kent->distribution;

        $obj = $SHAREDB->get_record('shared_rollovers', (array)$obj);

        if (!$obj) {
            throw new \moodle_exception('Rollover not found.');
        }

        $context = \context_course::instance($obj->to_course);
        if (!has_capability('moodle/course:update', $context)) {
            throw new \moodle_exception('You do not have the required permissions to do that.');
        }

        $obj->status = self::STATUS_DELETED;
        $SHAREDB->update_record('shared_rollovers', $obj);

        // Delete any notifications.
        $kc = new \local_kent\Course($event->courseid);
        $notification = $kc->get_notification($event->context->id, 'rollover');
        if ($notification) {
            $notification->delete();
        }
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
     * Remove the contents of a course.
     */
    public static function remove_course_contents($courseid) {
        return remove_course_contents($courseid, false, array(
            'keep_roles_and_enrolments' => true,
            'keep_groups_and_groupings' => true
        ));
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
     * This should only EVER be called from the import task.
     */
    public function go() {
        global $SHAREDB;

        static::setup_folder();

        $context = \context_course::instance($this->record->to_course);

        // Fire event.
        $event = \local_rollover\event\rollover_started::create(array(
            'objectid' => $this->record->id,
            'courseid' => $this->record->to_course,
            'context' => $context
        ));
        $event->trigger();

        $this->migrate_data();
        $this->manipulate_data();
        $this->import();
        $this->fix_sections();
        $this->notify_course($context);

        // SHAREDB may no longer be connected, reconnect just in case.
        \local_kent\util\sharedb::dispose();
        $SHAREDB = new \local_kent\util\sharedb();

        // Fire event.
        $event = \local_rollover\event\rollover_finished::create(array(
            'objectid' => $this->record->id,
            'courseid' => $this->record->to_course,
            'context' => $context
        ));
        $event->trigger();
    }

    /**
     * Move the data folder to our backup folder.
     */
    private function migrate_data() {
        global $CFG;

        $to = escapeshellcmd($CFG->tempdir . '/backup/' . $this->uuid);

        // Work out the from location.
        $from = $this->record->path;
        if (strpos($from, '/data/moodledata') === 0) {
            $from = str_replace('/data/moodledata/', '/mnt/stollen/archivedata/', $from);
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

        // Rename news forum.
        $this->manipulate_fields($xpath, 'forum', 'name', 'Announcements', 'News forum');

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
    private function manipulate_fields($xpath, $module, $field, $value, $where = null) {
        $this->manipulate_module($xpath, $module, function($doc, $mxpath, $moduleid) use ($module, $field, $value, $where) {
            $query = "/activity/{$module}/{$field}";
            $nodes = $mxpath->query($query);
            foreach ($nodes as $node) {
                if (!$where || $node->nodeValue == $where) {
                    $node->nodeValue = $value;
                }
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
        echo "Clearing course...\n";
        self::remove_course_contents($this->record->to_course);

        echo "Running import...\n";

        $controller = new \restore_controller(
            $this->uuid,
            $this->record->to_course,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            2,
            \backup::TARGET_EXISTING_ADDING
        );

        if (!$controller->execute_precheck()) {
            echo "Warning - Precheck failed\n";
            $results = $controller->get_precheck_results();

            if (isset($results['errors'])) {
                throw new \moodle_exception("Plan pre-check failed. " . var_dump($results));
            }

            if (isset($results['warnings'])) {
                debugging(var_export($results['warnings'], true));
            }
        }

        $controller->execute_plan();
    }

    /**
     * Fix sections for courses.
     */
    private function fix_sections() {
        global $DB;

        // Count the number of sections.
        $sectioncount = $DB->count_records('course_sections', array(
            'course' => $this->record->to_course
        ));

        // Current setting.
        $current = $DB->get_record('course_format_options', array(
            'courseid' => $this->record->to_course,
            'name' => 'numsections'
        ));

        // update value.
        $current->value = $sectioncount;
        $DB->update_record('course_format_options', $current);
    }

    /**
     * Notify the couse.
     */
    private function notify_course($context) {
        global $CFG, $SHAREDB;

        $kc = new \local_kent\Course($context->instanceid);
        $manual = $kc->is_manual();
        $moduletext = ($manual ? 'manually-created ' : '') . 'module';

        $message = "<i class=\"fa fa-history\"></i> This {$moduletext} has been rolled over from a previous year.";

        // Get the rollover.
        $rollover = $SHAREDB->get_record('shared_rollovers', array('id' => $this->record->id));
        if ($rollover && isset($CFG->kent->paths[$rollover->from_dist])) {
            $url = $CFG->kent->paths[$rollover->from_dist] . "course/view.php?id=" . $rollover->from_course;

            $message = '<i class="fa fa-history"></i> ';
            $message .= "This {$moduletext} has been rolled over from ";
            $message .= '<a href="{$url}" class="alert-link" target="_blank">Moodle {$rollover->from_dist}</a>.';
        }

        // Is this a manual course?
        if ($manual) {
            $message .= ' An administrator must re-link any previous meta-enrolments.';
            $message .= 'Information on how to do this can be found on the ';
            $message .= '<a href="http://www.kent.ac.uk/elearning/files/moodle/moodle-meta-enrolment.pdf" class="alert-link" target="_blank">';
            $message .= 'E-Learning website</a>.';
        }

        // Add message.
        $kc->replace_notification($context->id, 'rollover', $message, 'info', false, true);
    }
}
