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
 * Provides an interface between a Moodle course and the rollover system.
 */
class Course
{
    /** Course ID. */
    private $courseid;

    /**
     * Constructor
     */
    public function __construct($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * What is the current rollover status of this module.
     */
    public function get_status() {
        global $CFG, $SHAREDB;

        $rollovers = $SHAREDB->get_records('rollovers', array(
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution,
            'to_course' => $this->courseid
        ));

        if (empty($rollovers)) {
            return Rollover::STATUS_NONE;
        }

        // Get the most recent rollover object.
        $rollover = end($rollovers);
        return $rollover->status;
    }

    /**
     * Is this module empty?
     */
    public function is_empty() {
        global $DB;

        // Count number of non-empty summaries as our first check.
        $sql = "
            SELECT COUNT(id)
            FROM {course_sections}
            WHERE course = :course
                AND section != 0
                AND summary IS NOT NULL
                AND summary != ''
        ";

        $count = $DB->count_records_sql($sql, array(
            'course' => $this->courseid
        ));

        // If there are any non-empty summaries return false as it has content.
        if ($count > 0) {
            return false;
        }

        // If not, then secondly count number of mods in this module.
        $count = $DB->count_records('course_modules', array(
            'course' => $this->courseid
        ));

        // If there are any modules return false as it has content.
        // We have two here because there are two default modules.
        if ($count > 2) {
            return false;
        }

        // Must be empty, return true.
        return true;
    }

    /**
     * Can we rollover into this course?
     */
    public function can_rollover() {
        $context = \context_course::instance($this->courseid);
        if (!has_capability('moodle/course:update', $context)) {
            return false;
        }

        return $this->get_status() == Rollover::STATUS_NONE && $this->is_empty();
    }
}