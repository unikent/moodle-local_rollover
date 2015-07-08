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
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

if (!\local_connect\util\helpers::is_enabled() || !\local_kent\util\sharedb::available() || $CFG->kent->distribution == 'archive') {
    print_error('connect_disabled', 'local_connect');
}

if (!\local_kent\User::has_course_update_role($USER->id)) {
    throw new required_capability_exception(\context_system::instance(), 'moodle/course:update', 'no_permissions', 'local_rollover');
}

$PAGE->set_context(\context_system::instance());

$currentpage = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$search = trim(optional_param('q',   '', PARAM_TEXT));
$action = optional_param('action',   '', PARAM_ALPHA);

$params = array(
    'page' => $currentpage,
    'perpage' => $perpage
);
if (!empty($search)) {
    $params['search'] = $search;
}
if (!empty($action)) {
    $params['action'] = $action;
}
$PAGE->set_url('/local/rollover/index.php', $params);

$PAGE->set_pagelayout('admin');
$PAGE->navbar->add(get_string('pluginname', 'local_rollover'));

$PAGE->set_title(get_string('pluginname', 'local_rollover'));
$PAGE->set_heading(get_string('pluginname', 'local_rollover'));

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('migrate');
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->jquery_plugin('blockui', 'theme_kent');

$PAGE->requires->strings_for_js(array('requestedmessage', 'errormessage'), 'local_rollover');

$PAGE->requires->js("/local/rollover/scripts/underscore-min.js");
$PAGE->requires->js("/local/rollover/scripts/hideshow.js");
$PAGE->requires->js("/local/rollover/scripts/submit.js");
$PAGE->requires->js("/local/rollover/scripts/autoComplete.js");

$PAGE->requires->css("/local/rollover/less/build/build.css");

$renderer = $PAGE->get_renderer('local_rollover');

$notification = '';
if ($action == 'undo') {
    require_sesskey();
    $id = required_param("id", PARAM_INT);

    // Try to undo the rollover.
    try {
        \local_rollover\Rollover::undo($id);
        $notification = $OUTPUT->notification("Rollover '$id' has been deleted.");
    } catch (\moodle_exception $e) {
        $notification = $OUTPUT->notification($e->getMessage());
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_rollover'));

if (!empty($notification)) {
    echo $notification;
    unset($notification);
}

$moduleoptions = "";
$modules = \local_rollover\Utils::get_rollover_course_modules();
foreach ($modules as $module) {
    $moduleoptions .= $renderer->module_option($module->name, get_string('modulename', $module->name));
}

echo $renderer->search_box($search);

$courses = \local_rollover\User::get_target_list();
if (!empty($search)) {
    $courses = \local_rollover\Utils::filter_target_list($courses, $search);
}

if (empty($courses)) {
    echo $renderer->no_courses();
    echo $OUTPUT->footer();
    die;
}

// Top page content
echo $renderer->help();

// Add in our confirmation dialog box and other error blocks ready
echo $renderer->dialogs();

// Pagination stuff.
$offset = ($currentpage == 0 ? 0 : $currentpage) * $perpage;
$totalcourses = count($courses);

// get the slice of $courses for this page
$show_courses = array_slice($courses, $offset, $perpage);

//Show paging if we have more courses than per page allowed.
if ($totalcourses > $perpage) {
    echo $OUTPUT->paging_bar($totalcourses, $currentpage, $perpage, $PAGE->url);
}

foreach ($show_courses as $course) {
    $desc = $course->summary;
    if (empty($course->summary)) {
        $desc = 'No description was found.';
    }

    switch ($course->rollover_status) {
        case \local_rollover\Rollover::STATUS_SCHEDULED:
            $from_content = $renderer->requested_form();
        break;

        case \local_rollover\Rollover::STATUS_BACKED_UP:
        case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
        case \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED:
        case \local_rollover\Rollover::STATUS_IN_PROGRESS:
            $from_content = $renderer->processing_form();
        break;

        case \local_rollover\Rollover::STATUS_ERROR:
        case \local_rollover\Rollover::STATUS_COMPLETE:
            if ((int)$course->module_count <= 2) {
                $from_content = $renderer->error_form($course->rollover_id);
            } else {
                continue;
            }
        break;

        default:
            $from_content = $renderer->rollover_form($moduleoptions, $course->shortname, $course->id, $CFG->kent->distribution);
        break;
    }

    $coursename = html_writer::link(new moodle_url('/course/view.php', array(
        'id' => $course->id
    )), $course->fullname);

    echo $renderer->course_form($course->id, $course->shortname, $coursename, $desc, $from_content);
}

// Show paging if we have more courses than per page allowed.
if ($totalcourses > $perpage) {
    echo $OUTPUT->paging_bar($totalcourses, $currentpage, $perpage, $PAGE->url);
}

$urls = $CFG->kent->paths;
unset($urls['connect']);
$urls = array_keys($urls);
$urls = array_reverse($urls);
$urls = json_encode($urls);

echo '<script type="text/javascript">
    window.rollover_paths = JSON.parse(\''.$urls.'\');
</script>';

echo $OUTPUT->footer();
