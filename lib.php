<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Returns list of modules in suitable format - just for testing.  Remove later.
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
 * Returns list of modules for user
 */
function kent_get_empty_courses($srch = ''){
    global $USER, $DB;

    $context = context_system::instance();

    //If we are an admin, then we need to see all modules
    if (has_capability('moodle/site:config', $context)){
        $courses = kent_get_all_courses();
    } else {
        $courses = kent_get_own_editable_courses();
    }



    if($srch != '') {
        $srch = strip_tags(strtolower($srch));
        $courses = array_filter($courses, function($c) use ($srch) { 
            if(strpos(strtolower($c->shortname), $srch) !== false) {
                return true;
            } elseif (strpos(strtolower($c->fullname), $srch) !== false) {
                return true;
            } elseif (strpos(strtolower($c->summary), $srch) !== false) {
                return true;
            } else {
                return false;
            }
        });
    }

    return $courses;

}


/**
 * Returns all modules that are empty and user with update module permissions can see
 */
function kent_get_own_editable_courses() {
    global $CFG, $USER, $DB;

    $category = \local_catman\core::get_category();

    $courselist = array();

    $contentcourses = kent_rollover_enrol_get_my_courses('id, shortname, summary, visible', 'shortname ASC', 0, 999999);

    $list = "";
    foreach ($contentcourses["courses"] as $tmpcourse) {
         $list .= $tmpcourse->id . ",";
    }
    $list = rtrim($list, ",");

    $sharedb = $CFG->kent->sharedb['name'];

    // Now check and get ones with content only.
    $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.category, c.summary, c.visible, ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel, rol.status AS rollover_status
     FROM {course} c
     LEFT JOIN `$sharedb`.`rollovers` rol ON rol.to_course = c.id AND rol.to_env = ? AND rol.to_dist = ?
     LEFT JOIN {course_sections} cse ON cse.course = c.id AND cse.summary != '' AND cse.section != 0
     JOIN {context} ctx ON (c.id = ctx.instanceid AND ctx.contextlevel=".CONTEXT_COURSE.")
     WHERE cse.section is null AND c.category != 0 AND c.category != {$category->id} AND c.id IN ($list)";

    // Pull out all module matching.

    if ($courses = $DB->get_records_sql($sql, array($CFG->kent->environment, $CFG->kent->distribution))) {
        foreach ($courses as $course) {
            if ($course->id == 1) {
                continue;
            }

            // Count number of mods in this module.
            $nomodules = intval($DB->count_records('course_modules', array(
                'course' => $course->id
            )));

            if ($nomodules <= 2) {
                $nomodulesfound = kent_check_mod_types($course->id);
                if (is_int($nomodulesfound) && $nomodulesfound > 0) {
                    continue;
                }
            } else if ($nomodules > 2) {
                // Skip if we have more than two modules.
                continue;
            }

            // If we had no rollover status, then set to none.
            if ($course->rollover_status === null) {
                $course->rollover_status = "none";
            }

            $courselist[$course->id] = $course;
        }

    }

    // Need to do the same now without the content check to get rollover status for those still running.
    // Now check and get ones with content only.
    $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.category, c.summary, c.visible, ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel, rol.status AS rollover_status
     FROM {course} c
     LEFT JOIN `$sharedb`.`rollovers` rol ON rol.to_course = c.id AND rol.to_env = ? AND rol.to_dist = ?
     JOIN {context} ctx ON (c.id = ctx.instanceid AND ctx.contextlevel=".CONTEXT_COURSE.")
     WHERE (rol.status = 0 OR rol.status = 1 OR rol.status = 3) AND c.category != 0 AND c.category != {$category->id} AND c.id IN ($list)";

    if ($courses = $DB->get_records_sql($sql, array($CFG->kent->environment, $CFG->kent->distribution))) {
        foreach ($courses as $course) {
            if ($course->id == 1) {
                continue;
            }

            $courselist[$course->id] = $course;
        }
    }

    return $courselist;

}

/**
 * Returns all empty courses of courses, for whole site, or category.  For admin use only!
 */
