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

namespace local_rollover\util;

defined('MOODLE_INTERNAL') || die();

/**
 * CLI Helpers
 */
class cli
{
    /**
     * Convert stdIn to an array of variables.
     * Used by connect.
     */
    public static function std_in_to_array() {
        $stdin = file('php://stdin');
        $prefs = array();

        if ($stdin === false) {
            return $prefs;
        }

        foreach ($stdin as $line) {
            $line = explode("=", $line);
            if (count($line) !== 2) {
                continue;
            }

            list($name, $val) = array_map('trim', $line);
            if (strlen($name) > 0) {
                $prefs[$name] = $val;
            }
        }

        return $prefs;
    }
}