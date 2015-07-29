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
 * Local stuff for Moodle
 *
 * @package    local_rollover
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rollover;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_function_parameters;

/**
 * Rollover API.
 */
class api extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_status_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(
                PARAM_INT,
                'The course ID',
                VALUE_REQUIRED
            ),
            'asstring' => new external_value(
                PARAM_BOOL,
                'Return the status as a string?',
                VALUE_DEFAULT, false
            )
        ));
    }

    /**
     * Expose to AJAX
     * @return boolean
     */
    public static function get_status_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Search a list of modules.
     *
     * @param $modulecode
     * @return array [string]
     * @throws \invalid_parameter_exception
     */
    public static function get_status($courseid, $asstring) {
        global $DB;

        $params = self::validate_parameters(self::get_status_parameters(), array(
            'courseid' => $courseid,
            'asstring' => $asstring
        ));
        $courseid = $params['courseid'];
        $asstring = $params['asstring'];

        $course = new \local_rollover\Course($courseid);
        $status = $course->get_status();

        if ($asstring) {
            switch ($status) {
                case \local_rollover\Rollover::STATUS_SCHEDULED:
                    $status = 'Creating backup';
                break;

                case \local_rollover\Rollover::STATUS_BACKED_UP:
                    $status = 'Backup complete';
                break;

                case \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED:
                    $status = 'Restore Scheduled';
                break;

                case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                    $status = 'In Progress';
                break;

                case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
                    $status = 'Scheduling';
                break;

                case \local_rollover\Rollover::STATUS_COMPLETE:
                    $status = 'Rollover Complete';
                break;

                case \local_rollover\Rollover::STATUS_ERROR:
                    $status = 'Rollover Error';
                break;

                case \local_rollover\Rollover::STATUS_DELETED:
                case \local_rollover\Rollover::STATUS_NONE:
                default:
                    $status = 'No Rollover';
                break;
            }
        }

        return $status;
    }

    /**
     * Returns description of get_status() result value.
     *
     * @return external_description
     */
    public static function get_status_returns() {
        return new external_single_structure(array(
            new external_value(PARAM_TEXT, 'The status code or string.')
        ));
    }
}