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
class backups extends \core\task\scheduled_task
{
    public function get_name() {
        return "Rollover Backups";
    }

    public function execute() {
        global $CFG, $SHAREDB;

        if (!\local_kent\util\sharedb::available()) {
            return;
        }

        $localevents = $SHAREDB->get_records('rollovers', array(
            'status' => \local_rollover\Rollover::STATUS_SCHEDULED,
            'from_env' => $CFG->kent->environment,
            'from_dist' => $CFG->kent->distribution
        ));

        // All of these need to be backed up.
        foreach ($localevents as $event) {
            $this->backup($event);
        }
    }

    /**
     * Rollover a course.
     */
    public function backup($event) {
        global $DB, $SHAREDB;

        $event->updated = date('Y-m-d H:i:s');

        $settings = (array)json_decode($event->options);
        $settings['backup_turnitintool'] = 0;
        $settings['backup_turnitintooltwo'] = 0;
        $settings['id'] = $event->from_course;

        $context = \context_course::instance($event->from_course);
        $user = $DB->get_record('user', array(
            'username' => $event->requester
        ));

        // Did this user have access to this course?
        if (!$user || !has_capability('moodle/course:update', $context, $user)) {
            $event->status = \local_rollover\Rollover::STATUS_ERROR;
            $SHAREDB->update_record('rollovers', $event);
            continue;
        }

        $event->status = \local_rollover\Rollover::STATUS_IN_PROGRESS;
        $SHAREDB->update_record('rollovers', $event);

        $event->path = \local_rollover\Rollover::backup($settings);
        if ($event->path) {
            $event->status = \local_rollover\Rollover::STATUS_BACKED_UP;
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
    }
} 