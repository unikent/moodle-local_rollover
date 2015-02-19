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

/**
 * Local stuff for Moodle Rollover
 *
 * @package    local_rollover
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rollover;

defined('MOODLE_INTERNAL') || die();

/**
 * Connect Sources container
 */
class Sources {

    /**
     * Returns a list of sources for rollover
     * 
     * @return array
     */
    public static function get_course_list($dist = '', $search = '') {
        global $CFG, $USER, $SHAREDB;

        $params = array(
            'current_env' => $CFG->kent->environment,
            'current_dist' => empty($dist) ? $CFG->kent->distribution : $dist
        );

        $admin = has_capability('moodle/site:config', \context_system::instance());

        $sql = 'SELECT sc.* FROM {shared_courses} sc';

        if (!$admin) {
            $sql .= ' INNER JOIN {shared_course_admins} sca
                        ON sca.moodle_env=sc.moodle_env
                        AND sca.moodle_dist=sc.moodle_dist
                        AND sca.courseid=sc.moodle_id
                        AND sca.username=:username';
            $params['username'] = $USER->username;
        }

        $sql .= ' WHERE sc.moodle_id > 1 AND sc.moodle_env = :current_env';

        if ($dist !== '*') {
            $sql .= empty($dist) ? ' AND sc.moodle_dist <> :current_dist' : ' AND sc.moodle_dist = :current_dist';
        }

        if (!empty($search)) {
            $sql .= ' AND sc.shortname LIKE :shortname';
            $params['shortname'] = $search;
        }

        return $SHAREDB->get_records_sql($sql, $params);
    }

    /**
     * Returns a list of sources for rollover
     * 
     * @return array
     */
    public static function get_source_list($target) {
        return static::get_course_list($target);
    }

    /**
     * Returns a list of targets for rollover
     * 
     * @return array
     */
    public static function get_target_list() {
        global $CFG;
        return static::get_course_list($CFG->kent->distribution);
    }
}
