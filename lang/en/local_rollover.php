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

$string['pluginname'] = 'Kent rollover';
$string['no_permissions'] = 'Sorry, but you do not currently have permissions to access this page';
$string['no_courses'] = 'There are currently no courses which you have access to which require content rollover.  Please contact an administrator if you require any assistance.';
$string['pendingmessage'] = '<h3>Pending...</h3>';
$string['errormessage'] = '<h3>Error!</h3><p>System administrators are aware of this problem <br />and will contact you soon</p>';
$string['are_you_sure_text'] = 'Are you sure you want to rollover this course?';

$string['rollover_from_error_text'] = 'Please select a valid existing course to rollover from.';
$string['rollover_to_error_text'] = 'No destination course set.  Please refresh this page and try again.  If this error persists, please contact an administrator.'; //Should never see this, but just incase.

//Help text
$string['top_page_help'] = '<p>To rollover content from a previous course to a course listed below, please select a course to rollover from and click the rollover button.</p>';
$string['advanced_opt_help'] = 'Advanced rollover options';
$string['advanced_opt_help_help'] = 'By default, all course module types are rolled over.  Uncheck any modules you do not wish to have as part of your rollover.';