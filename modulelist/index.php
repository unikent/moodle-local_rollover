<?php namespace r;
require_once 'lib/limonade.php';

l\option('base_uri', '/'); # '/' or same as the RewriteBase in your .htaccess

require_once('../../../config.php');
require_once('modlib.php');
require_once($CFG->libdir.'/adminlib.php');

global $USER;
//Check that rollover is switched on in config and there is a valid $USER logged in.
if(!isset($CFG->kent_rollover_system) || !$CFG->kent_rollover_system || !isloggedin()){
    $data = array();
    $error = 'Rollover system not enabled or user not authenticated.';
    $data['status'] = FALSE;
    $data['errors'][] = $error;
    kent_present_json($data);
    l\halt(NOT_FOUND, $error);
}

//Modlist routing.
l\dispatch('/modlist', 'r\modlist');
function modlist(){

    //Set up our data
    $data = kent_get_data();
    $data['action'] = 'modlist';

    if ($data['terms'] != '' && strlen($data['terms']) > 2){
        //Terms in modlist refers to shortcode passed .. ensure length is suitably restricted as in rollover list
        $data['terms'] = (strlen($data['terms']) > $data['max_length'] ? substr($data['terms'], 0, $data['max_length']) : $data['terms']);

        //Now carry out search
        kent_modlist_search($data);

    } else {
        $data['errors'][] = 'No shortcode set for module list, or shortcode was less than 2 characters.';
    }

    //Set any total data in then return JSON
    kent_total_courses($data);
    kent_present_json($data);

}

//Search routing.
l\dispatch('/search', 'r\search');
function search(){

    //Set up our data
    $data = kent_get_data();
    $data['action'] = 'modlist';

    if ((strlen($data['terms']) < 2)){
        $data['errors'][] = 'Need at least two characters in the search term';
    } else {
        //Now carry out search
        kent_modlist_search($data);
    }

    //Set any total data in then return JSON
    kent_total_courses($data);
    kent_present_json($data);
}

//Allmodlist routing.
l\dispatch('/allmodlist', 'r\allmodlist');
function allmodlist(){
    //Set up our data
    $data = kent_get_data();
    $data['action'] = 'modlist';

    kent_get_all_user_courses($data);
    
    //Set any total data in then return JSON
    kent_total_courses($data);
    kent_present_json($data);
}

//Kick everything off
l\run();

