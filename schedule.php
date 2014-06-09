<?php
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
//Now some library includes
require_once('../../config.php');
require_once('lib.php');

require_login();

if (!kent_has_edit_course_access() && !has_capability('moodle/site:config', \context_system::instance())) {
    header('HTTP/1.0 401 Unauthorized', true, 401);
    exit(1);
}

// check that rollover is switched on in config and there is a valid $USER logged in.
if (!\local_connect\util\helpers::is_enabled()) {
    header('HTTP/1.0 401 Unauthorized', true, 401);
    exit(1);
}

// check the moodleness of this page request
$site = get_site();
if(!$site) die();

// Set up our paths and bits.
$data = array();

// Try and catch any problems (be it with filter_var or anything else).
try {
    // Sanitize our post data.
    $data = kent_filter_post_data();

    // Ok, we need to create a new rollover event in the moodle DB for this request.
    $fromcourse = $data['id_from'];
    $tocourse = $data['id_to'];

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
    $record = $SHAREDB->get_record('rollovers', (array)$record);
    if ($record && $record->status < 2) {
        header('HTTP/1.1 500 Server Error');
        exit(0);
    }

    // Now insert this into the DB.
    $record->created = date('Y-m-d H:i:s');
    $record->updated = date('Y-m-d H:i:s');
    $record->status = 0;
    $record->options = $options;

    $id = $SHAREDB->insert_record('rollovers', $record);

    if ($id) {
        header("HTTP/1.1 201 Created");
        exit(0);
    }

} catch (Exception $e) {
    kent_json_errors($e);
}

header("HTTP/1.1 500 Server Error");
exit(1);
