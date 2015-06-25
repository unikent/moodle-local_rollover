<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_rollover_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2012011815) {
        upgrade_plugin_savepoint(true, 2012011815, 'local', 'rollover');
    }

    if ($oldversion < 2012053001) {
        $table = new xmldb_table('rollover_events');
        $field = new xmldb_field('backup_source', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'archive');
        $dbman->add_field($table, $field);

        $field = new xmldb_field('restore_target', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'live');
        $dbman->add_field($table, $field);

        upgrade_plugin_savepoint(true, 2012053001, 'local', 'rollover');
    }

    if ($oldversion < 2014060900) {
        $table = new xmldb_table('rollover_events');

        // Conditionally launch drop table for rollover_events.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2014060900, 'local', 'rollover');
    }

    if ($oldversion < 2015061500 && $CFG->kent->distribution == '2015') {
        // Fix all section counts.
        $courses = $DB->get_records('course');
        foreach ($courses as $course) {
            // Is there a rollover?
            $kr = new \local_rollover\Course($course);
            if ($kr->get_status() !== \local_rollover\Rollover::STATUS_NONE) {
                // Count the number of sections.
                $sectioncount = $DB->count_records('course_sections', array(
                    'course' => $course->id
                ));

                // Current setting.
                $current = $DB->get_record('course_format_options', array(
                    'courseid' => $course->id,
                    'name' => 'numsections'
                ));

                // update value.
                $current->value = $sectioncount;
                $DB->update_record('course_format_options', $current);
            }
        }

        upgrade_plugin_savepoint(true, 2015061500, 'local', 'rollover');
    }

    if ($oldversion < 2015062500) {
        // Upgrade previous notifications.
        $courses = $DB->get_records('course');
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);

            $kr = new \local_rollover\Course($course);
            if ($kr->get_status() !== \local_rollover\Rollover::STATUS_NONE) {
                $record = $kr->get_rollover();

                $kc = new \local_kent\Course($course->id);

                // Generate a status notification.
                \local_rollover\notification\status::create(array(
                    'objectid' => $course->id,
                    'context' => $context,
                    'other' => array(
                        'complete' => true,
                        'rolloverid' => $record->id,
                        'record' => $record,
                        'manual' => $kc->is_manual()
                    )
                ));
            }
        }

        upgrade_plugin_savepoint(true, 2015062500, 'local', 'rollover');
    }

    return true;

}
