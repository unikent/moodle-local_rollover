<?php

/**
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod
 * @subpackage newmodule
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $USER, $CFG, $PAGE, $OUTPUT;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

$systemcontext = context_system::instance();
require_login();

if(!kent_has_edit_course_access() && !has_capability('moodle/site:config', $systemcontext)) {
    throw new required_capability_exception($systemcontext, 'moodle/course:update', 'no_permissions', 'local_rollover');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/rollover/index.php');
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add(get_string('pluginname', 'local_rollover'));

$PAGE->set_title(get_string('pluginname', 'local_rollover'));
$PAGE->set_heading(get_string('pluginname', 'local_rollover'));

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('migrate');
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->jquery_plugin('blockui', 'theme_kent');

$PAGE->requires->js("/local/rollover/scripts/js/underscore-min.js");
$PAGE->requires->js("/local/rollover/scripts/hideshow.js");
//$PAGE->requires->js("/local/rollover/scripts/autoComplete.js");
$PAGE->requires->js("/local/rollover/scripts/submit.js");

$PAGE->requires->string_for_js('requestedmessage', 'local_rollover');
$PAGE->requires->string_for_js('errormessage', 'local_rollover');

// Build a list of rollover targets
$targets = array();
foreach ($CFG->connect->rollover_targets as $target) {
    $targets[$target] = $CFG->kent->paths[$target];
}

// Init rollovers
$PAGE->requires->js_init_call('M.local_rollover.init', array(json_encode($targets)), false, array(
    'name' => 'local_rollover',
    'fullpath' => '/local/rollover/scripts/js/rollover.js',
    'requires' => array("node", "io", "dump", "json-parse")
));

$PAGE->requires->css("/local/rollover/scripts/css/styles.min.css");

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_rollover'));

echo '<script type="text/javascript">
window.autoCompleteUrl="' . $CFG->kent->paths[LIVE_MOODLE] . 'local/rollover/modulelist/index.php?action=allmodlist&orderbyrole=1";
window.twentyTwelveAutoCompleteUrl = "' . $CFG->kent->paths['2012'] . 'local/rollover/modulelist/index.php?action=allmodlist&orderbyrole=1";
window.archiveAutoCompleteUrl ="' . $CFG->kent->paths['archive'] . 'local/rollover/modulelist/index.php?action=allmodlist&orderbyrole=1";
window.pendingMessage = "'. get_string('requestedmessage', 'local_rollover').'";
window.errorMessage = "'. get_string('errormessage', 'local_rollover').'";
</script>';

$module_list = kent_get_formated_module_list();

//TODO - move this to function and pass in shortcode and embed into the form name.
//TODO - Pass in schedule.php location rather than hard code it.  Set as a global config? ... overkill?
$short_code_label_text = get_string('short_code_label_text', 'local_rollover');
$description_label_text = get_string('description_label_text', 'local_rollover');

$form = <<< HEREDOC
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
HEREDOC;

$search_placeholder = get_string('search_placeholder', 'local_rollover');
$advanced_options_label = get_string('advanced_options_label', 'local_rollover');
$rollover_button_text = get_string('rollover_button_text', 'local_rollover');

$from_form = <<< HEREDOC
<td class='rollover_crs_from'>
    <div class='arrow'></div>
    <div class='from_form'>
        <input type='text' class='rollover_crs_input' placeholder='$search_placeholder' value='%1\$s'/>
        <ul class='rollover_advanced_options'>
            $module_list
        </ul>
        <div class='more_advanced_wrap'>
            <div class='more_advanced'>
                <div class='text'>Show advanced options</div>
                %2\$s
                <div style=' clear: both'></div>
                <div class='arrow_border'></div>
                <div class='arrow_light'></div>
                
            </div>
        </div>
        <input type="hidden" name="id_from" class="id_from" value=""/>
        <input type="hidden" name="src_from" class="src_from" value=""/>
        <input type="hidden" name="id_to" class="id_to" value="%3\$d"/>
        <button type='buttons' class='rollover_crs_submit'>$rollover_button_text</button>
    </div>
</td>
HEREDOC;

$from_processing = '<td class="rollover_crs_from processing"><div class="arrow"></div>'. get_string('processingmessage', 'local_rollover').'</td>';

$from_requested = '<td class="rollover_crs_from pending"><div class="arrow"></div>'. get_string('requestedmessage', 'local_rollover').'</td>';

$form_error = '<td class="rollover_crs_from error"><div class="arrow"></div>'. get_string('errormessage', 'local_rollover').'</td>';

$search = optional_param('srch', null, PARAM_TEXT);

echo '<div id="rollover_search">
        <form action="'. $CFG->wwwroot . '/local/rollover/index.php" method="get">
        <input type="text" id="srch" name="srch" value="' . $search . '"placeholder="SEARCH FOR ROLLOVER"/>
        <input type="submit" id="srch_submit" value="Search" />
        </form>
        </div>';

$courses = kent_get_empty_courses($search);

if (!empty($courses)) {
    
    //Top page content
    echo get_string('top_page_help', 'local_rollover');

    //Add in our confirmation dialog box and other error blocks ready
    echo '<div id="dialog_sure">'.get_string('are_you_sure_text', 'local_rollover').'</div>';
    echo '<div id="dialog_id_from_error">'.get_string('rollover_from_error_text', 'local_rollover').'</div>';
    echo '<div id="dialog_id_to_error">'.get_string('rollover_to_error_text', 'local_rollover').'</div>';
    echo '<div id="dialog_autocomplete_error">'.get_string('rollover_autocomplete_error', 'local_rollover').'</div>';

    $no_course_description_text = get_string('no_course_description_text', 'local_rollover');

    // pagination stuff
    $baseurl = new moodle_url($PAGE->URL, array('srch'=>$search));
    $current_page = optional_param('page', 0, PARAM_INT);
    $per_page = 20;
    $offset = $current_page == 0 ? 0 : $current_page * $per_page;
    $total_courses = count($courses);

    // get the slice of $courses for this page
    $show_courses = array_slice($courses, $offset, $per_page);

    //Show paging if we have more courses than per page allowed.
    if($total_courses > $per_page){
        echo $OUTPUT->paging_bar($total_courses, $current_page, $per_page, $baseurl);
    }

    foreach ($show_courses as $course) {
        $desc = $no_course_description_text;
        if (!empty($course->summary)) {
            $desc = $course->summary;
        }

        $coursename = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $course->fullname);

        //Extract the shortname without year - only grabs the first
        $pattern = "([a-zA-Z]{2,4}[0-9]{1,4})";
        preg_match($pattern, $course->shortname, $matches);
        $shortcode = "";
        if($matches != FALSE){
            $shortcode = $matches[0];
        }

        switch (kent_get_current_rollover_status($course->id)) {
            case 'requested':
                $from_content = $from_requested;
                break;
            case 'processing':
                $from_content = $from_processing;
                break;
            case 'completed':
                //Should not be used as the form should not show complete items
                $from_content = $from_requested;
                break;
            case 'errored':
                $from_content = $form_error;
                break;
            default:
                $from_content = sprintf($from_form, $shortcode, $OUTPUT->help_icon('advanced_opt_help', 'local_rollover'), $course->id);
        }

        printf($form, $course->id, $course->shortname, $coursename, $desc, $from_content);
    }

    //Show paging if we have more courses than per page allowed.
    if($total_courses > $per_page){
        echo $OUTPUT->paging_bar($total_courses, $current_page, $per_page, $baseurl);
    }

    echo "<div class='paging-spacer'></div>";

} else {
    echo "<p>" . get_string('no_courses', 'local_rollover') . "</p>";
}

echo $OUTPUT->footer();