function kent_get_all_courses() {
    global $USER, $CFG, $DB;

    $category = \local_catman\core::get_category();

    $sharedb = $CFG->kent->sharedb['name'];

    $params = array($CFG->kent->environment, $CFG->kent->distribution);
    $courselist = array();

    $context = context_system::instance();
    // Only return everything if an admin...
    if (has_capability('moodle/site:config', $context)) {

        // Select everything that has less than than 3 course modules.
        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.summary, c.visible, rol.status as rollover_status
                FROM {course} c
                LEFT JOIN `$sharedb`.`rollovers` rol ON rol.to_course = c.id AND rol.to_env = ? AND rol.to_dist = ?
                LEFT JOIN {course_sections} cse ON cse.course = c.id AND length(cse.summary)>0 AND cse.section != 0
                WHERE cse.section is null AND c.category != {$category->id}
                GROUP BY c.id
                ORDER BY c.shortname ASC";

        if ($courses = $DB->get_records_sql($sql, $params)) {
            foreach ($courses as $course) {
                if ($course->id == 1) {
                    continue;
                }

                // Count number of modules in this module.
                $nomodules = intval($DB->count_records('course_modules', array(
                    'course' => $course->id
                )));

                if ($nomodules <= 2) {
                    $nomodulesfound = kent_check_mod_types($course->id);
                    if (is_int($nomodulesfound) && $nomodulesfound > 0) {
                        continue;
                    }
                } else if ($nomodules > 2) {
                    // Skip if we have more than one module.
                    continue;
                }

                if ($course->rollover_status === null) {
                    $course->rollover_status = "none";
                }

                $courselist[$course->id] = $course;
            }
        }

        // Pick up any modules which may not be empty, because they are in rollover progress.
        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.summary, c.visible, rol.status as rollover_status
                FROM {course} c
                LEFT JOIN `$sharedb`.`rollovers` rol ON rol.to_course = c.id AND rol.to_env = ? AND rol.to_dist = ?
                WHERE rol.status <> 2
                ORDER BY c.shortname ASC";

        // Pull out all module matching.
        if ($courses = $DB->get_records_sql($sql, $params)) {
            foreach ($courses as $course) {
                if ($course->id == 1) {
                    continue;
                }

                $courselist[$course->id] = $course;
            }
        }

    }

    return $courselist;
}


/**
* Function to take caught errors and put them into JSON data
*
* @param object $object exception object
*/
function kent_json_errors($obj) {

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

    $m1_blacklist = array("ouwiki");

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
            // check not blacklisted
            if(!in_array($modname, $m1_blacklist)) {
                if (file_exists($moodle_one_modfile)) {
                    $rollover_mods[$modname]['moodle_1_support'] = TRUE;
                }
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

            //Get proper name of module
            $rollover_mods[$modname]['modulename'] = get_string('modulename', $modname);

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

       $module_list .= sprintf($list_item, $m1, $m2, ucfirst($dets['modulename']), strtolower($module));
    }

    return $module_list;
}

/*
 * Quick function to get list of rollover records
 */
