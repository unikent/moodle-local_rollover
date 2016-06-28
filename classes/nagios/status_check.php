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
        global $CFG, $SHAREDB;

        $sql = <<<SQL
        SELECT status, COUNT(*) cnt
        FROM {rollovers}
        WHERE to_dist=:to_dist AND to_env=:to_env
        GROUP BY status
SQL;
        $counts = $SHAREDB->get_records_sql($sql, array(
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution
        ));
        if (!$counts) {
            return;
        }

        $complete = 0;
        $errored = 0;
        $queued = 0;
        $inprogress = 0;
        foreach ($counts as $record) {
            switch ($record->status) {
                case \local_rollover\Rollover::STATUS_COMPLETE:
                    $complete += $record->cnt;
                break;

                case \local_rollover\Rollover::STATUS_ERROR:
                    $errored += $record->cnt;
                break;

                case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
                    $queued += $record->cnt;
                break;

                case \local_rollover\Rollover::STATUS_SCHEDULED:
                    $queued += $record->cnt;
                break;

                case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                    $inprogress += $record->cnt;
                break;

                case \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED:
                    $inprogress += $record->cnt;
                break;

                case \local_rollover\Rollover::STATUS_BACKED_UP:
                    $inprogress += $record->cnt;
                break;
            }
        }

        if ($errored > 0) {
            $this->error($errored . ' failed rollovers');
        }

        if ($queued > 2) {
            $this->warning($queued . ' queued rollovers');
        }

        if ($inprogress > 5) {
            $this->warning($inprogress . ' in progress rollovers');
        }
    }
}
