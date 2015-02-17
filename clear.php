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

require_once(dirname(__FILE__) . '/../../config.php');

$id = required_param("id", PARAM_INT);
$confirmed = optional_param("confirm", false, PARAM_BOOL);

$context = \context_course::instance($id);
$PAGE->set_context($context);
$PAGE->set_url('/local/rollover/clear.php');
$PAGE->set_pagelayout('admin');

require_capability('moodle/course:update', $context);

$course = $DB->get_record('course', array(
	'id' => $id
), '*', MUST_EXIST);

if ($confirmed) {
	require_sesskey();

	\local_rollover\Rollover::remove_course_contents($id);

	// Reset any rollovers.
    $objs = $SHAREDB->get_records('shared_rollovers', array(
    	'to_course' => $id,
		'to_env' => $CFG->kent->environment,
		'to_dist' => $CFG->kent->distribution
    ));
    foreach ($objs as $obj) {
	    $obj->status = self::STATUS_DELETED;
	    $SHAREDB->update_record('shared_rollovers', $obj);
	}

	redirect(new \moodle_url('/my/'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading("Clear Course: {$course->shortname} ({$course->fullname})");

echo '<p>Are you sure you want to clear out this course? This will delete ALL contents.</p>';

$url = new \moodle_url('/local/rollover/clear.php', array(
	'id' => $id,
	'confirm' => '1'
));
echo $OUTPUT->single_button($url, 'Yes');

echo $OUTPUT->footer();
