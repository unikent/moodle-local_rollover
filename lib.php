<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Returns list of courses in suitable format - just for testing.  Remove later.
 */
function kent_list_rollover_courses(){

    $courses = kent_get_empty_courses();

    if(!empty($courses)){
     
       $output = "<ul>";
       foreach($courses as $course){
           $output .= "<li>" . $course->id . " - " . $course->shortname . "</li>";
       }
       $output .= "</ul>";

       return $output;

    } else {
        return "<p>Sorry.  No courses are accessible.</p>";
    }


}


/**
 * Returns list of courses for user
 */
function kent_get_empty_courses(){
    global $USER, $DB;

    $context = get_context_instance(CONTEXT_SYSTEM);

    //TODO - Need to check the rollover log table once its ready as well maybe?
   
    //If we are an admin, then we need to see all courses
    if (has_capability('moodle/site:config', $context)){
        $courses = kent_get_all_courses();
    } else {
        $courses = kent_get_own_editable_courses();
    }

    return $courses;

}


/**
 * Returns all courses that are empty and user with update course permissions can see
 */
function kent_get_own_editable_courses(){

    global $CFG, $USER, $DB;

    $course_list = array();
    $params['userid'] = (int)$USER->id;
    $params['capability'] = 'moodle/course:update';

    $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.fullname, c.summary, c.visible, rol.what as rollover_status
            FROM {$CFG->prefix}context con
            JOIN {$CFG->prefix}role_assignments ra ON userid=:userid AND con.id=ra.contextid AND roleid IN (SELECT DISTINCT roleid FROM {$CFG->prefix}role_capabilities rc WHERE rc.capability=:capability AND rc.permission=1 ORDER BY rc.roleid ASC)
            JOIN mdl_course c ON c.id=con.instanceid
            LEFT JOIN {$CFG->prefix}rollover_events rol ON rol.to_course = c.id
            LEFT JOIN {$CFG->prefix}course_sections cse ON cse.course = c.id AND length(cse.summary)>0 AND cse.section != 0
            LEFT JOIN {$CFG->prefix}course_modules cms ON c.id = cms.course
            WHERE cms.course is null AND cse.section is null AND con.contextlevel=50
            ORDER BY c.shortname DESC";

    // pull out all course matching
    if ($courses = $DB->get_records_sql($sql, $params)) {

        foreach ($courses as $course) {
            if ($course->id == 1) continue;
            //If we had no rollover status, then set to none
            if($course->rollover_status === NULL){
                $course->rollover_status = "none";
            }
            $course_list[$course->id] = $course;
        }
    }

    return $course_list;

}





/**
 * Returns all empty courses of courses, for whole site, or category.  For admin use only!
 */
function kent_get_all_courses() {

    global $USER, $CFG, $DB;

    $params = array();
    $course_list = array();

    $context = get_context_instance(CONTEXT_SYSTEM);
    //Only return everything if an admin...
    if (has_capability('moodle/site:config', $context)){

//Don't seem to need context in this case. - Keep this here incase we ever do.
//        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.summary, c.visible, ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel, ctx.instanceid AS ctxinstance, cms.course, cse.section
//                FROM {$CFG->prefix}course c
//                LEFT JOIN {$CFG->prefix}context ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = 50)
//                LEFT JOIN {$CFG->prefix}course_sections cse ON cse.course = c.id AND length(cse.summary)>0 AND cse.section != 0
//                LEFT JOIN {$CFG->prefix}course_modules cms ON c.id = cms.course
//                WHERE cms.course is null AND cse.section is null
//                ORDER BY c.shortname ASC";

        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.summary, c.visible, cms.course, cse.section, rol.what as rollover_status
                FROM {$CFG->prefix}course c
                LEFT JOIN {$CFG->prefix}rollover_events rol ON rol.to_course = c.id
                LEFT JOIN {$CFG->prefix}course_sections cse ON cse.course = c.id AND length(cse.summary)>0 AND cse.section != 0
                LEFT JOIN {$CFG->prefix}course_modules cms ON c.id = cms.course
                WHERE cms.course is null AND cse.section is null 
                ORDER BY c.shortname DESC";

        // pull out all course matching
        if ($courses = $DB->get_records_sql($sql, $params)) {

            // loop throught them
            foreach ($courses as $course) {
                if ($course->id == 1) continue;
                //If we had no rollover status, then set to none
                if($course->rollover_status === NULL){
                    $course->rollover_status = "none";
                }
                
                $course_list[$course->id] = $course;
            }
        }
    }

    return $course_list;
}


