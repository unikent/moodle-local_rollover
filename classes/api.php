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
            )
        ));
    }

    /**
     * Search a list of modules.
     *
     * @param $modulecode
     * @return array [string]
     * @throws \invalid_parameter_exception
     */
    public static function get_status($courseid) {
        global $DB;

        $params = self::validate_parameters(self::get_status_parameters(), array(
            'courseid' => $courseid
        ));
        $courseid = $params['courseid'];

        $course = new \local_rollover\Course($courseid);
        $status = $course->get_status();
        $stringstatus = 'Unknown';
        $progress = -1;

        switch ($status) {
            case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
                $stringstatus = 'Scheduling';
                $progress = 15;
            break;

            case \local_rollover\Rollover::STATUS_SCHEDULED:
                $stringstatus = 'Creating backup';
                $progress = 25;
            break;

            case \local_rollover\Rollover::STATUS_BACKED_UP:
                $stringstatus = 'Backup complete';
                $progress = 50;
            break;

            case \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED:
                $stringstatus = 'Restore Scheduled';
                $progress = 75;
            break;

            case \local_rollover\Rollover::STATUS_COMPLETE:
                $stringstatus = 'Rollover Complete';
                $progress = 100;
            break;

            case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                $stringstatus = 'In Progress';
            break;

            case \local_rollover\Rollover::STATUS_ERROR:
                $stringstatus = 'Rollover Error';
            break;

            case \local_rollover\Rollover::STATUS_DELETED:
            case \local_rollover\Rollover::STATUS_NONE:
            default:
                $stringstatus = 'No Rollover';
            break;
        }

        return array(
            'status_code' => $status,
            'status_str' => $stringstatus,
            'progress' => $progress
        );
    }

    /**
     * Returns description of get_status() result value.
     *
     * @return external_description
     */
    public static function get_status_returns() {
        return new external_single_structure(array(
            'status_code' => new external_value(PARAM_TEXT, 'The status code.'),
            'status_str' => new external_value(PARAM_TEXT, 'The status string.'),
            'progress' => new external_value(PARAM_INT, 'The current level of progress.')
        ));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function schedule_parameters() {
        return new external_function_parameters(array(
            'target' => new external_value(
                PARAM_INT,
                'The local course ID',
                VALUE_REQUIRED
            ),
            'source' => new external_value(
                PARAM_INT,
                'The remote shared_course ID',
                VALUE_REQUIRED
            )
        ));
    }

    /**
     * Search a list of modules.
     *
     * @param $modulecode
     * @return array [string]
     * @throws \invalid_parameter_exception
     */
    public static function schedule($target, $source) {
        global $DB, $SHAREDB;

        $params = self::validate_parameters(self::schedule_parameters(), array(
            'target' => $target,
            'source' => $source
        ));
        $target = $params['target'];
        $source = $params['source'];

        require_capability('moodle/course:update', \context_course::instance($target));

        $source = $SHAREDB->get_record('courses', array(
            'id' => $source
        ), '*', MUST_EXIST);

        // Undo any existing, completed rollover.
        $course = new \local_rollover\Course($target);
        $course->undo_rollovers();

        ob_start();
        $id = \local_rollover\Rollover::schedule($source->moodle_dist, $source->moodle_id, $target);
        ob_end_clean();

        if (!$id) {
            throw new \moodle_exception("Error creating rollover entry (unknown error).");
        }

        return array('id' => $id);
    }

    /**
     * Returns description of schedule() result value.
     *
     * @return external_description
     */
    public static function schedule_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'The rollover ID.')
        ));
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function search_sources_parameters() {
        return new external_function_parameters(array(
            'search' => new external_value(
                PARAM_TEXT,
                'The search string',
                VALUE_REQUIRED
            ),
            'target' => new external_value(
                PARAM_INT,
                'The local course ID',
                VALUE_DEFAULT, 0
            ),
            'dist' => new external_value(
                PARAM_TEXT,
                'The search string',
                VALUE_DEFAULT, '*'
            )
        ));
    }

    /**
     * Search a list of modules.
     *
     * @return array [string]
     * @throws \invalid_parameter_exception
     */
    public static function search_sources($search, $target, $dist) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::search_sources_parameters(), array(
            'search' => $search,
            'target' => $target,
            'dist' => $dist
        ));
        $search = $params['search'];
        $target = $params['target'];
        $dist = $params['dist'];

        // Grab source list.
        $sources = \local_rollover\Sources::get_course_list($dist, "%{$search}%");
        if (empty($sources)) {
            return array();
        }

        // Build result.
        $data = array();
        foreach ($sources as $course) {
            // We can't rollover into self!
            if ($course->moodle_dist == $CFG->kent->distribution && $course->moodle_id == $target) {
                continue;
            }

            $data[] = array(
                'id' => $course->id,
                'moodle_dist' => $course->moodle_dist,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname
            );
        }

        return $data;
    }

    /**
     * Returns description of search_sources() result value.
     *
     * @return external_description
     */
    public static function search_sources_returns() {
        return new external_multiple_structure(new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'The shared course ID.'),
            'moodle_dist' => new external_value(PARAM_TEXT, 'The shared course dist.'),
            'shortname' => new external_value(PARAM_TEXT, 'The shared course shortname.'),
            'fullname' => new external_value(PARAM_TEXT, 'The shared course fullname.'),
        )));
    }
}
