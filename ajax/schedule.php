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

$fromcourse = required_param("from_course", PARAM_INT);
$fromdist = required_param("from_dist", PARAM_ALPHANUM);
$tocourse = required_param("to_course", PARAM_INT);
$options = required_param("options", PARAM_RAW);

$context = context_course::instance($tocourse);
$PAGE->set_context($context);
$PAGE->set_url('/local/rollover/ajax/schedule.php');

require_login();
require_capability('moodle/course:update', $context);

$options = json_decode($options);
$id = \local_rollover\Rollover::schedule($fromdist, $fromcourse, $tocourse, $options);
if (!$id) {
    print_error("Error creating rollover entry (unknown error).");
}

echo $OUTPUT->header();
echo json_encode(array(
    "result" => 'success'
));