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
class imports extends \core\task\scheduled_task
{
    public function get_name() {
        return "Rollover Imports";
    }

    public function execute() {
        global $CFG, $SHAREDB;

        if (!\local_kent\util\sharedb::available()) {
            return;
        }

        $localevents = $SHAREDB->get_records('rollovers', array(
            'status' => \local_rollover\Rollover::STATUS_BACKED_UP,
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution
        ));

        // All of these need to be imported.
        foreach ($localevents as $event) {
            $this->import($event);
        }
    }

    /**
     * Rollover a course.
     */
    public function import($event) {
        global $SHAREDB;

        $event->updated = date('Y-m-d H:i:s');

        try {
            $event->status = \local_rollover\Rollover::STATUS_IN_PROGRESS;
            $SHAREDB->update_record_raw('rollovers', $event);

            $controller = new \local_rollover\Rollover(array(
                'id' => $event->id,
                'tocourse' => $event->to_course,
                'folder' => $event->path,
                'fromcourse' => $event->from_dist
            ));
            $controller->go();

            $event->status = \local_rollover\Rollover::STATUS_COMPLETE;
            $SHAREDB->update_record_raw('rollovers', $event);
        } catch (\moodle_exception $e) {
            $event->status = \local_rollover\Rollover::STATUS_ERROR;
            $SHAREDB->update_record_raw('rollovers', $event);

            // Also, wipe the course.
            remove_course_contents($event->to_course);

            $error = \local_rollover\event\rollover_error::create(array(
                'objectid' => $event->id,
                'courseid' => $event->to_course,
                'context' => \context_course::instance($event->to_course),
                'other' => array(
                    'message' => $e->getMessage()
                )
            ));
            $error->trigger();
        }
    }
} 