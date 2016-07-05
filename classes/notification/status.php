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

class status extends \local_notifications\notification\simplelist {
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
     * Default to false for lists.
     */
    public function is_dismissble() {
        return true;
    }

    /**
     * Default to false for lists.
     */
    public function is_expanded() {
        return true;
    }

    /**
     * Retrieve all items from the list.
     */
    public function get_items() {
        $items = array();
        if (!isset($this->other['complete'])) {
            return $items;
        }

        $items[] = 'Check links to external resources - they may not exist any more!';

        // Is this a manual course?
        if ($this->other['manual']) {
            $metalink = 'http://www.kent.ac.uk/elearning/files/moodle/moodle-meta-enrolment.pdf';
            $metalink = \html_writer::link($metalink, 'meta-enrolments', array(
                'class' => 'alert-link',
                'target' => '_blank'
            ));
            $message = "Re-link any previous {$metalink}.";

            $items[] = $message;
        }

        $modinfo = get_fast_modinfo($this->objectid);
        $modules = $modinfo->get_used_module_names();

        // CLA notifications.
        $clalink = \html_writer::link('https://www.kent.ac.uk/library/staff/cla.html', 'CLA requests', array(
            'class' => 'alert-link',
            'target' => '_blank'
        ));
        if (isset($modules['cla'])) {
            $items[] = "Check CLA activities and ensure any new {$clalink} are created 6 weeks before they will be needed.";
        } else {
            $items[] = "Ensure any new {$clalink} are created 6 weeks before they will be needed.";
        }

        // Reading lists.
        $readinglistlink = \html_writer::link('https://www.kent.ac.uk/library/staff/readinglists.html', 'reading list', array(
            'class' => 'alert-link',
            'target' => '_blank'
        ));
        $readinglists = new \mod_aspirelists\course($this->objectid);
        if ($readinglists->has_list()) {
            if (!$readinglists->is_published()) {
                $items[] = "Check and publish this year's {$readinglistlink}.";
            }
        } else {
            $items[] = "Create a {$readinglistlink} for your module if required.";
        }

        $items[] = 'Once done, dismiss this notification by clicking on the cross to the right.';

        return $items;
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
     * Returns some text (before the items).
     */
    protected function render_text() {
        global $SHAREDB;

        if (isset($this->other['complete'])) {
            return $this->render_complete();
        }

        $rollover = $SHAREDB->get_record('rollovers', array(
            'id' => $this->other['rolloverid']
        ));

        if ($rollover->status == \local_rollover\Rollover::STATUS_ERROR) {
            return "The rollover for this course failed! Please contact your FLT.";
        }

        return "This course is currently being rolled over.";
    }

    /**
     * Returns a rendered item.
     * @param $item
     * @return
     */
    protected function render_item($item) {
        return $item;
    }

    /**
     * Render a rollover complete message.
     */
    private function render_complete() {
        global $CFG;

        $rollover = $this->other['record'];

        $moduletext = ($this->other['manual'] ? 'manually-created ' : '') . 'module';
        $message = "This {$moduletext} has been rolled over from a previous year";

        // Get the rollover.
        if ($rollover && isset($CFG->kent->httppaths[$rollover->from_dist])) {
            $url = $CFG->kent->httppaths[$rollover->from_dist] . "course/view.php?id=" . $rollover->from_course;

            $message = "This {$moduletext} has been rolled over from ";
            $message .= \html_writer::link($url, "Moodle {$rollover->from_dist}", array(
                'class' => 'alert-link',
                'target' => '_blank'
            ));
        }

        $message .= ', you should now:';

        return $message;
    }

    /**
     * Setter for $customdata.
     * @param mixed $customdata (anything that can be handled by json_encode)
     */
    protected function set_custom_data($customdata) {
        if (empty($customdata['rolloverid'])) {
            throw new \moodle_exception("rolloverid cannot be empty!");
        }

        return parent::set_custom_data($customdata);
    }
}