/**
* Function to take caught errors and put them into JSON data
*
* @param object $object exception object
*/
function pm($obj) {

    $data['status'] = FALSE;
    $data['errors'][] = htmlentities(print_r($obj, TRUE));
    header('Content-type: application/json');
    echo json_encode($data);

}


/**
* Very simple function at present to do a santize string on all POST key=>vals
*/
function kent_filter_post_data(){

    $data = array();

    //Just santize everything as string for now.
    foreach ($_POST as $data_key => $data_value){
        $data_key = filter_var($data_key, FILTER_SANITIZE_STRING);
        $data_value = filter_var($data_value, FILTER_SANITIZE_STRING);
        $data[$data_key] = $data_value;
    }

    return $data;
    
}


/**
* Gets an array of all Moodle 2 modules (only if the folder exists in /MOD.  Returns flags for M2/M1 support and visibility. Returns FALSE if no modules.
*
* @param bool $only_visible -- Set to true if you only want mods which are visible in Moodle 2.X
* @param bool $must_have_m1_support -- Set to true if you only want mods which have Moodle 1.9 backup support
*/
function kent_get_rollover_modules($only_visible=FALSE, $must_have_m1_support=FALSE){

    global $CFG, $DB;
    $modules = $DB->get_records("modules");

    $rollover_mods = array();

    foreach ($modules as $mod){

        $modname = $mod->name;
        $modpath = "$CFG->dirroot/mod/$modname";


        //If we only want visible mods and our mod isn't visible - set our skip flag to true.
        $vis_skip = (($only_visible && $mod->visible != "1") ? TRUE : FALSE);

        if(file_exists($modpath) && !$vis_skip){

            $moodle_one_modfile = "$modpath/backup/moodle1/lib.php";
            $moodle_two_modfile = "$modpath/backup/moodle2/backup_".$modname."_activity_task.class.php";

            $moodle_one_class = "moodle1_mod_".$modname."_handler";
            $moodle_two_class = "backup_".$modname."_activity_task";

            //Moodle 1 backup/restore check
            $rollover_mods[$modname]['moodle_1_support'] = FALSE;
            if (file_exists($moodle_one_modfile)) {
                $rollover_mods[$modname]['moodle_1_support'] = TRUE;
            }

            $m1_skip = (($must_have_m1_support && !$rollover_mods[$modname]['moodle_1_support']) ? TRUE : FALSE);

            //Moodle 2 backup/restore check
            $rollover_mods[$modname]['moodle_2_support'] = FALSE;
            if (file_exists($moodle_two_modfile)) {
                $rollover_mods[$modname]['moodle_2_support'] = TRUE;
            }

            //Store ID and visibility
            $rollover_mods[$modname]['moodle_2_id'] = $mod->id;
            $rollover_mods[$modname]['visible'] = ($mod->visible == "1" ? TRUE : FALSE);

            //If we don't want mods without moodle 1.9 bkup support, then unset it.
            if($m1_skip){
                unset($rollover_mods[$modname]);
            }

        }

    }

    if(!empty($rollover_mods)){
        return $rollover_mods;
    } else {
        return FALSE;
    }

}


