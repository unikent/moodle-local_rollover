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

define('AJAX_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/rollover/ajax/sources.php');

if (!isloggedin()) {
    print_error("You must be logged in.");
}

$dist = required_param('dist', PARAM_ALPHANUMEXT);
$sources = \local_rollover\Sources::get_source_list($dist);

echo $OUTPUT->header();
echo json_encode($sources);