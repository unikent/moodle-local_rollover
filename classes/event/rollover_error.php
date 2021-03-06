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

namespace local_rollover\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event Class
 */
class rollover_error extends \local_kent\event\sharedb_event
{
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'course';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return "Rollover Error";
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return 'The rollover with an ID of ' . $this->objectid . ' failed because of: "' . $this->other['message'] . '".';
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/rollover/');
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->courseid)) {
            throw new \coding_exception('The \'courseid\' must be set.');
        }

        if (!isset($this->other['message'])) {
            throw new \coding_exception('The \'message\' must be set.');
        }
    }
}