function kent_get_formated_module_list() {
    $module_list = '';
    $list_item = "<li class='rollover_option_item %1\$s %2\$s'><input class='rollover_checkbox' name='backup_%4\$s' type='checkbox' checked/>%3\$s</li>";
    
    $modules = kent_get_rollover_modules(TRUE);
    foreach($modules as $module => $dets) {
        
        $m1 = ($dets['moodle_1_support'] == TRUE) ? '': 'm1';
        $m2 = ($dets['moodle_2_support'] == TRUE) ? '': 'm2';
        
       $module_list .= sprintf($list_item, $m1, $m2, ucfirst($module), strtolower($module));
    } 
    
    return $module_list;
}

/*
 * Quick function to get list of rollover records
 */
function kent_get_rollover_records($course_id, $just_current=FALSE){
    global $CFG, $DB;
    $results = array();

    //See if we just want the top current item
    $limit = "";
    if($just_current){
        $limit = " LIMIT 0,1";
    }

    $sql = "SELECT * FROM {$CFG->prefix}rollover_events WHERE to_course={$course_id} ORDER BY id DESC{$limit}";

    //Loop through results and add to array to return
    if ($records = $DB->get_records_sql($sql)) {
        foreach($records as $record){
            $results[] = $record;
        }
    }

    return $results;
}

/*
 * Essentially a wrapper function for above to get the most current record
 */
function kent_get_current_rollover($course_id){
    $record = array();
    if($record = kent_get_rollover_records($course_id, TRUE)){
        return $record[0];
    }
    //Otherwise return empty array
    return $record;
    
}

/*
 * Get just status
 */
function kent_get_current_rollover_status($course_id){
    $record = kent_get_current_rollover($course_id);
    $status = "none";

    if(!empty($record) && isset($record->what)){
        $status = trim(strtolower($record->what));
    }
    
    return $status;
}


/*
 * Can we set a rollover?
 */
function kent_rollover_ability($course_id, $status=""){
    //If status isn't passed, get it from course id
    if($status == ""){
        $status = kent_get_current_rollover_status($course_id);
    }

    if($status != "processing"){
        return TRUE;
    }

    return FALSE;
}


/**
 * Returns TRUE or FALSE depending on if a user has any edit course access at all.
 */
function kent_has_edit_course_access(){

    global $CFG, $USER, $DB;

    $params['userid'] = (int)$USER->id;
    $params['capability'] = 'moodle/course:update';

    $sql = "SELECT COUNT(ra.id) as assignments
            FROM {$CFG->prefix}role_assignments ra
            WHERE userid=:userid
            AND ra.roleid IN (SELECT DISTINCT roleid FROM {$CFG->prefix}role_capabilities rc WHERE rc.capability=:capability AND rc.permission=1 ORDER BY rc.roleid ASC)";

    //Pull out an amount of assignments a user has of course update in total.  Acts as a check to see if a user should ever hit the rollover list page.
    if ($courses = $DB->get_record_sql($sql, $params)) {
        $assignments = (int)$courses->assignments;
        if($assignments > 0){
            return TRUE;
        }

    }

    return FALSE;

}


/**
 * Check if a specified course has any content based on modules and summaries
 * @param <int> $course_id - Moodle Course ID
 * @return <boolean> false if empty, true if not
 */
function kent_course_has_content($course_id){

    global $CFG, $DB;

    // count number of modules in this course
    $no_modules = intval($DB->count_records('course_modules',array('course' => $course_id)));

    // if course has modules return true as it has content
    if (is_int($no_modules) && $no_modules>0) return TRUE;

    // count number of non-empty summaries
    $sql = "SELECT COUNT(id) FROM {$CFG->prefix}course_sections WHERE course={$course_id} AND section!=0 AND summary is not null AND summary !=''";
    $no_modules = (int) $DB->count_records_sql($sql);

    // if there are any non-empty summaries return true as it has content
    if ($no_modules>0) return TRUE;

    // must be empty, return false
    return FALSE;
}