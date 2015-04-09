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

global $USER, $CFG, $PAGE, $OUTPUT;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
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

$PAGE->requires->string_for_js('requestedmessage', 'local_rollover');
$PAGE->requires->string_for_js('errormessage', 'local_rollover');

$PAGE->requires->js("/local/rollover/scripts/js/underscore-min.js");
$PAGE->requires->js("/local/rollover/scripts/js/hideshow.js");
$PAGE->requires->js("/local/rollover/scripts/js/submit.js");
$PAGE->requires->js("/local/rollover/scripts/js/autoComplete.js");

$PAGE->requires->css("/local/rollover/scripts/css/styles.css");

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

$short_code_label_text = get_string('short_code_label_text', 'local_rollover');
$description_label_text = get_string('description_label_text', 'local_rollover');

$form = <<<HTML5
    <div class='rollover_item'>
        <form method='post' id='rollover_form_%1\$d' name='rollover_form_%1\$d' action=''>
            <table class='rollover_layout'>
                <tr>
                    <td class='rollover_crs_title'>
                        <div class='arrow'></div>
                        <h3><a href="">%3\$s</a></h3>
                        <p class='rollover_shrt_code'><span class='rollover_txt_head'>$short_code_label_text: </span><span class='rollover_sc_num'>%2\$s</span></p>
                        <p class='rollover_desc'><span class='rollover_txt_head'>$description_label_text: </span>%4\$s</p>
                    </td>
                    %5\$s
                </tr>
            </table>
        </form>
    </div>
HTML5;

$rollover_button_text = get_string('rollover_button_text', 'local_rollover');

$from_form = <<<HTML5
<td class='rollover_crs_from'>
    <div class='arrow'></div>
    <div class='from_form'>
        <input type='text' class='rollover_crs_input' placeholder='%1\$s' />
        <ul class='rollover_advanced_options'>
            {$moduleoptions}
        </ul>
        <div class='more_advanced_wrap'>
            <div class='more_advanced'>
                <div class='text'>Show advanced options</div>
                %s%s
                <div class="clearfix"></div>
                <div class='arrow_border'></div>
                <div class='arrow_light'></div>
            </div>
        </div>
        <input type="hidden" name="id_from" class="id_from" value=""/>
        <input type="hidden" name="src_from" class="src_from" value=""/>
        <input type="hidden" name="id_to" class="id_to" value="%d"/>
        <input type="hidden" name="src_to" class="src_to" value="%s"/>
        <button type='buttons' class='rollover_crs_submit'>{$rollover_button_text}</button>
    </div>
</td>
HTML5;

$from_processing = '<td class="rollover_crs_from processing"><div class="arrow"></div>'. get_string('processingmessage', 'local_rollover').'</td>';

$from_requested = '<td class="rollover_crs_from pending"><div class="arrow"></div>'. get_string('requestedmessage', 'local_rollover').'</td>';

$form_error = '<td class="rollover_crs_from error"><div class="arrow"></div>'. get_string('errormessage', 'local_rollover').'</td>';

$form_complete = <<<HTML5
    <td class="rollover_crs_from success">
        <div class="arrow"></div>
        <h3>Completed</h3>
        <p>Your rollover request has been completed but the module appears to be empty.</p>
        <br />
        %s
        <p>WARNING: this may result in the deletion of content from the module!</p>
    </td>
HTML5;

echo $renderer->search_box($search);

$courses = \local_rollover\User::get_target_list();
if (!empty($search)) {
    $courses = \local_rollover\Utils::filter_target_list($courses, $search);
}

if (empty($courses)) {
    echo "<p>" . get_string('no_courses', 'local_rollover') . "</p>";
    echo $OUTPUT->footer();
    die;
}

// Top page content
echo get_string('top_page_help', 'local_rollover');

// Add in our confirmation dialog box and other error blocks ready
echo $renderer->dialogs();

// Pagination stuff.
$offset = $currentpage == 0 ? 0 : $currentpage * $perpage;
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
        $desc = get_string('no_course_description_text', 'local_rollover');
    }

    switch ($course->rollover_status) {
        case \local_rollover\Rollover::STATUS_SCHEDULED:
            $from_content = $from_requested;
        break;

        case \local_rollover\Rollover::STATUS_BACKED_UP:
        case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
        case \local_rollover\Rollover::STATUS_IN_PROGRESS:
            $from_content = $from_processing;
        break;

        case \local_rollover\Rollover::STATUS_COMPLETE:
            if ((int)$course->module_count <= 2) {
                $a = $OUTPUT->single_button(new moodle_url('/local/rollover/', array(
                    'id' => $course->rollover_id,
                    'action' => 'undo'
                )), 'Click here to undo the rollover.');
                $from_content = sprintf($form_complete, $a);
            } else {
                continue;
            }
        break;

        case \local_rollover\Rollover::STATUS_ERROR:
            $from_content = $form_error;
        break;

        default:
            $from_content = sprintf($from_form, $course->shortname, $OUTPUT->help_icon('advanced_opt_help', 'local_rollover'), $course->id, $CFG->kent->distribution);
        break;
    }

    $coursename = html_writer::link(new moodle_url('/course/view.php', array(
        'id' => $course->id
    )), $course->fullname);

    printf($form, $course->id, $course->shortname, $coursename, $desc, $from_content);
}

// Show paging if we have more courses than per page allowed.
if ($totalcourses > $perpage) {
    echo $OUTPUT->paging_bar($totalcourses, $currentpage, $perpage, $PAGE->url);
}

echo "<div class='paging-spacer'></div>";

$urls = $CFG->kent->paths;
unset($urls['connect']);
$urls = array_keys($urls);
$urls = array_reverse($urls);
$urls = json_encode($urls);

echo '<script type="text/javascript">
    window.rollover_paths = JSON.parse(\''.$urls.'\');
    window.pendingMessage = "'. get_string('requestedmessage', 'local_rollover').'";
    window.errorMessage = "'. get_string('errormessage', 'local_rollover').'";
</script>';

echo $OUTPUT->footer();
