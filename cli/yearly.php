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
 * Kent yearly rollover script.
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        'from' => LIVE_MOODLE,
        'dry' => false,
        'mode' => 'exact',
        'weeks' => '*',
        'manual' => false // Just manual rollovers?
    )
);

$username = exec('logname');
$user = $DB->get_record('user', array(
    'username' => $username
));

if ($user) {
    \core\session\manager::set_user($user);
    cli_writeln("Hello {$user->firstname}.");
}

raise_memory_limit(MEMORY_UNLIMITED);

// Grab a list of courses in this Moodle.
$courses = array();
if ($options['manual']) {
    $courses = $DB->get_recordset_sql('SELECT * FROM {course} WHERE shortname LIKE :shortname', array('shortname' => 'DP%'));
} else {
    $courses = $DB->get_recordset('course');
}

// Rollover.
$countmatch = 0;
$countnomatch = 0;
foreach ($courses as $course) {
    $rc = new \local_rollover\Course($course);
    if (!$rc->can_rollover()) {
        continue;
    }

    // Restrict to SDS modules beginning in weeks "x-y".
    if (!$options['manual'] && $options['weeks'] != '*') {
        $weeks = explode('-', $options['weeks']);
        $connect = \local_connect\course::get_by('mid', $course->id);

        // No connect course!
        if (!$connect) {
            continue;
        }

        // Handle merged courses.
        if (is_array($connect)) {
            $connect = reset($connect);

            if ($connect->is_version_merged()) {
                $connect = $connect->get_primary_version();
            }
        }

        if ($connect->module_week_beginning < $weeks[0] || $connect->module_week_beginning > $weeks[1]) {
            continue;
        }
    }


    $matchtype = 'Exact';
    $match = $rc->exact_match($options['from']);

    if (!$match && $options['mode'] == 'approximate') {
        $match = $rc->best_match($options['from']);
        $matchtype = 'Approximate';
    }

    if (!$match && $options['mode'] == 'sds') {
        $match = $rc->sds_match($options['from']);
        $matchtype = 'SDS';
    }

    if (!$match) {
        cli_writeln("No match for {$course->shortname}.");
        $countnomatch++;
        continue;
    }

    cli_writeln("{$matchtype} match for {$course->shortname}: {$match->shortname}.");
    $countmatch++;

    if (isset($options['dry']) && $options['dry']) {
        continue;
    }

    $rc->rollover($options['from'], $match->moodle_id);
}

$courses->close();

if (isset($options['dry']) && $options['dry']) {
    $total = $countmatch + $countnomatch;
    $perc = round(((float)$countmatch / (float)$total) * 100.0, 2);
    cli_writeln("Total: {$total}, matched: {$countmatch} ({$perc}%)");
}
