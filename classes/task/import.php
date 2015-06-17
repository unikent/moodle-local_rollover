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
 * Rollover imports
 */
class import extends \core\task\adhoc_task
{
    public function get_component() {
        return 'local_rollover';
    }

    public function execute() {
        global $SHAREDB;

        $params = $this->get_custom_data();
        $event = $SHAREDB->get_record('shared_rollovers', (array)$params, '*', MUST_EXIST);

        if ((int)$event->status != \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED) {
            echo "Warning! Event not in scheduled state for restore: {$event->status}.\n";
            return;
        }

        $event->updated = date('Y-m-d H:i:s');
        $event->status = \local_rollover\Rollover::STATUS_IN_PROGRESS;
        $SHAREDB->update_record('shared_rollovers', $event);

        try {
            $controller = new \local_rollover\Rollover(array(
                'id' => $event->id,
                'tocourse' => $event->to_course,
                'folder' => $event->path,
                'fromcourse' => $event->from_dist
            ));
            $controller->go();

            // Update status.
            $event->status = \local_rollover\Rollover::STATUS_COMPLETE;
            $SHAREDB->update_record('shared_rollovers', $event);
        } catch (\moodle_exception $e) {
            // Update status.
            $event->status = \local_rollover\Rollover::STATUS_ERROR;
            $SHAREDB->update_record('shared_rollovers', $event);

            // Also, wipe the course.
            remove_course_contents($event->to_course, false, array(
                'keep_roles_and_enrolments' => true,
                'keep_groups_and_groupings' => true
            ));

            $context = \context_course::instance($event->to_course);

            // Add message.
            $message = '<i class="fa fa-exclamation-triangle"></i> The rollover for this course failed! Please contact your FLT.';
            $kc = new \local_kent\Course($event->to_course);
            $kc->add_notification($context->id, 'rollover', $message, 'error', false, false);

            // Register event.
            $error = \local_rollover\event\rollover_error::create(array(
                'objectid' => $event->id,
                'courseid' => $event->to_course,
                'context' => $context,
                'other' => array(
                    'message' => $e->getMessage()
                )
            ));
            $error->trigger();

            throw $e;
        }
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
