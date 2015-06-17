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
 * Rollover Rollover Task generator
 */
class generator extends \core\task\scheduled_task
{
    public function get_name() {
        return "Rollover Task generator";
    }

    public function execute() {
        global $CFG, $DB;

        if (!\local_kent\util\sharedb::available()) {
            return;
        }

        // If we already have more than <x> pending adhoc tasks, don't schedule any more.
        $count = $DB->count_records('task_adhoc', array(
            'component' => 'local_rollover',
            'faildelay' => 0
        ));

        if ($count < $CFG->kent->rollover_ratelimit) {
            $this->schedule_backups();
            $this->schedule_restores();
        }
    }

    /**
     * Schedule all backup tasks.
     */
    public function schedule_backups() {
        global $CFG, $SHAREDB;

        $events = $SHAREDB->get_records('shared_rollovers', array(
            'status' => \local_rollover\Rollover::STATUS_WAITING_SCHEDULE,
            'from_env' => $CFG->kent->environment,
            'from_dist' => $CFG->kent->distribution
        ), '', '*', 0, $CFG->kent->rollover_ratelimit);

        // All of these need to be backed up.
        foreach ($events as $event) {
            $task = new \local_rollover\task\backup();
            $task->set_custom_data(array(
                'id' => $event->id
            ));

            $event->status = \local_rollover\Rollover::STATUS_SCHEDULED;
            $SHAREDB->update_record('shared_rollovers', $event);

            \core\task\manager::queue_adhoc_task($task);
        }
    }

    /**
     * Schedule all restore tasks.
     */
    public function schedule_restores() {
        global $CFG, $SHAREDB;

        $events = $SHAREDB->get_records('shared_rollovers', array(
            'status' => \local_rollover\Rollover::STATUS_BACKED_UP,
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution
        ), '', '*', 0, $CFG->kent->rollover_ratelimit);

        // All of these need to be imported.
        foreach ($events as $event) {
            $task = new \local_rollover\task\import();
            $task->set_custom_data(array(
                'id' => $event->id
            ));

            $event->status = \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED;
            $SHAREDB->update_record('shared_rollovers', $event);

            \core\task\manager::queue_adhoc_task($task);
        }
    }
}
