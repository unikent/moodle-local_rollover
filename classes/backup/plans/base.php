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

namespace local_rollover\backup\plans;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Our primary backup plan
 */
class base extends \backup_plan implements \loggable
{
    /**
     * Allow the controller to set specific settings.
     */
    public function set_prefs($prefs) {
        $pattern = "/(?:[a-z][a-z]+)(_)(\d+)(_)(?:[a-z][a-z]+)/";

        // Loop through our settings array.
        foreach ($prefs as $pref => $value) {
            // Loops through backup settings.
            foreach ($this->settings as $key => $setting) {
                // Checks to see if it is a resource or global course setting.
                if (preg_match($pattern, $setting->get_name())) {
                    $parts = explode('_', $setting->get_name());

                    // Checks to see if our pref and the backup setting match.
                    if ($parts[0] == $pref) {
                        $setting->set_value((int)$value);
                    }
                }
            }
        }
    }
}