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
$scripts .='<script src="' . $CFG->wwwroot . '/local/rollover/lib/jquery-1.7.1.min.js" type="text/javascript"></script>';

echo $scripts;

//TODO - move this to function and pass in shortcode and embed into the form name.
//TODO - Pass in schedule.php location rather than hard code it.  Set as a global config? ... overkill?
$form = "<div class='rollover_item'>
            <form method='post' name='rollover_form_SHORTCODE' id='rollover_form_SHORTCODE' action='schedule.php'>
                <div class='rollover_crs_title'>
                    <div class='arrow'></div>
                    <h3>This is a test title</h3>
                    <p class='rollover_shrt_code'><span class='rollover_txt_head'>Short code: </span>EL310</p>
                    <p class='rollover_desc'><span class='rollover_txt_head'>Description: </span>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent fringilla sem id ligula sagittis ac aliquet eros vehicula. Sed rhoncus sapien ac dui cursus consectetur. Nunc lacinia elementum quam non aliquet. Donec venenatis, odio sed pharetra rutrum, purus dui tristique orci, eu fringilla mauris dui vitae ante</p>
                </div>
                <div class='rollover_crs_from'>
                    <div class='arrow'></div>
                    <div class='rollover_crs_search'>
                        <input type='text' class='rollover_crs_input'/>
                        <button type='buttons' class='rollover_crs_submit'>Go</button>
                    </div>
                    <h4 class='rollover_advanced_title'>Advanced options</h4>
                </div>
            </form>
         </div>";

echo $form;
echo $form;
echo $form;
echo $form;
echo $OUTPUT->footer();
