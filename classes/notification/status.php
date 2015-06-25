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

namespace local_rollover\notification;

defined('MOODLE_INTERNAL') || die();

class status extends \local_notifications\notification\base {
    /**
     * Returns the component of the notification.
     */
    public static function get_component() {
        return 'local_rollover';
    }

    /**
     * Returns the table name the objectid relates to.
     */
    public static function get_table() {
        return 'course';
    }

    /**
     * Returns the level of the notification.
     */
    public function get_level() {
        if (isset($this->other['complete'])) {
            return \local_notifications\notification\base::LEVEL_INFO;
        }

        return \local_notifications\notification\base::LEVEL_DANGER;
    }

    /**
     * Is the action dismissable?
     */
    public function is_dismissble() {
        return true;
    }

    /**
     * Returns the notification.
     */
    public function render() {
        global $SHAREDB;

        if (isset($this->other['complete'])) {
            return $this->render_complete();
        }

        $rollover = $SHAREDB->get_record('shared_rollovers', array(
            'id' => $this->other['rolloverid']
        ));

        if ($rollover->status == \local_rollover\Rollover::STATUS_ERROR) {
            return "The rollover for this course failed! Please contact your FLT.";
        }

        return "This course is currently being rolled over.";
    }

    /**
     * Render a rollover complete message.
     */
    private function render_complete() {
        global $CFG;

        $rollover = $this->other['record'];
        $manual = $this->other['manual'];

        $moduletext = ($manual ? 'manually-created ' : '') . 'module';
        $message = "This {$moduletext} has been rolled over from a previous year.";

        // Get the rollover.
        if ($rollover && isset($CFG->kent->paths[$rollover->from_dist])) {
            $url = $CFG->kent->paths[$rollover->from_dist] . "course/view.php?id=" . $rollover->from_course;

            $message = "This {$moduletext} has been rolled over from ";
            $message .= "<a href=\"{$url}\" class=\"alert-link\" target=\"_blank\">Moodle {$rollover->from_dist}</a>.";
        }

        // Is this a manual course?
        if ($manual) {
            $message .= ' An administrator must re-link any previous meta-enrolments.';
            $message .= 'Information on how to do this can be found on the ';
            $message .= '<a href="http://www.kent.ac.uk/elearning/files/moodle/moodle-meta-enrolment.pdf" class="alert-link" target="_blank">';
            $message .= 'E-Learning website</a>.';
        }

        return $message;
    }

    /**
     * Setter for $customdata.
     * @param mixed $customdata (anything that can be handled by json_encode)
     */
    public function set_custom_data($customdata) {
        if (empty($customdata['rolloverid'])) {
            throw new \moodle_exception("rolloverid cannot be empty!");
        }

        return parent::set_custom_data($customdata);
    }
}