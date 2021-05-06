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
 * This is the external API for this tool.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_s3wsrestore;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use moodle_exception;
use tool_s3wsrestore\local\data\restore_request;

/**
 * This is the external API for this tool.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * Returns the params expected for get_restore_status.
     *
     * @return external_function_parameters
     */
    public static function get_restore_status_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'restoreid' => new external_value(PARAM_INT,'The id of the restore to check')
            ]
        );
    }

    /**
     * Returns the status of a restore request previously made.
     *
     * @param $restoreid
     * @return string
     * @throws \invalid_parameter_exception
     */
    public static function get_restore_status($restoreid): string {
        $params = self::validate_parameters(self::get_restore_status_parameters(), ['restoreid' => $restoreid]);

        $request = restore_request::get_for_id($params['restoreid']);

        if (empty($request)) {
            return 'notfound';
        }

        switch ($request->status) {
            case (restore_request::STATUS_NEW):
            case (restore_request::STATUS_TASK_WAITING):
                return 'pending';
            case (restore_request::STATUS_DOWNLOADING):
            case (restore_request::STATUS_EXTRACTING):
            case (restore_request::STATUS_RESTORING):
                return 'inprogress';
            case (restore_request::STATUS_ERROR):
            case (restore_request::STATUS_FAILED):
                return 'failed';
            case (restore_request::STATUS_COMPLETE):
                return 'complete';
        }

        return 'unknownstatus';
    }

    /**
     * Get the expected return structure for get_restore_status.
     *
     * @return external_value
     */
    public static function get_restore_status_returns(): external_value {
        return new external_value(PARAM_TEXT, 'The status of the backup requested');
    }

    /**
     * Returns the params expected for get_restore_status.
     *
     * @return external_function_parameters
     */
    public static function get_detailed_restore_status_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'restoreid' => new external_value(PARAM_INT,'The id of the restore to check')
            ]
        );
    }

    public static function get_detailed_restore_status($restoreid): array {
        $params = self::validate_parameters(self::get_restore_status_parameters(), ['restoreid' => $restoreid]);

        $request = restore_request::get_for_id($params['restoreid']);

        if (empty($request)) {
            return ['status' => 'notfound'];
        }

        switch ($request->status) {
            case (restore_request::STATUS_NEW):
            case (restore_request::STATUS_TASK_WAITING):
                return ['status' => 'pending'];
            case (restore_request::STATUS_DOWNLOADING):
                return ['status' => 'downloading'];
            case (restore_request::STATUS_EXTRACTING):
                return ['status' => 'extracting'];
            case (restore_request::STATUS_RESTORING):
                return ['status' => 'restoring', 'progress' => round($request->progress * 100, 1)];
            case (restore_request::STATUS_ERROR):
                $result = ['status' => 'error'];
                if (isset($request->errormessage)) {
                    $result['errormessage'] = $request->errormessage;
                }
                return $result;
            case (restore_request::STATUS_FAILED):
                $result = ['status' => 'failed'];
                if (isset($request->errormessage)) {
                    $result['errormessage'] = $request->errormessage;
                }
                return $result;
            case (restore_request::STATUS_COMPLETE):
                return ['status' => 'complete'];
        }
    }

    public static function get_detailed_restore_status_returns(): external_single_structure {
        return new external_single_structure(
            [
                'status'        => new external_value(PARAM_TEXT, 'The status of the restore'),
                'progress'      => new external_value(PARAM_FLOAT, 'Progress percentage of restore', VALUE_OPTIONAL),
                'errormessage'  => new external_value(PARAM_TEXT, 'Error message that goes with the status', VALUE_OPTIONAL)
            ]
        );
    }

    /**
     * Returns the params expected for restore_course.
     *
     * @return external_function_parameters
     */
    public static function restore_course_parameters() {
        return new external_function_parameters(
            [
                's3key'     => new external_value(PARAM_RAW,'The S3 key of the file to restore'),
                'courseid'  => new external_value(PARAM_INT,'The Moodle course id to restore into')
            ]
        );

    }

    /**
     * Register a new restore course request.
     *
     * @param $s3key
     * @param $courseid
     * @return array
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     */
    public static function restore_course($s3key, $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::restore_course_parameters(), ['s3key' => $s3key, 'courseid' => $courseid]);
        $courseid = $params['courseid'];
        $s3key = $params['s3key'];

        // TODO - Permissions check - maybe make option?

        // First check that the course exists.
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return ['status' => 'error', 'message' => 'Course not found'];
        }

        $params = [$courseid, restore_request::STATUS_RESTORING];
        if ($DB->record_exists_select(restore_request::TABLE, 'courseid = ? AND status <= ?', $params)) {
            return ['status' => 'error', 'message' => 'Restore already registered for course.'];
        }

        // Now check that the remote file exists.
        try {
            $s3 = new s3();
            if (!$s3->check_file_exists($s3key)) {
                return ['status' => 'error', 'message' => 'File not found'];
            }
        } catch (moodle_exception $e) {
            return ['status' => 'error', 'message' => 'Exception ' . $e->errorcode . ' when checking AWS'];
        }

        // Make the request.
        $request = new restore_request();

        $request->courseid = $courseid;
        $request->filekey = $s3key;

        try {
            // This will create a task, and save the request to the DB.
            $request->create_adhoc_task();
        } catch (moodle_exception $e) {
            return ['status' => 'error', 'message' => 'Unable to schedule restore'];
        }

        return ['status' => 'success', 'restoreid' => $request->id];

        // $result = [];
        // switch ($courseid % 5) {
        //     case (0):
        //         $result['status'] = 'success';
        //         $result['restoreid'] = mt_rand();
        //         break;
        //     case (1):
        //         $result['status'] = 'error';
        //         $result['message'] = 'Unable to schedule restore';
        //         break;
        //     case (2):
        //         $result['status'] = 'error';
        //         $result['message'] = 'File not found';
        //         break;
        //     case (3):
        //         $result['status'] = 'error';
        //         $result['message'] = 'No permissions to course';
        //         break;
        //     default:
        //         $result['status'] = 'error';
        //         $result['message'] = 'Course not found';
        //         break;
        // }
        //
        // return $result;
    }

    /**
     * Get the expected return structure for restore_course.
     *
     * @return external_single_structure
     */
    public static function restore_course_returns(): external_single_structure {
        return new external_single_structure(
            [
                'status'    => new external_value(PARAM_TEXT, 'The response status of the request'),
                'restoreid' => new external_value(PARAM_INT, 'The unique ID given to the restore', VALUE_OPTIONAL),
                'message'   => new external_value(PARAM_TEXT, 'Any message to go along with the result', VALUE_OPTIONAL)
            ]
        );
    }
}
