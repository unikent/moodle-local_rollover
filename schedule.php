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

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once('lib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/rollover/schedule.php');

require_login();

if (!\local_rollover\User::has_course_update_role()) {
    print_error('Unauthorized');
}

// Check that rollover is switched on in config and there is a valid $USER logged in.
if (!\local_connect\util\helpers::is_enabled()) {
    print_error('Unauthorized');
}

// Sanitize our post data.
$data = kent_filter_post_data();

// Ok, we need to create a new rollover event in the moodle DB for this request.
$fromcourse = $data['id_from'];
$tocourse = $data['id_to'];

// Grab a course context.
$context = \context_course::instance($tocourse);
if (!has_capability('moodle/course:update', $context)) {
    print_error('Unauthorized');
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
$record->from_env = $CFG->kent->environment;
$record->from_dist = $data['src_from'];
$record->from_course = $fromcourse;
$record->to_env = $CFG->kent->environment;
$record->to_dist = $CFG->kent->distribution;
$record->to_course = $tocourse;

// Check if the to_course exists in here already.
$prod = $SHAREDB->get_record('rollovers', (array)$record);
if ($prod && $prod->status < 2) {
    print_error('There is already a rollover for that course.');
}

// Courses cannot rollover into themselves.
if ($record->from_env == $record->to_env &&
    $record->from_dist == $record->to_dist &&
    $record->from_course == $record->to_course) {
    print_error('You cannot roll a course over into itself.');
}

// Now insert this into the DB.
$record->created = date('Y-m-d H:i:s');
$record->updated = date('Y-m-d H:i:s');
$record->status = \local_rollover\Rollover::STATUS_WAITING_SCHEDULE;
$record->options = $options;
$record->requester = $USER->username;

$id = $SHAREDB->insert_record('rollovers', $record);

if (!$id) {
    print_error('Could not create rollover (reason unknown).');
}

echo $OUTPUT->header();
echo json_encode(array(
    'status' => 'success',
    'id' => $id
));