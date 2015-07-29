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
 * Local stuff for Kent rollover
 *
 * @package    local_kent
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$services = array(
    'Rollover service' => array(
        'functions' => array (
            'get_rollover_status',
            'schedule_rollover',
            'search_rollover_source_list'
        ),
        'requiredcapability' => '',
        'restrictedusers' => 0,
        'enabled' => 1
    )
);

$functions = array(
    'get_rollover_status' => array(
        'classname'   => 'local_rollover\api',
        'methodname'  => 'get_status',
        'description' => 'Get rollover status.',
        'type'        => 'read'
    ),
    'schedule_rollover' => array(
        'classname'    => 'local_rollover\api',
        'methodname'   => 'schedule',
        'description'  => 'Schedule a rollover.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update'
    ),
    'search_rollover_source_list' => array(
        'classname'    => 'local_rollover\api',
        'methodname'   => 'search_sources',
        'description'  => 'Search a list of rollover sources.',
        'type'         => 'read'
    )
);
