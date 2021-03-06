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

/**
 * Local stuff for Moodle Rollover
 *
 * @package    local_connect
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rollover\task;

/**
 * Rollover backups
 */
class backup extends \core\task\adhoc_task
{
    public function get_component() {
        return 'local_rollover';
    }

    public function execute() {
        global $DB, $SHAREDB;

        $params = $this->get_custom_data();
        $event = $SHAREDB->get_record('rollovers', (array)$params, '*', MUST_EXIST);

        if ((int)$event->status != \local_rollover\Rollover::STATUS_SCHEDULED) {
            echo "Warning! Event not in scheduled state for backup:  {$event->status}\n";
            return;
        }

        $event->updated = date('Y-m-d H:i:s');

        // Decode settings.
        $settings = (array)json_decode($event->options);

        // Decode user.
        $context = \context_course::instance($event->from_course);
        $user = $DB->get_record('user', array(
            'username' => $event->requester
        ));

        // Did this user have access to this course?
        if (!$user || !has_capability('moodle/course:update', $context, $user)) {
            $event->status = \local_rollover\Rollover::STATUS_ERROR;
            $SHAREDB->update_record('rollovers', $event);

            throw new \moodle_exception("User does not have access to backup that course!");
        }

        $event->status = \local_rollover\Rollover::STATUS_IN_PROGRESS;
        $SHAREDB->update_record('rollovers', $event);

        $event->path = \local_rollover\Rollover::backup($event->from_course, $settings);
        if ($event->path) {
            $event->status = \local_rollover\Rollover::STATUS_BACKED_UP;

            // Build a data array now.
            $event->data = json_encode($this->build_data($event->from_course));
        } else {
            $error = \local_rollover\event\rollover_error::create(array(
                'objectid' => $event->id,
                'courseid' => $event->from_course,
                'context' => \context_course::instance($event->from_course),
                'other' => array(
                    'message' => 'The backup failed.'
                )
            ));
            $error->trigger();

            $event->status = \local_rollover\Rollover::STATUS_ERROR;
        }

        $SHAREDB->update_record('rollovers', $event);

        if ($event->status == \local_rollover\Rollover::STATUS_BACKED_UP) {
            try {
                \local_kent\helpers::execute_script_on($event->to_dist, '/admin/tool/task/cli/schedule_task.php', array(
                    '--execute=\\local_rollover\\task\\generator'
                ));
            } catch (\moodle_exception $e) {
                debugging($e->getMessage());
            }
        }
    }

    /**
     * Build a data array for a given course.
     */
    private function build_data($courseid) {
        global $DB;

        $data = array();

        // Build a list of courses this course was linked to.
        $enrols = $DB->get_records('enrol', array(
            'enrol' => 'metaplus',
            'courseid' => $courseid
        ));

        $metalinks = array();
        foreach ($enrols as $enrol) {
            $metas = $DB->get_records('enrol_metaplus', array('enrolid' => $enrol->id));
            foreach ($metas as $meta) {
                $metalinks[$meta->courseid] = $enrol->customtext1;
            }
        }

        $data['enrol_metaplus_links'] = $metalinks;

        // Build a list of courses this course was linked by.
        $metalinked = array();
        $metas = $DB->get_records('enrol_metaplus', array('courseid' => $courseid));
        foreach ($metas as $meta) {
            $enrol = $DB->get_record('enrol', array('id' => $meta->enrolid));
            if ($enrol) {
                $metalinked[$enrol->courseid] = $enrol->customtext1;
            }
        }

        $data['enrol_metaplus_linked'] = $metalinked;

        return $data;
    }

    /**
     * Setter for $customdata.
     * @param mixed $customdata (anything that can be handled by json_encode)
     */
    public function set_custom_data($customdata) {
        if (empty($customdata['id'])) {
            throw new \moodle_exception("Event ID cannot be empty!");
        }

        return parent::set_custom_data($customdata);
    }
}
