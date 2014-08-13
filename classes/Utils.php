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
}