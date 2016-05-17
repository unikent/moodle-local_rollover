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

    /** Course. */
    private $_course;

    /** Rollovers. */
    private $_rollovers;

    /**
     * Constructor
     */
    public function __construct($courseorid) {
        $this->courseid = $courseorid;

        if (is_object($courseorid)) {
            $this->_course = $courseorid;
            $this->courseid = $courseorid->id;
        }
    }

    /**
     * Get.
     */
    public function __get($name) {
        global $DB;

        if ($name == 'course') {
            if (!isset($this->_course)) {
                $this->_course = $DB->get_record('course', array('id' => $this->courseid));
            }

            return $this->_course;
        }

        return null;
    }

    /**
     * Get the best match for a course.
     */
    public function best_match($extdist) {
        global $SHAREDB;

        $shortname = $this->course->shortname;
        $list = explode(' ', $shortname);
        if (count($list) > 1) {
            $shortname = $list[0];
        }

        $like = $SHAREDB->sql_like('shortname', ':shortname', false, false);
        $matches = $SHAREDB->get_records_select('courses', 'moodle_dist=:moodle_dist AND ' . $like, array(
            'moodle_dist' => $extdist,
            'shortname' => "%{$shortname}%"
        ));

        if (empty($matches)) {
            return null;
        }

        if (count($matches) == 1) {
            return reset($matches);
        }

        // Calculate best match based on levenshtein distance of shortnames.
        $best = null;
        $bestlev = 99999;

        foreach ($matches as $match) {
            $distance = levenshtein($this->course->shortname, $match->shortname);
            if ($distance >= 0 && $distance < $bestlev) {
                $best = $match;
                $bestlev = $distance;
            }
        }

        return $best;
    }

    /**
     * Get an exact match for a course.
     */
    public function exact_match($extdist) {
        global $SHAREDB;

        return $SHAREDB->get_record('courses', array(
            'moodle_dist' => $extdist,
            'shortname' => $this->course->shortname
        ));
    }

    /**
     * Get an SDS match for a course.
     */
    public function sds_match($extdist, $extdb) {
        global $DB;

        $connect = \local_connect\course::get_by('mid', $this->course->id);
        if (!$connect || is_array($connect)) {
            return null;
        }

        if (preg_match('/moodle_[a-z0-9]+/', $extdb) !== 1) {
            debugging("Invalid DB name.");
            return null;
        }

        $sql = <<<SQL
        SELECT cc.mid as moodle_id, cc.module_delivery_key as shortname
        FROM $extdb.mdl_connect_course cc
        WHERE cc.module_delivery_key=:mdk
SQL;

        $ret = $DB->get_record_sql($sql, array('mdk' => $connect->module_delivery_key));
        if (!empty($ret->moodle_id)) {
            return $ret;
        }

        return null;
    }

    /**
     * Schedule a rollover on this course.
     */
    public function rollover($fromdist, $fromid) {
        Rollover::schedule($fromdist, $fromid, $this->courseid);
    }

    /**
     * What is the current rollover status of this module.
     */
    public function get_rollover() {
        global $CFG, $SHAREDB;

        if (!isset($this->_rollovers)) {
            $this->_rollovers = array();
            $this->_rollovers = $SHAREDB->get_records('rollovers', array(
                'to_env' => $CFG->kent->environment,
                'to_dist' => $CFG->kent->distribution,
                'to_course' => $this->courseid
            ));
        }

        if (empty($this->_rollovers)) {
            return null;
        }

        return end($this->_rollovers);
    }

    /**
     * What is the current rollover status of this module.
     */
    public function has_active_rollover() {
        $status = $this->get_status();

        return (
            $status === Rollover::STATUS_SCHEDULED ||
            $status === Rollover::STATUS_BACKED_UP ||
            $status === Rollover::STATUS_IN_PROGRESS ||
            $status === Rollover::STATUS_RESTORE_SCHEDULED ||
            $status === Rollover::STATUS_WAITING_SCHEDULE
        );
    }

    /**
     * What is the current rollover status of this module.
     */
    public function get_status() {
        $rollover = $this->get_rollover();
        return !$rollover ? Rollover::STATUS_NONE : (int)$rollover->status;
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

        if (!$this->is_empty()) {
            return false;
        }

        $status = $this->get_status();
        return $status == Rollover::STATUS_NONE || $status == STATUS_DELETED;
    }

    /**
     * Undo any previous rollover for a course.
     */
    public function undo_rollovers() {
        global $CFG, $SHAREDB;

        $select = 'to_course = :course AND to_dist = :dist AND (status = :complete OR status = :error)';
        $rollovers = $SHAREDB->get_records_select('rollovers', $select, array(
            'dist' => $CFG->kent->distribution,
            'course' => $this->courseid,
            'complete' => \local_rollover\Rollover::STATUS_COMPLETE,
            'error' => \local_rollover\Rollover::STATUS_ERROR
        ), '', 'id');

        foreach ($rollovers as $rollover) {
            \local_rollover\Rollover::undo($rollover->id);
        }
    }
}