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
 * Tests for the task processing.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use tool_s3wsrestore\local\data\restore_request;
use tool_s3wsrestore\task\process_request;
require_once($CFG->dirroot . '/admin/tool/s3wsrestore/tests/helper_trait.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

/**
 * Tests for the task processing.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_process_request_test extends externallib_advanced_testcase {
    use tool_s3wsrestore_helper_trait;

    protected function copy_fixture($fixturename) {
        global $CFG;

        $directory = make_temp_directory('tool_s3wsrestore_test/file_transfers');
        $directory = make_unique_writable_directory($directory);
        $fullpath = $directory . DIRECTORY_SEPARATOR . $fixturename;

        copy($CFG->dirroot . '/admin/tool/s3wsrestore/tests/fixtures/' . $fixturename, $fullpath);

        return $fullpath;
    }

    public function test_nothing() {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $mock = $this->create_mock_handler();
        $course = $this->getDataGenerator()->create_course();

        $request = new restore_request();
        $request->courseid = $course->id;
        $request->filekey = 'Anything';
        $request->create_adhoc_task();

        // First, an AWS exception.
        $mock->append($this->create_exception_result());

        ob_start();
        $this->runAdhocTasks(process_request::class, $USER->id);
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('Could not download file from AWS', $output);
        $this->assertFalse($DB->record_exists('page', ['name' => 'Restored Test Page']));
        $request = restore_request::get_for_id($request->id);
        $this->assertEquals(restore_request::STATUS_FAILED, $request->status);
        $this->assertStringContainsString('Could not download file from AWS', $request->errormessage);

        // Next try a bad backup file.
        $request->taskid = null;
        $request->create_adhoc_task();
        $path = $this->copy_fixture('invalidRestore.mbz');
        $mock->append($this->create_aws_download_result($path));

        ob_start();
        $this->runAdhocTasks(process_request::class, $USER->id);
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('Could not find moodle_backup.xml', $output);
        $this->assertFalse($DB->record_exists('page', ['name' => 'Restored Test Page']));
        $this->assertFalse(is_readable($path));
        // Reload from the DB.
        $request = restore_request::get_for_id($request->id);
        $this->assertEquals(restore_request::STATUS_FAILED, $request->status);
        $this->assertStringContainsString('Could not find moodle_backup.xml', $request->errormessage);

        // Now a good backup file.
        $request->taskid = null;
        $request->create_adhoc_task();
        $path = $this->copy_fixture('validRestore.mbz');
        $mock->append($this->create_aws_download_result($path));

        ob_start();
        $this->runAdhocTasks(process_request::class, $USER->id);
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertStringContainsString('Restore completed', $output);
        // See if a specific item was added.
        $this->assertTrue($DB->record_exists('page', ['name' => 'Restored Test Page']));
        // Reload from the DB.
        $request = restore_request::get_for_id($request->id);
        $this->assertEquals(restore_request::STATUS_COMPLETE, $request->status);
        $this->assertEqualsWithDelta(1.00, $request->progress, 0.1);

    }
}
