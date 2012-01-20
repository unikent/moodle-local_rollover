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

//Check that rollover is switched on in config and there is a valid $USER logged in.
if(!isset($CFG->kent_rollover_system) || !$CFG->kent_rollover_system || !isloggedin()){
   exit(1);
}

//Check the moodleness of this page request
$site = get_site();
if(!$site) die();

//Set up our paths and bits
$java_location = ((isset($CFG->kent_rollover_scheduler_path) && ($CFG->kent_rollover_scheduler_path != "") ) ? $CFG->kent_rollover_scheduler_path : $CFG->wwwroot.'/local/rollover/test.php');
$data = array();

//Try and catch any problems (be it with filter_var or anything else)
try {
    //Sanitize our post data
    $data = kent_filter_post_data();

    //Turn data into JSON and carry on...
    $json_data = json_encode($data);

    //Now punt off data to next location to get a response (java to set up rollover)
    $ch = curl_init($java_location);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

    $response = curl_exec($ch);
    $output = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    //Close resource
    curl_close($ch);

    //Echo back status code from curl...
    if( $output == 201 ) {
      header("HTTP/1.1 201 Created");
    } else {
      header("HTTP/1.1 500 Server Error");
    }
    exit(0);

} catch (Exception $e) {pm($e);}

header("HTTP/1.1 500 Server Error");
exit(1);
