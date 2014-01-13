<?php
/**
 * This script provides a web service which will return a list of modules or allow a search for modules which the user has access to update
 *
 * @copyright  2012 University of Kent (http://www.kent.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Web service accepts:
 *
 * action -- Currently either =modlist or =search or=allmodlist ... all pretty much the same, though modlist restricts search term to max length setting below (based on block code) and allmodlist gets everything
 * terms -- Space separated search terms
 * max_records -- Optional, if not set - 0 is passed by default and this gets everything.
 * contentless -- Optional, if not set - 0 is passed by default which means that it does not fetch contentless courses
 */
global $CFG, $USER;

require_once('../../../config.php');
require_once('modlib.php');
require_once('../lib.php');
require_once($CFG->libdir.'/adminlib.php');

// Check this is a valid user.
if (!isloggedin() or isguestuser()) {
    header('HTTP/1.0 401 Unauthorized', true, 401);
    exit(1);
}

// Check that rollover is switched on in config and there is a valid $USER logged in.
if (!\local_connect\utils::is_enabled()) {
    header('HTTP/1.0 401 Unauthorized', true, 401);
    exit(1);
}

//Some initilisations for the service
$max_length = 5;

//Check the moodleness of this page request
$site = get_site();
if(!$site) die();

//Set up the data array to turn into JSON at the end. //TODO - Maybe add other formats if required?
$data = array();
$data['status'] = FALSE; //Start with false status

//TODO!! -- Need to add in checks against SSO to then check against capabilities
//USING: has_capability('moodle/course:update', $context))

//Try and catch any problems (be it with filter_var or anything else
try {

    //Get our info from get string and sanitize sufficiently.
    $data['action'] = (isset($_GET['action']) ? strtolower(filter_var($_GET['action'], FILTER_SANITIZE_STRING)) : '');
    $data['terms'] = (isset($_GET['terms']) ? trim(filter_var($_GET['terms'], FILTER_SANITIZE_STRING)) : '');
    $data['max_records'] = ((isset($_GET['max_records']) && ((int)$_GET['max_records'] > 0)) ? trim(filter_var($_GET['max_records'], FILTER_SANITIZE_NUMBER_INT)) : 0);
    $data['contentless'] = ((isset($_GET['contentless']) && $_GET['contentless']==1) ? TRUE : FALSE);
    $data['orderbyrole'] = ((isset($_GET['orderbyrole']) && $_GET['orderbyrole']==1) ? TRUE : FALSE);


    if ($data['action'] == 'modlist'){ //Process a standard module list

        if ($data['terms'] != '' && strlen($data['terms']) > 2){
            //Terms in modlist refers to shortcode passed .. ensure length is suitably restricted as in rollover list
            $data['terms'] = (strlen($data['terms']) > $max_length ? substr($data['terms'], 0, $max_length) : $data['terms']);

            //Now carry out search
            kent_modlist_search($data);

        } else {
            $data['errors'][] = 'No shortcode set for module list, or shortcode was less than 2 characters.';
        }

    } elseif ($data['action'] == 'search') { //Process a search

        if ((strlen($data['terms']) < 2)){
            $data['errors'][] = 'Need at least two characters in the search term';
        } else {
            //Now carry out search
            kent_modlist_search($data);
        }

    } elseif ($data['action'] == 'allmodlist'){
        kent_get_all_user_courses($data);

    } else { //Invalid action
        $data['errors'][] = 'Invalid action, or no action specified.';
    }

//Give total of courses incase we need it
$data['total_courses'] = (isset($data['courses']) ? count($data['courses']) : 0);

if($data['total_courses'] == 0){
    $data['errors'][] = 'No courses found.  Check search criteria or access to Moodle instance.';
    $data['status'] = FALSE;
}

} catch (Exception $e) {pm($e);}

//Package up and return our JSON
header('Access-Control-Allow-Origin: *.kent.ac.uk');

// Support JSONP
if (isset($_GET['callback'])) {
    if (preg_match('/\W/', $_GET['callback'])) {
        header('HTTP/1.1 400 Bad Request');
        exit();
    }

    header('Content-type: application/javascript; charset=utf-8');
    echo sprintf('%s(%s);', $_GET['callback'], json_encode($data));
} else {
    header('Content-type: application/json');
    echo json_encode($data);
}