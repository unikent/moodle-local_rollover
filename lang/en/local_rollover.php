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
 * English strings for newmodule
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod
 * @subpackage newmodule
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Kent Rollover';
$string['no_permissions'] = 'Sorry, but you do not currently have permissions to access this page';

// @deprecated
$string['no_courses'] = 'You currently do not have access to any modules requiring content rollover. Please contact an administrator if you require any assistance.';
$string['processingmessage'] = '<h3>Scheduled for rollover</h3><p>Your request will be completed in the next 24 hours.</p>';
$string['requestedmessage'] = '<h3>Successfully scheduled</h3><p>Your request will be completed in the next 24 hours.</p>';
$string['errormessage'] = '<h3>Error!</h3><p>Please contact your FLT if this problem still<br/>remains after an hour.</p>';
$string['rollover_button_text'] = 'Rollover';
$string['top_page_help'] = '<p>To rollover content from a previous module to a module listed below, please select a module to rollover from and click the rollover button.</p>';
$string['are_you_sure_text'] = 'Are you sure you want to rollover this module?';
$string['rollover_from_error_text'] = 'Please select a valid existing module to rollover from.';
$string['rollover_to_error_text'] = 'No destination module set. Please refresh this page and try again. If this error persists, please contact an administrator.';
//$string['rollover_autocomplete_error'] = 'Could not retrieve autocomplete data.  Please refresh this page and try again.  If this error persists, please contact an administrator.';
$string['rollover_autocomplete_error'] = 'Could not retrieve autocomplete data. <br/><br/>Please note that you need to be also logged into the current <a target="_blank" href="https://moodle.kent.ac.uk/moodle">Moodle website</a> to carry out rollovers and avoid this error.  This is a temporary measure until student exams are finished and so not to cause interruption to that service.<br/><br/>We apologise for any temporary inconvenience caused.';
$string['short_code_label_text'] = 'Short code';
$string['description_label_text'] = 'Description';
$string['no_course_description_text'] = 'No description at this time.';


$string['search_placeholder'] = 'Please enter a module code..';
$string['advanced_options_label'] = 'Advanced options';

//Help text
$string['advanced_opt_help'] = 'Advanced rollover options';
$string['advanced_opt_help_help'] = 'By default, all types of module content are rolled over. Uncheck any content you do not wish to have as part of your rollover.';
