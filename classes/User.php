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
class User
{
    /**
     * Returns all courses we can rollover into.
     */
    public static function get_target_list() {
        global $CFG, $DB, $USER;

        $category = \local_catman\core::get_category();
        $sharedb = $CFG->kent->sharedb['name'];

        $params = array(
            'env' => $CFG->kent->environment,
            'dist' => $CFG->kent->distribution,
            'rmcatid' => $category->id,
            'ctxlevel' => CONTEXT_COURSE
        );

        // If we are not admin, then we need a magic join to only grab courses
        // we have permissions too.
        $join = "";
        if (!has_capability('moodle/site:config', \context_system::instance())) {
            $join = <<<SQL
            INNER JOIN (
                SELECT ira.contextid
                FROM {role_assignments} ira
                WHERE ira.userid = :userid AND ira.roleid IN (
                    SELECT rc.roleid
                    FROM {role_capabilities} rc
                    WHERE rc.capability = :capability
                        AND rc.permission = 1
                    GROUP BY rc.roleid
                )
                GROUP BY ira.contextid
            ) ra
            ON ra.contextid = ctx.id
SQL;
            $params['userid'] = $USER->id;
            $params['capability'] = 'moodle/course:update';
        }

        // We want to get all empty courses that don't have a rollover associated with them.
        $sql = <<<SQL
            SELECT
                c.id, c.shortname, c.fullname, c.category, c.summary, c.visible,
                ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel,
                COUNT(cm.id) as module_count,
                COALESCE(r.status, '-1') AS rollover_status
            FROM {course} c
            INNER JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :ctxlevel
            $join
            LEFT OUTER JOIN `$sharedb`.`rollovers` r
                ON r.to_course = c.id
                AND r.to_env = :env
                AND r.to_dist = :dist
            LEFT OUTER JOIN {course_modules} cm
                ON cm.course = c.id
            WHERE c.id > 1 AND c.category <> :rmcatid
            GROUP BY c.id
            HAVING module_count <= 2 OR rollover_status != '-1'
SQL;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns true if a user has any access to edit any course.
     */
    public static function has_course_update_role() {
        global $DB, $USER;

        if (has_capability('moodle/site:config', \context_system::instance())) {
            return true;
        }

        $sql = <<<SQL
            SELECT COUNT(ra.id)
            FROM {role_assignments} ra
            WHERE ra.userid = :userid AND ra.roleid IN (
                SELECT rc.roleid
                FROM {role_capabilities} rc
                WHERE rc.capability = :capability AND rc.permission = 1
                GROUP BY rc.roleid
            )
SQL;
        return $DB->count_records_sql($sql, array(
            'userid' => $USER->id,
            'capability' => 'moodle/course:update'
        )) > 0;
    }
}
