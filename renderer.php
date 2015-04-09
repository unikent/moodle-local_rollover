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
 * Rollover.
 *
 * @package    local_rollover
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Rollover Renderer.
 *
 * @copyright  2015 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_rollover_renderer extends plugin_renderer_base
{
	/**
	 * Renders a module item.
	 */
	public function module_option($shortname, $longname) {
		$shortname = strtolower($shortname);
		$longname = ucfirst($longname);

	    return <<<HTML5
	    	<li class="rollover_option_item">
	    		<input class='rollover_checkbox' name='backup_{$shortname}' type='checkbox' checked />{$longname}
	    	</li>
HTML5;
	}

	/**
	 * Renders the search box.
	 */
	public function search_box($search = '') {
		global $CFG;

		return <<<HTML5
		    <div id="rollover_search" class="bootstrap">
		        <form action="{$CFG->wwwroot}/local/rollover/index.php" method="GET">
		            <div class="input-group">
		                <input type="text" name="q" value="{$search}" placeholder="Search..." class="form-control" />
		                <span class="input-group-btn">
		                    <button class="btn btn-default" type="button"><i class="fa fa-search"></i></button>
		                </span>
		            </div>
		        </form>
		    </div>
HTML5;
	}

	/**
	 * Renders the help text.
	 */
	public function help() {
		return <<<HTML5
		    <p>To rollover content from a previous module to a module listed below, please select a module to rollover from and click the rollover button.</p>
HTML5;
	}

	/**
	 * Renders the dialogs.
	 */
	public function dialogs() {
		return <<<HTML5
		    <div id="dialog_sure">Are you sure you want to rollover this module?</div>
		    <div id="dialog_id_from_error">Please select a valid existing module to rollover from.</div>
		    <div id="dialog_id_to_error">No destination module set. Please refresh this page and try again. If this error persists, please contact an administrator.</div>
		    <div id="dialog_autocomplete_error">Could not retrieve autocomplete data.</div>
HTML5;
	}

	/**
	 * Render a processing form.
	 */
	public function processing_form() {
		return <<<HTML5
			<td class="rollover_crs_from processing">
				<div class="arrow"></div>
				<h3>Scheduled for rollover</h3>
				<p>Your request will be completed in the next 24 hours.</p>
			</td>
HTML5;
	}

	/**
	 * Render a requested form.
	 */
	public function requested_form() {
		return <<<HTML5
			<td class="rollover_crs_from pending">
				<div class="arrow"></div>
				<h3>Successfully scheduled</h3>
				<p>Your request will be completed in the next 24 hours.</p>
			</td>
HTML5;
	}

	/**
	 * Render a error form.
	 */
	public function error_form($rolloverid) {
		global $OUTPUT;

        $button = $OUTPUT->single_button(new moodle_url('/local/rollover/', array(
            'id' => $rolloverid,
            'action' => 'undo'
        )), 'Click here to undo the rollover.');

		return <<<HTML5
		    <td class="rollover_crs_from success">
		        <div class="arrow"></div>
		        <h3>Completed</h3>
		        <p>Your rollover request has been completed but the module appears to be empty.</p>
		        <br />
		        $button
		        <p>WARNING: this may result in the deletion of content from the module!</p>
		    </td>
HTML5;
	}

	/**
	 * Render a rollover form.
	 */
	public function rollover_form($moduleoptions, $shortname, $id, $src) {
		global $OUTPUT;

		$help = $OUTPUT->help_icon('advanced_opt_help', 'local_rollover');

		return <<<HTML5
			<td class='rollover_crs_from'>
			    <div class='arrow'></div>
			    <div class='from_form'>
			        <input type='text' class='rollover_crs_input' placeholder='{$shortname}' />
			        <ul class='rollover_advanced_options'>
			            {$moduleoptions}
			        </ul>
			        <div class='more_advanced_wrap'>
			            <div class='more_advanced'>
			                <div class='text'>Show advanced options</div>
			                {$shortname}{$help}
			                <div class="clearfix"></div>
			                <div class='arrow_border'></div>
			                <div class='arrow_light'></div>
			            </div>
			        </div>
			        <input type="hidden" name="id_from" class="id_from" value=""/>
			        <input type="hidden" name="src_from" class="src_from" value=""/>
			        <input type="hidden" name="id_to" class="id_to" value="{$id}"/>
			        <input type="hidden" name="src_to" class="src_to" value="{$src}"/>
			        <button type='buttons' class='rollover_crs_submit'>Rollover</button>
			    </div>
			</td>
HTML5;
	}
}
