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
 * A class that represents a restore request.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_s3wsrestore\local\data;

defined('MOODLE_INTERNAL') || die();

use core\task\manager;
use moodle_exception;
use tool_s3wsrestore\task\process_request;

/**
 * A class that represents a restore request.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_request extends base {

    public const STATUS_NEW = 0;

    public const STATUS_TASK_WAITING = 1;

    public const STATUS_DOWNLOADING = 2;

    public const STATUS_EXTRACTING = 3;

    public const STATUS_RESTORING = 4;

    public const STATUS_COMPLETE = 5;

    public const STATUS_FAILED = 6;

    public const STATUS_RESTORE_FAILED = 7;

    /** @var array Array of keys that go in the database object */
    protected $dbkeys = ['id', 'status', 'courseid', 'filekey', 'taskid', 'additional', 'progress', 'timecreated', 'timemodified'];

    /** @var array An array of default property->value pairs */
    protected $defaults = ['status' => self::STATUS_NEW];

    /** @var array Array of keys will be used to see if two objects are the same. */
    protected $diffkeys = ['status', 'courseid', 'filekey', 'taskid', 'additional'];

    /**
     * The table name of this object.
     */
    const TABLE = 'tool_s3wsrestore_requests';

    /**
     * Create an adhoc task for this request.
     *
     * @throws moodle_exception
     */
    public function create_adhoc_task(): void {
        if ($this->taskid) {
            // TODO - Maybe do more to make sure it is still existant.
            return;
        }

        if (!$this->id) {
            // Need to save to the DB before this can happen.
            $this->save_to_db();
        }

        $task = new process_request();
        $task->set_custom_data([
            'requestid' => $this->id
        ]);

        // TODO - decide if the restore should be done as admin, or the calling user.
        $adminuser = get_admin();
        $task->set_userid($adminuser->id);

        $taskid = manager::queue_adhoc_task($task);

        if ($taskid === false) {
            $this->status = static::STATUS_FAILED;
            $this->errormessage = 'Could not create task';
            $this->save_to_db();

            throw new moodle_exception('taskcreationfailed', 'tool_s3wsrestore');
        }

        $this->taskid = $taskid;
        $this->status = static::STATUS_TASK_WAITING;
        $this->save_to_db();
    }

    /**
     * Set an error state and message for this request.
     *
     * @param string $message
     * @param bool $save
     */
    public function set_failure(string $message, bool $save = true): void {
        // TODO - re-enrol and/or count.
        $this->errormessage = $message;
        $this->set_status(static::STATUS_FAILED, $save);
    }

    /**
     * Set a failure state and message for this request.
     *
     * @param string $message
     * @param bool $save
     */
    public function set_restore_failure(string $message, bool $save = true): void {
        $this->errormessage = $message;
        $this->set_status(static::STATUS_RESTORE_FAILED, $save);
    }

    /**
     * Set a status for this request.
     * @param $status
     * @param bool $save
     */
    public function set_status($status, $save = true): void {
        $this->status = $status;
        if ($save) {
            $this->save_to_db();
        }
    }
}
