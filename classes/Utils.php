<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_rollover;

defined('MOODLE_INTERNAL') || die();

/**
 * Rollover stuff
 */
class Utils
{
    /**
     * Returns all courses we can rollover into.
     */
    public static function get_target_list() {
        global $CFG, $DB;

        $sharedb = $CFG->kent->sharedb['name'];

        // We want to get all empty courses that don't have a rollover associated with them.
        $sql = <<<SQL
            SELECT
                c.id, c.shortname, c.fullname, c.category, c.summary, c.visible,
                ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel,
                COUNT(cm.id) as module_count,
                COALESCE(r.status, '-1') AS rollover_status
            FROM {course} c
            INNER JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel=:ctxlevel
            LEFT OUTER JOIN `$sharedb`.`rollovers` r
                ON r.to_course = c.id
                AND r.to_env = :env
                AND r.to_dist = :dist
            LEFT OUTER JOIN {course_modules} cm
                ON cm.course = c.id
            WHERE c.id > 1
            GROUP BY c.id
            HAVING module_count <= 2 OR rollover_status != '-1'
SQL;

        $params = array(
            'env' => $CFG->kent->environment,
            'dist' => $CFG->kent->distribution,
            'ctxlevel' => CONTEXT_COURSE
        );

        $courses = $DB->get_records_sql($sql, $params);

        // If we are admin, great! Return here.
        if (has_capability('moodle/site:config', \context_system::instance())) {
            return $courses;
        }

        // Get our courses.
        $mycourses = enrol_get_my_courses('id');

        // Filter out those we cannot edit.
        foreach ($mycourses as $id => $course) {
            if (!has_capability('moodle/course:update', \context_course::instance($id)) || !isset($courses[$id])) {
                unset($mycourses[$id]);
                continue;
            }

            $mycourses[$id] = $courses[$id];
        }

        return $mycourses;
    }

    /**
     * Filter a target list by given terms.
     * Returns all matching courses.
     */
    public static function filter_target_list($list, $terms) {
        $terms = strip_tags(strtolower($terms));

        $list = array_filter($list, function($course) use ($terms) {
            if (strpos(strtolower($course->shortname), $terms) !== false) {
                return true;
            }

            if (strpos(strtolower($course->fullname), $terms) !== false) {
                return true;
            }

            if (strpos(strtolower($course->summary), $terms) !== false) {
                return true;
            }

            return false;
        });

        return $list;
    }

    /**
     * Grab a list of modules that can rollover.
     */
    public static function get_rollover_course_modules() {
        global $CFG, $DB;

        $modules = $DB->get_records("modules", array(
            'visible' => 1
        ));

        foreach ($modules as $mod) {
            $name = $mod->name;
            $path = $CFG->dirroot . "/mod/" . $name;
            $filename = "/backup/moodle2/backup_{$name}_activity_task.class.php";
            if (file_exists($path . $filename)) {
                yield $mod;
            }
        }
    }

    /**
     * Returns true if a use has any access to edit any course.
     */
    public static function can_user_edit_a_course() {

        if (has_capability('moodle/site:config', \context_system::instance())) {
            return true;
        }
    }
}