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

    return true;

}
