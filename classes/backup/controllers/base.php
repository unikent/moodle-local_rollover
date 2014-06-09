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

namespace local_rollover\backup\controllers;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Our primary backup controller
 */
class base extends \backup_controller implements \loggable
{
    protected $prefs;

    /**
     * Constructor
     */
    public function __construct($type, $id, $format, $interactive, $mode, $userid, $prefs = array()) {
        $this->prefs = $prefs;
        parent::__construct($type, $id, $format, $interactive, $mode, $userid);
    }

    /**
     * Grab a new plan.
     */
    protected function create_plan() {
        return new \local_rollover\backup\plans\base($this);
    }

    /**
     * Load our custom plan.
     */
    protected function load_plan() {
        $this->log('loading controller plan', \backup::LOG_DEBUG);

        $this->plan = $this->create_plan();
        $this->plan->build();

        $this->set_status(\backup::STATUS_PLANNED);
    }

    /**
     * Apply settings to the plan.
     */
    protected function apply_defaults() {
        parent::apply_defaults();

        $this->plan->set_prefs($this->prefs);
    }
}