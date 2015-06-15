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
        FROM {shared_rollovers}
        WHERE to_dist=:to_dist AND to_env=:to_env
        GROUP BY status
SQL;
        $counts = $SHAREDB->get_records_sql($sql, array(
            'to_env' => $CFG->kent->environment,
            'to_dist' => $CFG->kent->distribution
        ));

        $total = 0;
        foreach ($counts as $k => $v) {
            $total += $v;
        }

        $complete = $counts[\local_rollover\Rollover::STATUS_COMPLETE]->cnt;
        $errored = $counts[\local_rollover\Rollover::STATUS_ERROR]->cnt;

        $queued = $counts[\local_rollover\Rollover::STATUS_WAITING_SCHEDULE]->cnt;
        $queued += $counts[\local_rollover\Rollover::STATUS_SCHEDULED]->cnt;

        $inprogress = $counts[\local_rollover\Rollover::STATUS_IN_PROGRESS]->cnt;
        $inprogress += $counts[\local_rollover\Rollover::STATUS_RESTORE_SCHEDULED]->cnt;
        $inprogress += $counts[\local_rollover\Rollover::STATUS_BACKED_UP]->cnt;

        if ($errored > 0) {
            $this->error($errored . ' failed rollovers');
        }

        if ($queued > $CFG->local_rollover_ratelimit) {
            $this->warning($queued . ' queued rollovers');
        }

        if ($inprogress > $CFG->local_rollover_ratelimit) {
            $this->warning($inprogress . ' in progress rollovers');
        }

        $this->set_perf_var('rollovers_queued', $queued);
        $this->set_perf_var('rollovers_inprogress', $inprogress);
    }
}
