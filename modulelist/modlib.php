<?php
defined('MOODLE_INTERNAL') || die();

/**
* Function to present JSON data
*/
function kent_present_json($data){
    //Package up and return our JSON
    header('Content-type: application/json');
    echo json_encode($data);
}


/**
* Function to sanitize and sort GET params as well as set some defaults.
*/
function kent_get_data(){

    $data = array();

    $data['max_length'] = 5; //Max length of search string

    //Get our info from get string and sanitize sufficiently.
    $data['terms'] = (isset($_GET['terms']) ? trim(filter_var($_GET['terms'], FILTER_SANITIZE_STRING)) : '');
    $data['max_records'] = ((isset($_GET['max_records']) && ((int)$_GET['max_records'] > 0)) ? trim(filter_var($_GET['max_records'], FILTER_SANITIZE_NUMBER_INT)) : 0);
    $data['contentless'] = ((isset($_GET['contentless']) && $_GET['contentless']==1) ? TRUE : FALSE);
    $data['orderbyrole'] = ((isset($_GET['orderbyrole']) && $_GET['orderbyrole']==1) ? TRUE : FALSE);


    return $data;

}


/**
* Function to bolt on total modules.
*/
function kent_total_courses(&$data){
    //Give total of modules incase we need it
    $data['total_courses'] = (isset($data['courses']) ? count($data['courses']) : 0);

    if($data['total_courses'] == 0){
        $data['errors'][] = 'No modules found.  Check search criteria or access to Moodle instance.';
        $data['status'] = FALSE;
    }
}


/**
* Function to construct search and result data for JSON
*
* @param array by reference $data -- Data object to add in reuslts and errors
*/
function kent_modlist_search(&$data){

    //Split up terms
    $search_term_array = explode(" ", $data['terms']);
    //Then carry out search

    $data['courses'] = kent_search_user_courses($data['action'], $search_term_array, -1, $data['more_courses'], $data['max_records'], $data['contentless'], $data['orderbyrole']);

    // This list is all the same modules, sort reverse alphabetically so the last year is at the top of the list if we have modules
    if (count($data['courses']) <= 0) {
        $data['errors'][] = 'No modules were found!';
    }

    $data['status'] = TRUE;
}


/**
 * Search all modules for ones whose shortname and fullname match the search terms
 * Discovered modules must be updateable by the logged in user and have some content
 *
 * This function was written for the Kent_Rollover block, but nabbed and tweaked for the module list web service to show modules
 * which could be used as a source module for a content rollover.
 *
 * @param String or Array of Strings $searchterms - phrases to match against in module short or fullname. If a string, it is split into an array of strings around spaces
 * @param int $omit_course a single module id to omit in the search results (usually the one the user is in)
 * @param boolean $ignore_empty Don't list empty module (default false)
 * @param &boolean $more_courses passed by reference, if there were more modules other than the ones returned, this is set to true
 * @param int $max_records this field sets the maximum number of results to return. Default is 25.
 * @param bool $contentless Set to FALSE by default not fetch modules without content
 * @return Array of formatted shortnames (of the form: NAME (YYYY/YYYY) ) indexed by the module ids.
 */
