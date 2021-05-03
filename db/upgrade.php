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
 * Upgrade for S3 Restore.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade for S3 Restore.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_tool_s3wsrestore_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021050100) {

        // Define table tool_s3wsrestore_requests to be created.
        $table = new xmldb_table('tool_s3wsrestore_requests');

        // Adding fields to table tool_s3wsrestore_requests.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filekey', XMLDB_TYPE_CHAR, '1024', null, XMLDB_NOTNULL, null, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('additionaldata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_s3wsrestore_requests.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('taskid', XMLDB_KEY_FOREIGN_UNIQUE, ['taskid'], 'task_adhoc', ['id']);

        // Conditionally launch create table for tool_s3wsrestore_requests.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // S3wsrestore savepoint reached.
        upgrade_plugin_savepoint(true, 2021050100, 'tool', 's3wsrestore');
    }

    if ($oldversion < 2021050201) {

        // Define field progress to be added to tool_s3wsrestore_requests.
        $table = new xmldb_table('tool_s3wsrestore_requests');
        $field = new xmldb_field('progress', XMLDB_TYPE_NUMBER, '15, 14', null, null, null, null, 'additionaldata');

        // Conditionally launch add field progress.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // S3wsrestore savepoint reached.
        upgrade_plugin_savepoint(true, 2021050201, 'tool', 's3wsrestore');
    }

    if ($oldversion < 2021050203) {

        // Rename field additionaldata on table tool_s3wsrestore_requests to additional.
        $table = new xmldb_table('tool_s3wsrestore_requests');
        $field = new xmldb_field('additionaldata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'taskid');

        // Launch rename field additionaldata.
        $dbman->rename_field($table, $field, 'additional');

        // S3wsrestore savepoint reached.
        upgrade_plugin_savepoint(true, 2021050203, 'tool', 's3wsrestore');
    }

    return true;
}
