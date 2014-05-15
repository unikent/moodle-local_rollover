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

if (!kent_has_edit_course_access() && !has_capability('moodle/site:config', $systemcontext)) {
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

// set up our paths and bits
$java_location = $CFG->kent->paths['connect'] . 'rollover/schedule';
$data = array();

// try and catch any problems (be it with filter_var or anything else)
try {
    // sanitize our post data
    $data = kent_filter_post_data();

    // ok, we need to create a new rollover event in the moodle DB for this request
    $from_course = $data['id_from'];
    $to_course = $data['id_to'];

    // remove those from the data so $data just contains the options
    unset($data['id_from']);
    unset($data['id_to']);

    //Fix so that we do not rollover Turnitin inboxes from previous years (as we can't)
    if (isset($data['backup_source']) && $data['backup_source'] != "live") {
        if (isset($data['backup_turnitintool'])) {
            unset($data['backup_turnitintool']);
        }
    }

    // json encode the remaining data (options)
    $options = json_encode($data);

    // check if the to_course exists in here already
    $row = $DB->get_record('rollover_events', array(
        'from_course' => $from_course,
        'to_course' => $to_course
    ));

    if ($row) {
        // it exists, so let's cancel this request (don't want to rollover > 1 time)
        header('HTTP/1.1 500 Server Error');
        exit(0);
    }

    // now insert this into the DB
    $record = new stdClass();
    $record->from_course = $from_course;
    $record->to_course = $to_course;
    $record->what = 'requested';
    $record->options = $options;
    $record->requested_at = date('Y-m-d H:i:s');

    if (isset($CFG->kent_rollover_environment)) {
        $rollover_env = $CFG->kent_rollover_environment;
    } else {
        $rollover_env = 'live';
    }

    // these are used by the server to determine which CLI scripts to run, so
    // make sure they are set to something appropriate (that exists)
    // 
    // archive = the 1.9 archive (probably only used for backup)
    // live = the 2.2 live install (used for restore, and maybe backup with training someday)
    // training = the 2.2 live TRAINING install (used for restore)
    if (\local_connect\util\helpers::enable_new_features()) {
        $record->backup_source = $data['src_from'];
    } else {
        $record->backup_source = $data['src_from'] == '2' ? 'live' : ($data['src_from'] == 'twentytwelve' ? 'twentytwelve' : 'archive');
    }

    $record->restore_target = $rollover_env;

    $id = $DB->insert_record('rollover_events', $record);

    if (!$id) {
        header('HTTP/1.1 500 Server Error');
        exit(0);
    }

    // this is super important, as it tells the backend which environment this
    // request came from. There are two options right now, 'live' and 'training'
    $environment = $rollover_env;

    // send a schedule request to the connect server backend
    $ch = curl_init($java_location);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('id' => $id, 'environment' => $environment));

    $response = curl_exec($ch);
    $output = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // echo back status code from curl, or just fail with a 500 if it's not good
    if( $output == 100 || $output == "100" || $output == 201 || $output == "201" ) {
      header("HTTP/1.1 201 Created");
    } else {
      header("HTTP/1.1 500 Server Error");
    }
    exit(0);

} catch (Exception $e) {kent_json_errors($e);}

header("HTTP/1.1 500 Server Error");
exit(1);
