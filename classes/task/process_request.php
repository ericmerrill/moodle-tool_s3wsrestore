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
 * This is the AWS S3 connector.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_s3wsrestore\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');

use backup;
use base_setting;
use core\progress\db_updater;
use core\task\adhoc_task;
use core\task\logging_trait;
use Exception;
use restore_controller;
use tool_s3wsrestore\local\data\restore_request;
use tool_s3wsrestore\s3;

/**
 * This is the AWS S3 connector.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_request extends adhoc_task {
    use logging_trait;

    public function execute() {
        global $DB, $USER;

        $requestid = $this->get_custom_data()->requestid;

        $this->log('Starting processing of restore request id: ' . $requestid);

        $request = restore_request::get_for_id($requestid);

        if (empty($request)) {
            $this->log('Error: Could not find request');
            return;
        }

        // Now we are going to download the file into a temp directory.
        $request->set_status(restore_request::STATUS_DOWNLOADING);

        $directory = make_temp_directory('tool_s3wsrestore' . DIRECTORY_SEPARATOR . 'file_transfers');
        $directory = make_unique_writable_directory($directory);

        $this->log("Downloading S3 file {$request->filekey} into $directory");

        $s3 = new s3();
        try {
            $fullpath = $s3->download_file($request->filekey, $directory);
        } catch (Exception $e) {
            // Something went wrong, mark things up.
            // TODO - re-enrol/counter on error.
            $request->set_failure('Could not download file from AWS: ' . $e->getMessage());

            $this->log('Error: Could not download file from AWS: ' . $e->getMessage());
            $this->log($e->getTraceAsString());

            return;
        }

        $request->set_status(restore_request::STATUS_EXTRACTING);

        $tempdir = 'tool_s3wsrestore_' . time() . '_' . random_string(4);
        $backuptempdir = make_backup_temp_directory($tempdir);

        $this->log("Unpacking file into {$backuptempdir}");

        try {
            $packer = get_file_packer('application/vnd.moodle.backup');
            $packer->extract_to_pathname($fullpath, $backuptempdir);
        } catch (Exception $e) {
            // Something went wrong, mark things up.
            // TODO - re-enrol/counter on error.
            $request->set_failure('Could not unpack file: ' . $e->getMessage());

            $this->log('Could not unpack file: ' . $e->getMessage());
            $this->log($e->getTraceAsString());

            // Cleanup.
            fulldelete(dirname($fullpath));
            fulldelete($backuptempdir);
            return;
        }

        // Make sure that the extracted file seems to have been a backup.
        if (!is_readable($backuptempdir . DIRECTORY_SEPARATOR . 'moodle_backup.xml')) {
            $request->set_failure("Could not find moodle_backup.xml in {$backuptempdir}");

            $this->log("Could not find moodle_backup.xml in {$backuptempdir}");

            // Cleanup.
            fulldelete(dirname($fullpath));
            fulldelete($backuptempdir);
            return;
        }

        $request->set_status(restore_request::STATUS_RESTORING, false);
        $request->progress = 0.0;
        $request->save_to_db();

        $course = $DB->get_record('course', ['id' => $request->courseid]);

        $settings = [
            'overwrite_conf' => false,
            'users' => false,
            'keep_roles_and_enrolments' => true,
            'keep_groups_and_groupings' => true
        ];

        // This will update the progress value in the DB as the restore processes, showing approx percent complete.
        $progress = new db_updater($request->id, restore_request::TABLE, 'progress');

        try {
            $rc = new restore_controller($tempdir, $course->id, backup::INTERACTIVE_NO,
                backup::MODE_GENERAL, $USER->id, backup::TARGET_CURRENT_ADDING, $progress);

            $this->log('Restore ID is ' . $rc->get_restoreid());
            $request->restoreid = $rc->get_restoreid();

            // Apply settings to the plan.
            foreach ($settings as $settingname => $value) {
                $setting = $rc->get_plan()->get_setting($settingname);
                if ($setting->get_status() == base_setting::NOT_LOCKED) {
                    $rc->get_plan()->get_setting($settingname)->set_value($value);
                }
            }

            // Do the prechecks, and log the results.
            $rc->execute_precheck();
            $results = $rc->get_precheck_results();
            if (!empty($results)) {
                $this->log('The restore pre-checker reported these messages:');
                foreach ($results as $type => $messages) {
                    foreach ($messages as $message) {
                        $this->log($type . ': '. $message, 2);
                    }
                }
            }

            $rc->execute_plan();

            // Report results of the restore.
            $results = $rc->get_results();
            if (!empty($results)) {
                $this->log('The restore reported these results:');
                $this->log(var_export($results, true));
            }

            $rc->destroy();
        } catch (Exception $e) {
            // Cleanup the directory and original file.
            fulldelete($backuptempdir);

            $request = restore_request::get_for_id($requestid);
            $request->set_restore_failure("Restore failed\n" . $e->getMessage() . $e->getTraceAsString());

            $this->log_finish("Restore failed!");
            $this->log($e->getMessage());

            // Note that the finally statement will still run, even though we are returning here.
            return;
        } finally {
            // Delete the downloaded file.
            fulldelete(dirname($fullpath));
        }

        $this->log_finish('Restore completed');

        $request = restore_request::get_for_id($requestid);
        $request->set_status(restore_request::STATUS_COMPLETE);

    }

}
