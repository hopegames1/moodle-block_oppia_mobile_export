<?php 

// This file keeps track of upgrades to
// the oppia_mobile_export block

function xmldb_block_oppia_mobile_export_upgrade($oldversion) {

	global $CFG, $DB, $OUTPUT;

	$dbman = $DB->get_manager();

	if ($oldversion < 2013111402) {
		// block savepoint reached
		upgrade_block_savepoint(true, 2013111402, 'oppia_mobile_export');
	}

	if ($oldversion < 2014032100) {
	
		// Define table block_oppia_mobile_server to be created.
		$table = new xmldb_table('block_oppia_mobile_server');
	
		// Adding fields to table block_oppia_mobile_server.
		$table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
		$table->add_field('servername', XMLDB_TYPE_CHAR, '50', null, null, null, '');
		$table->add_field('url', XMLDB_TYPE_CHAR, '50', null, null, null, '');
		$table->add_field('moodleuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
		$table->add_field('username', XMLDB_TYPE_CHAR, '50', null, null, null, '');
		$table->add_field('apikey', XMLDB_TYPE_CHAR, '50', null, null, null, '');
		$table->add_field('defaultserver', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
	
		// Adding keys to table block_oppia_mobile_server.
		$table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
	
		// Conditionally launch create table for block_oppia_mobile_server.
		if (!$dbman->table_exists($table)) {
			$dbman->create_table($table);
		}
	
		// Blocks savepoint reached.
		upgrade_plugin_savepoint(true, 2014032100, 'error', 'blocks');
	}
	
	if ($oldversion < 2015021802) {
	
		// Changing type of field value on table block_oppia_mobile_config to text.
		$table = new xmldb_table('block_oppia_mobile_config');
		$field = new xmldb_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
	
		// Launch change of type for field value.
		$dbman->change_field_type($table, $field);
	
		// Blocks savepoint reached.
		upgrade_plugin_savepoint(true, 2015021802, 'error', 'blocks');
	}
	
	 
	return true;
}