function kent_search_user_courses($type, $searchterms, $omit_course=-1, &$more_courses=null, $max_records=0, $contentless=FALSE, $orderbyrole=FALSE) {

    global $CFG, $USER, $DB;

    if (!is_array($searchterms)) $searchterms = explode(array(" ",","), $searchterms);

    // all found modules will be formated and placed in the following array which is returned
    $courses_returned = array();
    $more_courses = false;

    $context = context_system::instance();
    $adminuseraccess = has_capability('moodle/site:config', $context);

    // build some neat SQL to search first the module shortname and then the module fullname
    foreach(array('shortname', 'fullname') as $course_field) {
    //foreach(array('shortname', 'fullname', 'summary') as $course_field) {

        $search_phrase = "";
        $content_restriction = "";
        $order_by = "";

        if ($type == 'modlist'){
            $search_phrase = "c.{$course_field} LIKE '{$searchterms[0]}%'";
        } else { //We do a standard search with multiple bits
            //This will be slow, but not as slow as it used to be...
            foreach ($searchterms as $searchterm) {
                if ($search_phrase) $search_phrase .= ' AND ';
                $search_phrase .= " c.{$course_field} LIKE '%{$searchterm}%' ";
            }
        }

        $content_courses = kent_rollover_enrol_get_my_courses('id, shortname, modinfo, summary, visible', 'shortname ASC', 0, 999999);

        $list = "";
        foreach($content_courses["courses"] as $tmp_course){
             $list .= $tmp_course->id . ",";
        }
        $list = rtrim($list, ",");

        if ($search_phrase != ""){
            $search_phrase .= " AND";
        }

        //Now check and get ones with content only .. ignore removed category 58 on Live
        $sql = "SELECT c.id, c.shortname, c.fullname, c.category, ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
         FROM {$CFG->prefix}course c
         JOIN {$CFG->prefix}context ctx
         ON (c.id = ctx.instanceid AND ctx.contextlevel=".CONTEXT_COURSE.")
         WHERE {$search_phrase} c.category != 58 AND c.category != 0 AND c.id IN ($list)
         AND (c.id = (SELECT course FROM {$CFG->prefix}course_modules WHERE course=c.id LIMIT 0,1)
              OR c.id = (SELECT course FROM {$CFG->prefix}course_sections WHERE course=c.id AND section!=0 AND summary is not null AND summary !='' LIMIT 0,1)
         )";

        //Override query if an admin
        if($adminuseraccess){
            $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.fullname, c.summary, c.visible
                    FROM {$CFG->prefix}course c
                    WHERE {$search_phrase} (c.id = (SELECT course FROM {$CFG->prefix}course_modules WHERE course=c.id LIMIT 0,1)
                    OR c.id = (SELECT course FROM {$CFG->prefix}course_sections WHERE course=c.id AND section!=0 AND summary is not null AND summary !='' LIMIT 0,1) AND c.category != 58 AND c.category != 0
                    ORDER BY c.shortname DESC";
        }
        
        $course_search_rs = $DB->get_recordset_sql($sql);
        // run the module search query
        if ($course_search_rs) {

            // count the total number of modules here
            $course_count = 1;

            foreach ($course_search_rs as $course) {

                // ignore module if its the omit_course module
                if ( $course->id == $omit_course ) continue;

                // Have we reached the limit of the number of modules to return?
                if ($max_records > 0 && $course_count > $max_records){
                    $more_courses = true;
                    break;
                }

                // check the user is able to use this module in a rollover

                /*** BEGIN this module is a valid one to use for a rollover so add to the return_array ***/

                // we're going to change the text so the year is printed nicely (yyyy/yyyy)
                $matches = array();

                // Look to see if a year is part of the module name (4 digits) as long as its not of the form xxxx/xxxx or part of a larger string
                $found = preg_match_all('/[ (]([0-9]{4})/', $course->shortname, $matches);

                if ($found !== FALSE && $found > 0) {
                    // if the year is found on its own, replace it with: last-year/this-year notation
                    $module_year = "(" . ($matches[$found][0] - 1) . "/" . $matches[$found][0] . ")";
                    $courses_returned[$course->id]['shortname'] = str_replace($matches[$found][0], $module_year, $course->shortname);
                } else {
                    // couldn't find a year so just print the shortname
                    $courses_returned[$course->id]['shortname'] = $course->shortname;
                }

                //Also add in fullname
                $courses_returned[$course->id]['fullname'] = $course->fullname;

                /*** END ***/

                // incremenet the counter to the end of the recordset to determine the max number of results
                $course_count++;

            }

        }

        // as soon as one search has some results, stop further searches
        if (count($courses_returned)>0) break;

    }

    // return the formated list of discovered modules
    return $courses_returned;
}


/**
 * Get a list of modules depending on role.  Admin can see everything
 */
function kent_get_all_user_courses(&$data){
    global $USER;

    $context = context_system::instance();

    //If we are an admin, then we need to see all modules
    if (has_capability('moodle/site:config', $context)){
        $data['courses'] = kent_get_all_content_courses($data['max_records'], $data['contentless']);
    } else {
        $data['courses'] = kent_get_own_courses($data['max_records'], $data['contentless'], $data['orderbyrole']);
    }

    $data['status'] = TRUE;

}


/**
 * Returns list of modules for user which has content
 * @param int $max_records Set to 0 by default to fetch all. Set max records if you want to limit how much is returned.
 * @param bool $contentless Set to FALSE by default not fetch modules without content
 */
function kent_get_own_courses($max_records=0, $contentless=FALSE, $orderbyrole=FALSE){

    global $CFG, $USER, $DB;

    $content_courses = kent_rollover_enrol_get_my_courses('id, shortname, modinfo, summary, visible', 'shortname ASC', 0, 999999);

    $course_list = array();

    $list = "";
    foreach($content_courses["courses"] as $tmp_course){
         $list .= $tmp_course->id . ",";
    }
    $list = rtrim($list, ",");

    //Now check and get ones with content only
    $tmpsql = "SELECT c.id, c.shortname, c.fullname, c.category, ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
     FROM {$CFG->prefix}course c
     JOIN {$CFG->prefix}context ctx
     ON (c.id = ctx.instanceid AND ctx.contextlevel=".CONTEXT_COURSE.")
     WHERE c.category != 0 AND c.category != 58 AND c.id IN ($list)
     AND (c.id = (SELECT course FROM {$CFG->prefix}course_modules WHERE course=c.id LIMIT 0,1)
          OR c.id = (SELECT course FROM {$CFG->prefix}course_sections WHERE course=c.id AND section!=0 AND summary is not null AND summary !='' LIMIT 0,1)
     )";


     if ($results = $DB->get_records_sql($tmpsql)) {
         $course_list = array();
         $count = 1;
         foreach($results as $result){
            if ($max_records > 0 && $count > $max_records) break;
            $course_id = $result->id;
            $coursecontext = context_course::instance($result->id);
            if (has_capability('moodle/course:update', $coursecontext)) {
                $course_list[$course_id]['shortname'] = $result->shortname;
                $course_list[$course_id]['fullname'] = $result->fullname;
                ++$count;
            }
         }
     }

    return $course_list;

}


/**
 * Returns all modules with content.  For admin use only!
 * @param int $max_records Set to 0 by default to fetch all. Set max records if you want to limit how much is returned.
 * @param bool $contentless Set to FALSE by default not fetch modules without content
 */
function kent_get_all_content_courses($max_records=0, $contentless=FALSE) {

    global $USER, $CFG, $DB;

    $course_list = array();
    $fields = "";
    $order_by = "";
    $content_restriction = "";

    $context = context_system::instance();
    //Only return everything if an admin...
    if (has_capability('moodle/site:config', $context)){

        $fields = "c.id, c.shortname, c.fullname";
        //$fields = "c.id, c.shortname";
        $order_by = " ORDER BY c.shortname DESC";

        if(!$contentless){
            $content_restriction = " AND (c.id = (SELECT course FROM {$CFG->prefix}course_modules WHERE course=c.id LIMIT 0,1) OR c.id = (SELECT course FROM {$CFG->prefix}course_sections WHERE course=c.id AND section!=0 AND summary is not null AND summary !='' LIMIT 0,1))";
        }

        //Context doesn't seem to be needed - keep this here just in case (Goes underneath FROM of query below.
        //                JOIN {$CFG->prefix}context ctx
        //                ON (c.id = ctx.instanceid AND ctx.contextlevel=50)

        $sql = "SELECT {$fields}
                FROM {$CFG->prefix}course c
                WHERE category != 58 AND category != 0{$content_restriction}{$order_by}" ;

        // pull out all modules matching
        if ($courses = $DB->get_records_sql($sql)) {
                // loop throught them
                $count = 1;
                foreach ($courses as $course) {
                    if ($max_records > 0 && $count > $max_records) break;
                    if ($course->id == 1) continue;
                    $course_list[$course->id]['shortname'] = $course->shortname;
                    $course_list[$course->id]['fullname'] = $course->fullname;
                    ++$count;
                }
            }
    }

    return $course_list;
}