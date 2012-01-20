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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');

global $USER;

$systemcontext = get_context_instance(CONTEXT_SYSTEM);
require_login();
require_capability('moodle/course:update', $systemcontext);

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_url('/local/rollover/index.php');
$PAGE->set_pagelayout('admin'); 
$PAGE->navbar->add(get_string('pluginname', 'local_rollover'));

$PAGE->set_title(get_string('pluginname', 'local_rollover'));
$PAGE->set_heading(get_string('pluginname', 'local_rollover'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_rollover'));

$scripts ='<link rel="stylesheet/less" type"text/css" href="styles.less">';
$scripts .='<script src="' . $CFG->wwwroot . '/lib/less/less-1.2.0.min.js" type="text/javascript"></script>';
$scripts .='<script src="' . $CFG->wwwroot . '/lib/jquery/jquery-1.7.1.min.js" type="text/javascript"></script>';
$scripts .='<script src="scripts/hideshow.js" type="text/javascript"></script>';

echo $scripts;

$module_list = kent_get_formated_module_list();

//TODO - move this to function and pass in shortcode and embed into the form name.
//TODO - Pass in schedule.php location rather than hard code it.  Set as a global config? ... overkill?
$form = <<< HEREDOC
    <div class='rollover_item'>
            <form method='post' name='rollover_form_%1\$d' action='schedule.php'>
                <div class='rollover_crs_title'>
                    <div class='arrow'></div>
                    <h3>%3\$s</h3>
                    <p class='rollover_shrt_code'><span class='rollover_txt_head'>Short code: </span>%2\$s</p>
                    <p class='rollover_desc'><span class='rollover_txt_head'>Description: </span>%4\$s</p>
                </div>
                <div class='rollover_crs_from'>
                    <div class='arrow'></div>
                    <input type='text' class='rollover_crs_input'/>
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
                    <input type="hidden" name="id_from" value=""/>
                    <input type="hidden" name="id_to" value="%1\$d"/>
                    <button type='buttons' class='rollover_crs_submit'>Rollover!</button>
                </div>
            </form>
         </div>
HEREDOC;

$courses = kent_get_empty_courses();
if(!empty($courses)){
    foreach($courses as $course){
        $desc = 'No description at this time.';
        if (!empty($course->summary)) {
            $desc = $course->summary;
            $desc = strip_tags($desc);
        }
        
        printf($form, $course->id, $course->shortname, $course->fullname, $desc);
    }
} else {
    echo 'There are no empty courses';
}

echo $OUTPUT->footer();
