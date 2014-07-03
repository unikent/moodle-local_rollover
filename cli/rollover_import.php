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
 * Kent rollover import script.
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        // The ID of the course to import into.
        'course' => false,
        // The path of the course to get data from.
        'path' => false,
        // Where was this from?
        'from_dist' => false
    )
);

if (!$options['course']) {
    cli_error("Must specify course with --course=id!");
}

if (!$options['path']) {
    cli_error("Must specify path with --path=/path/!");
}

if (!$options['from_dist']) {
    cli_error("Must specify from_dist with --from_dist=2013!");
}

raise_memory_limit(MEMORY_UNLIMITED);

$settings = array(
    'id' => uniqid(),
    'tocourse' => $options['course'],
    'folder' => $options['path'],
    'event' => (object)array(
        'from_dist' => $options['from_dist']
    )
);

try {
    $controller = new \local_rollover\Rollover($settings);
    $controller->go();
} catch (moodle_exception $e) {
    cli_error($e->getMessage());
}