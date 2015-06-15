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
        'from' => PREVIOUS_MOODLE,
        'dry' => false,
        'mode' => 'exact'
    )
);

$username = exec('logname');
$user = $DB->get_record('user', array(
    'username' => $username
));

if ($user) {
    \core\session\manager::set_user($user);
    echo "Hello {$user->firstname}.\n";
}

raise_memory_limit(MEMORY_UNLIMITED);

// Grab a list of courses in this Moodle.
$courses = $DB->get_recordset('course');
foreach ($courses as $course) {
    $rc = new \local_rollover\Course($course);

    $matchtype = 'Exact';
    $match = $rc->exact_match($options['from']);
    if (!$match && $options['mode'] == 'approximate') {
        $match = $rc->best_match($options['from']);
        $matchtype = 'Approximate';
    }

    if (!$match) {
        echo "No match for {$course->shortname}.\n";
        continue;
    }

    echo "$matchtype match for {$course->shortname}: {$match->shortname}.\n";

    if (isset($options['dry']) && $options['dry']) {
        continue;
    }

    $rc->rollover($options['from'], $match->moodle_id);
}
$courses->close();
