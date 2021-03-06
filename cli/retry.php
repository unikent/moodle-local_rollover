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
 * Kent rollover retry script.
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/moodlelib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        // The ID of the rollover to retry.
        'id' => false,
        'force' => false,
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

// Is this a backup?
$params = array(
    'id' => $options['id'],
    'from_env' => $CFG->kent->environment,
    'from_dist' => $CFG->kent->distribution
);

if (!$options['force']) {
    $params['status'] = \local_rollover\Rollover::STATUS_WAITING_SCHEDULE;
}

$event = $SHAREDB->get_record('rollovers', $params);

if ($event) {
    $event->status = \local_rollover\Rollover::STATUS_SCHEDULED;
    $SHAREDB->update_record('rollovers', $event);

    $task = new \local_rollover\task\backup();
    $task->set_custom_data(array(
        "id" => $event->id
    ));
    $task->execute();

    echo "Backup complete\n";
}

// Is this an import?

$importevent = $SHAREDB->get_record('rollovers', array(
    'id' => $options['id'],
    'to_env' => $CFG->kent->environment,
    'to_dist' => $CFG->kent->distribution,
    'status' => \local_rollover\Rollover::STATUS_BACKED_UP
));

if (!$importevent) {
    if (!$event) {
        cli_error('Could not find event.');
    }

    die();
}

$importevent->status = \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED;
$SHAREDB->update_record('rollovers', $importevent);

// Import.
$task = new \local_rollover\task\import();
$task->set_custom_data(array(
    "id" => $importevent->id
));
$task->execute();

echo "Restore complete\n";
