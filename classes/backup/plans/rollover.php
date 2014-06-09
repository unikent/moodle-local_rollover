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
 * Our rollover backup plan
 */
class rollover extends base implements \loggable
{
    /**
     * Allow the controller to set specific settings.
     */
    public function set_prefs($prefs) {
        $users = $this->get_setting('users');
        $users->set_value(0);

        parent::set_prefs($prefs);
    }

    /**
     * Should we process the requested pref?
     */
    protected function can_process($pref) {
        return $pref != 'id' || $pref != 'users';
    }
}