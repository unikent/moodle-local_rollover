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
 * Local stuff for Moodle Rollover
 *
 * @package    local_rollover
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/accesslib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$currentpage = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

// Page setup.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new \moodle_url('/local/rollover/manage/index.php', array(
    'page' => $currentpage,
    'perpage' => $perpage
)));
$PAGE->set_pagelayout('admin');
$PAGE->set_title("Rollover Management");
$PAGE->navbar->add('Rollover Administration');

// Optional vars.
$q = optional_param('q', null, PARAM_TEXT);
$id = optional_param('id', null, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);

// Perform the actions here, notify later.
$notification = '';
if (!empty($id)) {
    $rollover = $SHAREDB->get_record('rollovers', array(
        'id' => $id
    ));

    if ($rollover) {
        $rollover->updated = date('Y-m-d H:i:s');

        if ($action === 'retry') {
            // Schedule a new rollover.
            $rollover->status = \local_rollover\Rollover::STATUS_WAITING_SCHEDULE;
            $notification = 'Successfully rescheduled as ' . $id;
        }

        if ($action === 'undo') {
            // Schedule a new rollover.
            $rollover->status = \local_rollover\Rollover::STATUS_DELETED;
            $notification = 'Deleted rollover ' . $id;
        }

        if ($action === 'fail') {
            // Schedule a new rollover.
            $rollover->status = \local_rollover\Rollover::STATUS_ERROR;
            $notification = 'Forced failure of ' . $rollover->id;
        }

        $SHAREDB->update_record('rollovers', $rollover);
    }
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading('Rollover Administration');

if (!empty($notification)) {
    echo $OUTPUT->notification($notification, 'notifysuccess');
    echo \html_writer::empty_tag('br');
}

$form = new \local_rollover\form\search();
$form->display();

// Build a table.
$table = new html_table();
$table->head  = array(
    "ID",
    "From",
    "To",
    "Requested",
    "Last Updated",
    "Status",
    "Action"
);
$table->attributes['class'] = 'admintable generaltable';
$table->data = array();

$sql = <<<SQL
    SELECT
        sr.id, sr.status, sr.from_dist, sr.from_course, sr.to_dist, sr.to_course, sr.created, sr.updated,
        sc.shortname as from_shortname, sc2.shortname as to_shortname,
        sc.fullname as from_fullname, sc2.fullname as to_fullname
    FROM {rollovers} sr
    LEFT OUTER JOIN {courses} sc
        ON sc.moodle_id=sr.from_course
        AND sc.moodle_env=sr.from_env
        AND sc.moodle_dist=sr.from_dist
    LEFT OUTER JOIN {courses} sc2
        ON sc2.moodle_id=sr.to_course
        AND sc2.moodle_env=sr.to_env
        AND sc2.moodle_dist=sr.to_dist
    WHERE sr.to_env = :env AND sr.to_dist = :dist
SQL;

$params = array(
    'env' => $CFG->kent->environment,
    'dist' => $CFG->kent->distribution
);

// Are we searching?
if (!empty($q)) {
    $sql .= <<<SQL
    AND (sr.id = :q OR sc.shortname LIKE :q2 OR sc2.shortname LIKE :q3)
SQL;

    $params['q'] = $q;
    $params['q2'] = "%{$q}%";
    $params['q3'] = "%{$q}%";
}

$rollovers = $SHAREDB->get_recordset_sql($sql, $params, $currentpage * $perpage, $perpage);

foreach ($rollovers as $rollover) {
    $action = new html_table_cell('-');

    $status = '';
    switch ($rollover->status) {
        case \local_rollover\Rollover::STATUS_WAITING_SCHEDULE:
            $status = 'Awaiting Scheduling';
        break;

        case \local_rollover\Rollover::STATUS_SCHEDULED:
        case \local_rollover\Rollover::STATUS_RESTORE_SCHEDULED:
            $status = 'Scheduled';
        break;

        case \local_rollover\Rollover::STATUS_BACKED_UP:
            $status = 'Backed Up';
        break;

        case \local_rollover\Rollover::STATUS_COMPLETE:
            $status = 'Complete';

            $url = new moodle_url($PAGE->url, array(
                'action' => 'undo',
                'id' => $rollover->id
            ));

            $action->text = $OUTPUT->single_button($url, 'Undo');
        break;

        case \local_rollover\Rollover::STATUS_ERROR:
            $status = 'Error';

            $url = new moodle_url($PAGE->url, array(
                'action' => 'retry',
                'id' => $rollover->id
            ));

            $action->text = $OUTPUT->single_button($url, 'Retry');
        break;

        case \local_rollover\Rollover::STATUS_IN_PROGRESS:
            $status = 'In Progress';

            $url = new moodle_url($PAGE->url, array(
                'action' => 'fail',
                'id' => $rollover->id
            ));

            $action->text = $OUTPUT->single_button($url, 'Stop');
        break;

        case \local_rollover\Rollover::STATUS_DELETED:
            $status = 'Deleted';
        break;
    }

    $from = html_writer::tag('a', "{$rollover->from_shortname}: {$rollover->from_fullname}", array(
        'href' => $CFG->kent->httppaths[$rollover->from_dist] . "course/view.php?id=" . $rollover->from_course
    ));

    $to = html_writer::tag('a', "{$rollover->to_shortname}: {$rollover->to_fullname}", array(
        'href' => $CFG->kent->httppaths[$rollover->to_dist] . "course/view.php?id=" . $rollover->to_course
    ));

    $row = new html_table_row(array(
        new html_table_cell($rollover->id),
        new html_table_cell($from),
        new html_table_cell($to),
        new html_table_cell($rollover->created),
        new html_table_cell($rollover->updated),
        new html_table_cell($status),
        $action
    ));
    $table->data[] = $row;
}

$rollovers->close();

echo html_writer::table($table);

$total = $SHAREDB->count_records_sql("SELECT COUNT(*) FROM ({$sql}) tmp", $params);

echo $OUTPUT->paging_bar($total, $currentpage, $perpage, $PAGE->url);


echo $OUTPUT->footer();
