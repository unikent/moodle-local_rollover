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

namespace local_rollover\nagios;

defined('MOODLE_INTERNAL') || die();

/**
 * Event Class
 */
class status_check extends \local_nagios\base_check
{
    /**
     * Execute the check.
     */
    public function execute() {
        global $SHAREDB;

        $errored = $SHAREDB->count_records('rollovers', array(
            'status' => 3
        ));

        $queued = $SHAREDB->count_records('rollovers', array(
            'status' => 0
        ));

        $queued += $SHAREDB->count_records('rollovers', array(
            'status' => 1
        ));

        $inprogress = $SHAREDB->count_records('rollovers', array(
            'status' => 4
        ));

        $threshold = 5;

        if ($errored > 0) {
            $this->error($errored . ' failed rollovers.');
        }

        if ($queued > $threshold) {
            $this->warning($queued . ' queued rollovers.');
        }

        if ($inprogress > $threshold) {
            $this->warning($inprogress . ' in progress rollovers.');
        }
    }
}