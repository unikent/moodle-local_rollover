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

    /** Cache of rollover objects related to the course. */
    private $rollovers;

    /**
     * Constructor
     */
    public function __construct($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Setup.
     */
    private function setup() {
        global $CFG, $SHAREDB;

        if (isset($this->rollovers)) {
            return;
        }

        $this->rollovers = $SHAREDB->get_records('rollovers', array(
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution,
            'to_course' => $this->courseid
        ));
    }

    /**
     * What is the current rollover status of this module.
     */
    public function get_status() {
        $this->setup();

        if (empty($this->rollovers)) {
            return Rollover::STATUS_NONE;
        }

        // Get the most recent rollover object.
        $rollover = array_pop($this->rollovers);
        return $rollover->status;
    }

    /**
     * Is this module empty?
     */
    public function is_empty() {
        return false;
    }
}