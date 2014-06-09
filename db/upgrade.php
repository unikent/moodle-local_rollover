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

        // Define field status to be added to rollover_events.
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'restore_target');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index i_status (not unique) to be added to rollover_events.
        $index = new xmldb_index('i_status', XMLDB_INDEX_NOTUNIQUE, array('status'));

        // Conditionally launch add index i_status.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Also change all the statuses.
        $DB->set_field('rollover_events', 'status', '1');

        upgrade_plugin_savepoint(true, 2014060900, 'local', 'rollover');
    }

    return true;

}
