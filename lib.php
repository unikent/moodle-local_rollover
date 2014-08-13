<?php
defined('MOODLE_INTERNAL') || die();

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
