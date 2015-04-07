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

	    $output = '<li class="rollover_option_item">';
	    $output .= "<input class='rollover_checkbox' name='backup_{$shortname}' type='checkbox' checked />{$longname}";
	    $output .= '</li>';

	    return $output;
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
		                <input type="text" name="srch" value="{$search}" placeholder="Search..." class="form-control" />
		                <span class="input-group-btn">
		                    <button class="btn btn-default" type="button"><i class="fa fa-search"></i></button>
		                </span>
		            </div>
		        </form>
		    </div>
HTML5;
	}

	/**
	 * Renders the dialogs.
	 */
	public function dialogs() {
	    $output = '<div id="dialog_sure">' . get_string('are_you_sure_text', 'local_rollover') . '</div>';
	    $output .= '<div id="dialog_id_from_error">' . get_string('rollover_from_error_text', 'local_rollover') . '</div>';
	    $output .= '<div id="dialog_id_to_error">' . get_string('rollover_to_error_text', 'local_rollover') . '</div>';
	    $output .= '<div id="dialog_autocomplete_error">' . get_string('rollover_autocomplete_error', 'local_rollover') . '</div>';

	    return $output;
	}
}