function kent_get_rollover_records($course_id, $just_current=FALSE){
    global $CFG, $SHAREDB;
    $results = array();

    //See if we just want the top current item
    $limit = "";
    if($just_current){
        $limit = " LIMIT 0,1";
    }

    $sql = "SELECT * FROM rollovers WHERE to_course={$course_id} AND to_env = ? AND to_dist = ? ORDER BY id DESC{$limit}";

    //Loop through results and add to array to return
    if ($records = $SHAREDB->get_records_sql($sql, array($CFG->kent->environment, $CFG->kent->distribution))) {
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
    $status = -1;

    if(!empty($record) && isset($record->status)){
        $status = (int)$record->status;
    }

    return $status;
}


/*
 * Can we set a rollover? actually, this is more accurately defined as
 * 'should we show the rollover box?'
 *
 * @deprecated See local_rollover/Course::can_rollover
 */
function kent_rollover_ability($course_id, $status=""){
    //If status isn't passed, get it from course id
    if($status == ""){
        $status = kent_get_current_rollover_status($course_id);
    }

    //As well as status, will need to see that the course has no content
    $course_has_content = kent_course_has_content($course_id);

    // if($status != "processing" && !$course_has_content){
    //     return true;
    // }

    // Don't show the rollover box if the status is completed (it's done), or there
    // is no rollover status and the course already has content (no rolling over into
    // already-populated courses)
    if ($status == 'completed' || ($status == 'none' && $course_has_content) ) {
        return false;
    }

    // in all other cases, do show the rollover box
    return true;
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

    //Pull out an amount of assignments a user has of module update in total.  Acts as a check to see if a user should ever hit the rollover list page.
    if ($courses = $DB->get_record_sql($sql, $params)) {
        $assignments = (int)$courses->assignments;
        if($assignments > 0){
            return true;
        }

    }

    return false;

}


/**
 * Check if a specified module has any content based on modules and summaries
 * @param <int> $course_id - Moodle Course ID
 * @return <boolean> false if empty, true if not
 */
function kent_course_has_content($course_id){

    global $CFG, $DB;

    // Count number of non-empty summaries as our first check
    $sql = "SELECT COUNT(id) FROM {$CFG->prefix}course_sections WHERE course={$course_id} AND section!=0 AND summary is not null AND summary !=''";
    $no_summaries = (int) $DB->count_records_sql($sql);

    // if there are any non-empty summaries return true as it has content
    if ($no_summaries > 0) return true;

    // If not, then secondly count number of mods in this module
    $nomodules = intval($DB->count_records('course_modules',array('course' => $course_id)));

    if($nomodules <= 2){
        $nomodules = kent_check_mod_types($course_id);
        if (is_int($nomodules) && $nomodules > 0){
            return true; 
        }
    } elseif ($nomodules > 2) {
		return true; //For certain has content if more than two modules
	}

    // must be empty, return false
    return false;
}


/**
 * Function to find out if there are any news forums
 */
function kent_check_mod_types($course_id){

    global $DB, $CFG;

	// $sql = "SELECT COUNT(id) FROM {$CFG->prefix}course WHERE course={$course_id} AND type = 'news'";
    $sql = "SELECT COUNT(id) FROM {$CFG->prefix}course_modules AS cm WHERE course={$course_id}
                AND cm.module IN (SELECT id FROM {$CFG->prefix}modules AS m
                                  WHERE m.name != 'forum' AND m.name != 'aspirelists')";
    $nomodules = (int) $DB->count_records_sql($sql);

    return $nomodules;

}


/**
 * Function to set a module to ignore being rolled over - if user is starting a module from scratch
 */
function kent_set_ignore_rollover($course_id){
    print_error("No longer supported");
}


/**
 * Returns list of courses current $USER is enrolled in and can access
 *
 * - $fields is an array of field names to ADD
 *   so name the fields you really need, which will
 *   be added and uniq'd
 *
 * @param string|array $fields
 * @param string $sort
 * @param int $limit max number of courses
 * @return array
 */
function kent_rollover_enrol_get_my_courses($fields = NULL, $sort = 'sortorder ASC', $page, $perpage) {
    global $DB, $USER;

    // Guest account does not have any courses
    if (isguestuser() or !isloggedin()) {
        return(array());
    }

    $basefields = array('id', 'category', 'sortorder',
                        'shortname', 'fullname', 'idnumber',
                        'startdate', 'visible',
                        'groupmode', 'groupmodeforce');

    if (empty($fields)) {
        $fields = $basefields;
    } else if (is_string($fields)) {
        // turn the fields from a string to an array
        $fields = explode(',', $fields);
        $fields = array_map('trim', $fields);
        $fields = array_unique(array_merge($basefields, $fields));
    } else if (is_array($fields)) {
        $fields = array_unique(array_merge($basefields, $fields));
    } else {
        throw new coding_exception('Invalid $fields parameter in enrol_get_my_courses()');
    }
    if (in_array('*', $fields)) {
        $fields = array('*');
    }

    $orderby = "";
    $sort    = trim($sort);
    if (!empty($sort)) {
        $rawsorts = explode(',', $sort);
        $sorts = array();
        foreach ($rawsorts as $rawsort) {
            $rawsort = trim($rawsort);
            if (strpos($rawsort, 'c.') === 0) {
                $rawsort = substr($rawsort, 2);
            }
            $sorts[] = trim($rawsort);
        }
        $sort = 'c.'.implode(',c.', $sorts);
        $orderby = "ORDER BY $sort";
    }

    $wheres = array("c.id <> :siteid");
    $params = array('siteid'=>SITEID);

    if (isset($USER->loginascontext) and $USER->loginascontext->contextlevel == CONTEXT_COURSE) {
        // list _only_ this course - anything else is asking for trouble...
        $wheres[] = "courseid = :loginas";
        $params['loginas'] = $USER->loginascontext->instanceid;
    }

    $coursefields = 'c.' .join(',c.', $fields);

    $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
    $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
    $params['contextlevel'] = CONTEXT_COURSE;

    $wheres = implode(" AND ", $wheres);

    //note: we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there

    $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                     WHERE ue.status = :active AND e.status = :enabled AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
                   ) en ON (en.courseid = c.id)
           $ccjoin
             WHERE $wheres
          $orderby";
    $params['userid']  = $USER->id;
    $params['active']  = ENROL_USER_ACTIVE;
    $params['enabled'] = ENROL_INSTANCE_ENABLED;
    $params['now1']    = round(time(), -2); // improves db caching
    $params['now2']    = $params['now1'];

    //$totalcourses = count($DB->get_records_sql($sql, $params));
    //$courses = $DB->get_records_sql($sql, $params, $page, $perpage);
    $courses = $DB->get_records_sql($sql, $params);

    $totalcourses = count($courses);
    $courseset = array_slice($courses, $page, $perpage, true);

    // preload contexts and check visibility
    foreach ($courseset as $id=>$course) {
        context_helper::preload_from_record($course);
        /*if (!$course->visible) {
            if (!$context = context_course::instance($id)) {
                unset($courseset[$id]);
                continue;
            }
            if (!has_capability('moodle/course:viewhiddencourses', $context)) {
                unset($courseset[$id]);
                continue;
            }
        }*/
        if ($context = context_course::instance($id)) {
            if (has_capability('moodle/course:viewhiddencourses', $context)) {
                $course->user_can_view = true;
            } else {
                $course->user_can_view = false;
            }
            if (has_capability('moodle/course:visibility', $context)) {
                $course->user_can_adjust_visibility = true;
            } else {
                $course->user_can_adjust_visibility = false;
            }
        }
        $courseset[$id] = $course;
    }

    return array('totalcourses' => $totalcourses, 'courses' => $courseset);
}

/**
 * Is Connect enabled?
 */
function connect_isEnabled() {
    return $CFG->kent->environment == "dev" || ($CFG->kent->environment == "live" && $CFG->kent->distribution == LIVE_MOODLE);
}
