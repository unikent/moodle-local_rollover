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
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

global $USER;

/* $url = 'http://localhost/moodle/kent/modulelist/index.php?action=allmodlist';
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $output = curl_exec($ch);
  curl_close($ch);

  var_dump($output);
 */

$systemcontext = get_context_instance(CONTEXT_SYSTEM);
require_login();

if(!kent_has_edit_course_access() && !has_capability('moodle/site:config', $systemcontext)) {
    throw new required_capability_exception($systemcontext, 'moodle/course:update', 'no_permissions', 'local_rollover');
}


$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_url('/local/rollover/index.php');
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add(get_string('pluginname', 'local_rollover'));

$PAGE->set_title(get_string('pluginname', 'local_rollover'));
$PAGE->set_heading(get_string('pluginname', 'local_rollover'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_rollover'));

$scripts ='<link rel="stylesheet" href="scripts/css/ui-lightness/jquery-ui-1.8.17.custom.css" type="text/css" />';
$scripts .= '<link rel="stylesheet/less" type"text/css" href="styles.less">';
$scripts .='<script src="' . $CFG->wwwroot . '/lib/less/less-1.2.0.min.js" type="text/javascript"></script>';
$scripts .='<script src="' . $CFG->wwwroot . '/lib/jquery/jquery-1.7.1.min.js" type="text/javascript"></script>';
$scripts .='<script src="' . $CFG->wwwroot . '/local/rollover/scripts/js/jquery-ui-1.8.17.custom.min.js" type="text/javascript"></script>';
$scripts .='<script src="scripts/hideshow.js" type="text/javascript"></script>';
$scripts .='<script src="scripts/autoComplete.js" type="text/javascript"></script>';
$scripts .='<script src="scripts/submit.js" type="text/javascript"></script>';

echo $scripts;

$module_list = kent_get_formated_module_list();

//TODO - move this to function and pass in shortcode and embed into the form name.
//TODO - Pass in schedule.php location rather than hard code it.  Set as a global config? ... overkill?
$form = <<< HEREDOC
    <div class='rollover_item'>
            <form method='post' name='rollover_form_%1\$d' action='schedule.php'>
                <div class='rollover_crs_title'>
                    <div class='arrow'></div>
                    <h3><a href="">%3\$s</a></h3>
                    <p class='rollover_shrt_code'><span class='rollover_txt_head'>Short code: </span>%2\$s</p>
                    <p class='rollover_desc'><span class='rollover_txt_head'>Description: </span>%4\$s</p>
                </div>
                %5\$s
            </form>
         </div>
HEREDOC;

$from_form = <<< HEREDOC
<div class='rollover_crs_from'>
    <div class='arrow'></div>
    <div class='from_form'>
        <input type='text' class='rollover_crs_input' placeholder='Please enter course name..'/>
        <h4 class='rollover_advanced_title'>Advanced options</h4>
        <ul class='rollover_advanced_options'>
            $module_list
        </ul>
        <div class='more_advanced_wrap'>
            <div class='more_advanced'>
                <div class='text'>More options</div>
                <div class='arrow_border'></div>
                <div class='arrow_light'></div>
            </div>
        </div>
        <input type="hidden" name="id_from" class="id_from" value=""/>
        <input type="hidden" name="id_to" class="id_to" value="%1\$d"/>
        <button type='buttons' class='rollover_crs_submit'>Rollover</button>
    </div>
</div>
HEREDOC;

$from_processing = <<< HEREDOC
<div class='rollover_crs_from pending'>
    <div class='arrow'></div>
    <h3>Pending...</h3>
</div>
HEREDOC;

$form_error = <<< HEREDOC
<div class='rollover_crs_from error'>
    <div class='arrow'></div>
    <h3>Error</h3>
    <p>System administrators are aware of this problem <br />and will contact you soon</p>
</div>
HEREDOC;

$courses = kent_get_empty_courses();

if (!empty($courses)) {
    foreach ($courses as $course) {
        $desc = 'No description at this time.';
        if (!empty($course->summary)) {
            $desc = $course->summary;
            $desc = strip_tags($desc);
        }
        
        $coursename = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $course->fullname);
        
        switch (kent_get_current_rollover_status($course->id)) {
            case 'processing';
                $from_content = $from_processing;
                break;
            case 'complete';
                //Should not be used as the form should not show complete items
                break;
            case 'restoreerror';
            case 'backuperror';
                $from_content = $form_error;
                break;
            default:
                $from_content = $from_form;
        }

        printf($form, $course->id, $course->shortname, $coursename, $desc, $from_content);
    }
} else {
    echo "<p>" . get_string('no_courses', 'local_rollover') . "</p>";
}

echo $OUTPUT->footer();
