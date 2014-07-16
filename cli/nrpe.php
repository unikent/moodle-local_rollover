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
 * Nagios alerts for Rollover.
 */

define('CLI_SCRIPT', true);
define('NRPE_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');

$errored = $SHAREDB->count_records('rollovers', array(
    'status' => 3
));

$queued = $SHAREDB->count_records('rollovers', array(
    'status' => 0
));

$queued += $SHAREDB->count_records('rollovers', array(
    'status' => 1
));

$inprogress = $SHAREDB->count_records('rollovers', array(
    'status' => 4
));

$threshold = 5;

$status = 0;
$messages = array();

if ($errored > 0) {
    $status = 1;
    $messages[] = $errored . ' failed rollovers.';
}

if ($queued > $threshold) {
    $status = $status == 1 ? 1 : 2;
    $messages[] = $queued . ' queued rollovers.';
}

if ($inprogress > $threshold) {
    $status = $status == 1 ? 1 : 2;
    $messages[] = $inprogress . ' in progress rollovers.';
}

echo implode(' ', $messages);
exit($status);