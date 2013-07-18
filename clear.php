<?php
/**
 * This script provides a web service which will set off a course empty
 *
 * @copyright  2013 University of Kent (http://www.kent.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Web service accepts:
 *
 * course (GET data id as int)
 */

//Now some library includes
require_once('../../config.php');
require_once('../../lib/moodlelib.php');
require_once('lib.php');

// check that rollover is switched on in config and there is a valid $USER logged in.
if(!isset($CFG->kent_rollover_system) || !$CFG->kent_rollover_system || !isloggedin()){
   header('HTTP/1.1 500 Server Error');
   exit(1);
}

$data = array();

//Just santize everything as string for now.
foreach ($_POST as $data_key => $data_value){
    $data_key = filter_var($data_key, FILTER_SANITIZE_STRING);
    $data_value = filter_var($data_value, FILTER_SANITIZE_STRING);
    $data[$data_key] = $data_value;
}

global $DB;

// check the moodleness of this page request
$site = get_site();
if(!$site) die();

$course = intval($data['course']);

$context = get_context_instance(CONTEXT_COURSE, $course);

//Ensure that current user can access course and has correct permissions!
if($context != FALSE && has_capability('moodle/course:update', $context)) {

    //Delete the course but keep enrolments
    $options = array();
    $options['keep_roles_and_enrolments'] = 1;
    $options['keep_groups_and_groupings'] = 1;
    
    $status = remove_course_contents($course, FALSE, $options);

    //If we successfully removed course contents, lets tidy and remove any entry in the rollover_events table
    if($status){
        $sql = "DELETE FROM {rollover_events} WHERE to_course = ?";
        $DB->execute($sql, array($course));
    }

} 

if(isset($status) && $status){
   header("HTTP/1.1 201 Created");
   exit(0);
}

header('HTTP/1.1 500 Server Error');
exit(1);