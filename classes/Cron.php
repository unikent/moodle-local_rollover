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

/**
 * Cron stuff
 */
abstract class Cron
{
    /**
     * Static Run
     */
    public static function run() {
        global $CFG, $DB, $SHAREDB;

        // First, backups.

        $localevents = $SHAREDB->get_records('rollovers', array(
            'status' => 0,
            'from_env' => $CFG->kent->environment,
            'from_dist' => $CFG->kent->distribution
        ));

        // All of these need to be backed up.
        foreach ($localevents as $event) {
            $event->updated = date('Y-m-d H:i:s');

            $settings = json_decode($event->options);
            $settings->id = $event->from_course;

            $context = \context_course::instance($event->from_course);
            $user = $DB->get_record('user', array(
                'username' => $event->requester
            ));

            // Did this user have access to this course?
            if (!$user || !has_capability('moodle/course:update', $context, $user)) {
                $event->status = 3; // Error.
                $SHAREDB->update_record('rollovers', $event);
                continue;
            }

            $event->status = 4; // In Progress.
            $SHAREDB->update_record('rollovers', $event);

            $event->path = Rollover::backup((array)$settings);
            if ($event->path) {
                $event->status = 1; // Restore.
            } else {
                $event->status = 3; // Error.
            }

            $SHAREDB->update_record('rollovers', $event);
        }

        // Now, imports.

        $localevents = $SHAREDB->get_records('rollovers', array(
            'status' => 1,
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution
        ));

        // All of these need to be imported.
        foreach ($localevents as $event) {
            $event->updated = date('Y-m-d H:i:s');

            try {
                $event->status = 4; // In Progress.
                $SHAREDB->update_record('rollovers', $event);

                $controller = new Rollover(array(
                    'id' => $event->id,
                    'tocourse' => $event->to_course,
                    'folder' => $event->path
                ));
                $controller->go();

                $event->status = 2; // Finished.

            } catch (\moodle_exception $e) {
                $event->status = 3; // Error.
                echo $e->getMessage();
            }

            $SHAREDB->update_record('rollovers', $event);
        }
    }
}