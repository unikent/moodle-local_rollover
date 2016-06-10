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
        } else {
            $options = (object)$options;
        }

        // Force this.
        $options->turnitintool = false;
        $options->turnitintooltwo = false;
        $options->videofile = false;
        $options->hotpot = false;
        $options->streamingvideo = false;

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
        $prod = $SHAREDB->get_record('rollovers', (array)$obj);
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
        $obj->data = json_encode(array());
        $obj->requester = $USER->username;

        $obj->id = $SHAREDB->insert_record('rollovers', $obj);

        if (!$obj->id) {
            return false;
        }

        // Add notification.
        \local_rollover\notification\status::create(array(
            'objectid' => $obj->to_course,
            'context' => $context,
            'other' => array(
                'rolloverid' => $obj->id
            )
        ));

        // Fire event.
        $event = \local_rollover\event\rollover_scheduled::create(array(
            'objectid' => $obj->id,
            'courseid' => $toid,
            'context' => $context
        ));
        $event->add_shared_record_snapshot('rollovers', $obj);
        $event->trigger();

        // Try to generate the task now.
        try {
            \local_kent\helpers::execute_script_on($obj->to_dist, '/admin/tool/task/cli/schedule_task.php', array(
                '--execute=\\local_rollover\\task\\generator'
            ));
        } catch (\moodle_exception $e) {
            debugging($e->getMessage());
        }

        return $obj->id;
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

        $obj = $SHAREDB->get_record('rollovers', (array)$obj);

        if (!$obj) {
            throw new \moodle_exception('Rollover not found.');
        }

        $context = \context_course::instance($obj->to_course);
        if (!has_capability('moodle/course:update', $context)) {
            throw new \moodle_exception('You do not have the required permissions to do that.');
        }

        $obj->status = self::STATUS_DELETED;
        $SHAREDB->update_record('rollovers', $obj);

        // Delete any notifications.
        $notification = \local_rollover\notification\status::get($event->courseid, $event->context);
        if ($notification) {
            $notification->delete();
        }
    }

    /**
     * Static backup method.
     */
    public static function backup($id, $settings) {
        global $CFG;

        static::setup_folder();

        $controller = new backup\controllers\rollover($id, $settings);
        $controller->get_plan()->get_setting('filename')->set_value($this->uuid . '.mbz');
        $controller->execute_plan();

        $result = $controller->get_results();
        $file = $result['backup_destination'];

        if ($file->get_contenthash()) {
            $packer = get_file_packer('application/vnd.moodle.backup');

            $destination = $CFG->dataroot . '/rollover/' . $file->get_contenthash();

            $extracted = $file->extract_to_pathname($packer, $destination);
            $file->delete();

            if ($extracted) {
                return $destination;
            }

            return null;
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
        $event->add_shared_record_snapshot('rollovers', $this->record);
        $event->trigger();

        $this->migrate_data();
        $this->manipulate_data();
        $this->import();
        $this->fix_sections();
        $this->notify_course($context);

        // SHAREDB may no longer be connected, reconnect just in case.
        \local_kent\util\sharedb::dispose();
        $SHAREDB = new \local_kent\util\sharedb();

        // Process data.
        $this->process_data();

        // Fire event.
        $event = \local_rollover\event\rollover_finished::create(array(
            'objectid' => $this->record->id,
            'courseid' => $this->record->to_course,
            'context' => $context
        ));
        $event->add_shared_record_snapshot('rollovers', $this->record);
        $event->trigger();
    }

    /**
     * Process custom data.
     */
    private function process_data() {
        global $DB, $SHAREDB;

        $data = $this->record->data;
        if (empty($data)) {
            return;
        }

        // Decode.
        $data = json_decode($data, true);

        // Build a mapping table.
        $maptable = array();
        if ($this->record->from_dist == $this->record->to_dist && $this->record->from_env == $this->record->to_env) {
            $maptable = $DB->get_records_sql('SELECT id, id as from_course, id as to_course FROM {course}');
        } else {
            $maptable = $SHAREDB->get_records('rollovers', array(
                'from_env' => $this->record->from_env,
                'from_dist' => $this->record->from_dist,
                'to_env' => $this->record->to_env,
                'to_dist' => $this->record->to_dist,
                'status' => self::STATUS_COMPLETE
            ), '', 'id, from_course, to_course');
        }

        // Meta enrolments?
        if (!empty($data['enrol_metaplus_links'])) {
            $links = $data['enrol_metaplus_links'];

            // Map to courses.
            $newids = array();
            foreach ($maptable as $potential) {
                if (isset($links[$potential->from_course])) {
                    $newids[$potential->from_course] = $potential->to_course;
                }
            }

            // Build metaplus lists.
            $course = $DB->get_record('course', array('id' => $this->record->to_course));
            foreach ($newids as $from => $to) {
                $roleexclusions = $links[$from];
                $instance = \enrol_metaplus\core::map_instance($course->id, $roleexclusions);
                \enrol_metaplus\core::create_or_update($course, array($to), $roleexclusions, false, $instance);
            }
        }

        // More meta enrolments?
        if (!empty($data['enrol_metaplus_linked'])) {
            // We have a list of courses that used to link to us, let's see which have already
            // rolled over and re-link ourselves to them.
            foreach ($data['enrol_metaplus_linked'] as $courseid => $roleexclusions) {
                foreach ($maptable as $potential) {
                    if ($potential->from_course == $courseid) {
                        $course = $DB->get_record('course', array('id' => $potential->to_course));

                        // Grab current instances.
                        $instance = \enrol_metaplus\core::map_instance($course->id, $roleexclusions);

                        // This course used to be linked to us! Do it again.
                        \enrol_metaplus\core::create_or_update($course, array($potential->from_course), $roleexclusions, false, $instance);
                    }
                }
            }
        }
    }

    /**
     * Move the data folder to our backup folder.
     */
    private function migrate_data() {
        global $CFG;

        $to = escapeshellcmd($CFG->tempdir . '/backup/' . $this->uuid);
        $from = escapeshellcmd($this->record->path);

        exec("mv {$from} {$to}", $out, $return);

        if ($return != 0 || !file_exists($to . '/moodle_backup.xml')) {
            throw new \moodle_exception("Could not copy backup folder: {$out}!");
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

        // Rename news forum.
        $this->manipulate_fields($xpath, 'forum', 'name', 'Announcements', 'News forum');

        // Don't show dates for old files.
        $this->manipulate_fields($xpath, 'resource', 'displayoptions', function($val) {
            $arr = unserialize($val);
            $arr['showdate'] = 0;
            return serialize($arr);
        });

        if ($doc->save($xml) === false) {
            throw new \moodle_exception('Could not overwrite backup file <' . $xml . '>');
        }
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
                    if (is_callable($value)) {
                        $node->nodeValue = $value($node->nodeValue);
                    } else {
                        $node->nodeValue = $value;
                    }
                }
            }
        });
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
        $kc = new \local_kent\Course($context->instanceid);

        // Add message.
        \local_rollover\notification\status::create(array(
            'objectid' => $this->record->to_course,
            'context' => $context,
            'other' => array(
                'complete' => true,
                'rolloverid' => $this->record->id,
                'record' => $this->record,
                'manual' => $kc->is_manual()
            )
        ));
    }
}
