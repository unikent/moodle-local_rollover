<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_rollover_upgrade($oldversion) {

  if( $oldversion < 2012011815 ) {
    upgrade_plugin_savepoint(true, 2012011805, 'local', 'rollover');
  }

  return true;

}
