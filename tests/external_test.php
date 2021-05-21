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
 * Tests for the external API.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use tool_s3wsrestore\external;
use tool_s3wsrestore\local\data\restore_request;
require_once($CFG->dirroot . '/admin/tool/s3wsrestore/tests/helper_trait.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

/**
 * Tests for the external API.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_testcase extends externallib_advanced_testcase {
    use tool_s3wsrestore_helper_trait;

    public function test_restore_course() {
        global $DB;

        $this->resetAfterTest();

        $mock = $this->create_mock_handler();
        $course = $this->getDataGenerator()->create_course();

        // First try a course that doesn't exist.
        $result = external::restore_course('key', ($course->id + 100));
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Course not found', $result['message']);

        // Now try the right course, but with a non-existent file.
        // Add an exception to the handler, which represents a missing file.
        $mock->append($this->create_exception_result());
        $result = external::restore_course('key', $course->id);
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('File not found', $result['message']);

        // Now with a correctly found file.
        $mock->append($this->create_aws_result());
        $result = external::restore_course('key', $course->id);
        $this->assertEquals('success', $result['status']);
        $this->assertIsInt($result['restoreid']);

        $request = restore_request::get_for_id($result['restoreid']);
        $this->assertInstanceOf(restore_request::class, $request);
        $this->assertEquals('key', $request->filekey);
        $this->assertEquals($course->id, $request->courseid);

        // Now check that the task was created.
        $this->assertTrue($DB->record_exists('task_adhoc', ['id' => $request->taskid]));

    }

    public function test_get_restore_status() {
        global $DB;

        $this->resetAfterTest();

        $request = new restore_request();
        $request->courseid = 12345;
        $request->filekey = 'Anything';
        $request->save_to_db();

        $result = external::get_restore_status($request->id + 100);
        $this->assertEquals('notfound', $result);

        $result = external::get_restore_status($request->id);
        $this->assertEquals('pending', $result);

        $request->set_status(restore_request::STATUS_TASK_WAITING);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('pending', $result);

        $request->set_status(restore_request::STATUS_DOWNLOADING);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('inprogress', $result);

        $request->set_status(restore_request::STATUS_EXTRACTING);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('inprogress', $result);

        $request->set_status(restore_request::STATUS_RESTORING);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('inprogress', $result);

        $request->set_status(restore_request::STATUS_COMPLETE);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('complete', $result);

        $request->set_status(restore_request::STATUS_FAILED);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('failed', $result);

        $request->set_status(restore_request::STATUS_RESTORE_FAILED);
        $result = external::get_restore_status($request->id);
        $this->assertEquals('partiallyrestored', $result);
    }

}
