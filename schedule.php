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
 * This script provides a web service which will accept a post of scheduling data for a rollover
 *
 * @copyright  2012 University of Kent (http://www.kent.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Web service accepts:
 *
 * _POST -- Post data from schedule form.
 */

require_once('../../config.php');
require_once('lib.php');

require_login();

if (!\local_rollover\User::has_course_update_role()) {
    header('HTTP/1.0 401 Unauthorized', true, 401);
    exit(1);
}

// Check that rollover is switched on in config and there is a valid $USER logged in.
if (!\local_connect\util\helpers::is_enabled()) {
    header('HTTP/1.0 401 Unauthorized', true, 401);
    exit(1);
}

// Check the moodleness of this page request.
$site = get_site();
if (!$site) {
    die();
}

// Try and catch any problems (be it with filter_var or anything else).
try {
    // Sanitize our post data.
    $data = kent_filter_post_data();

    // Ok, we need to create a new rollover event in the moodle DB for this request.
    $fromcourse = $data['id_from'];
    $tocourse = $data['id_to'];

    // Grab a course context.
    $context = \context_course::instance($tocourse);
    if (!has_capability('moodle/course:update', $context)) {
        header('HTTP/1.0 401 Unauthorized', true, 401);
        exit(1);
    }

    // Remove those from the data so $data just contains the options.
    unset($data['id_from']);
    unset($data['id_to']);

    // Fix so that we do not rollover Turnitin inboxes from previous years (as we can't).
    if (isset($data['backup_source']) && $data['backup_source'] != "live") {
        if (isset($data['backup_turnitintool'])) {
            unset($data['backup_turnitintool']);
        }
    }

    // Json encode the remaining data (options).
    $options = json_encode($data);

    $record = new stdClass();
    $record->from_env = $CFG->kent->environment; // Todo - fix this.
    $record->from_dist = $data['src_from'];
    $record->from_course = $fromcourse;
    $record->to_env = $CFG->kent->environment;
    $record->to_dist = $CFG->kent->distribution;
    $record->to_course = $tocourse;

    // Check if the to_course exists in here already.
    $prod = $SHAREDB->get_record('rollovers', (array)$record);
    if ($prod && $prod->status < 2) {
        header('HTTP/1.1 500 Server Error');
        exit(0);
    }

    // Now insert this into the DB.
    $record->created = date('Y-m-d H:i:s');
    $record->updated = date('Y-m-d H:i:s');
    $record->status = \local_rollover\Rollover::STATUS_WAITING_SCHEDULE;
    $record->options = $options;
    $record->requester = $USER->username;

    $id = $SHAREDB->insert_record('rollovers', $record);

    if ($id) {
        header("HTTP/1.1 201 Created");
        exit(0);
    }

} catch (Exception $e) {
    header("HTTP/1.1 500 Server Error");
    header('Content-type: application/json');
    die(json_encode(array(
        'status' => false,
        'errors' => htmlentities(print_r($e, true))
    )));
}

header("HTTP/1.1 500 Server Error");
exit(1);
