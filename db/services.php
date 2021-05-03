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
 * Template library webservice definitions.
 *
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [

    'tool_s3wsrestore_restore_course' => [
        'classname'   => 'tool_s3wsrestore\external',
        'methodname'  => 'restore_course',
        'classpath'   => '',
        'description' => 'Register a backup to restore into a course.',
        'type'        => 'write',
        'capabilities'=> 'moodle/restore:restorecourse',
        'ajax'        => false,
        'loginrequired' => true,
    ],
    'tool_s3wsrestore_get_restore_status' => [
        'classname'   => 'tool_s3wsrestore\external',
        'methodname'  => 'get_restore_status',
        'description' => 'Get the status of a previously registered restore.',
        'type'        => 'read',
        'ajax'        => false,
        'loginrequired' => true,
    ],
    'tool_s3wsrestore_get_detailed_restore_status' => [
        'classname'   => 'tool_s3wsrestore\external',
        'methodname'  => 'get_detailed_restore_status',
        'description' => 'Get the detailed status of a previously registered restore.',
        'type'        => 'read',
        'ajax'        => false,
        'loginrequired' => true,
    ]
];

$services = array(
    'S3 Restore Service' => [
        'functions' => [
            'tool_s3wsrestore_get_restore_status',
            'tool_s3wsrestore_get_detailed_restore_status',
            'tool_s3wsrestore_restore_course'
        ],
        'restrictedusers' => 1, // if 1, the administrator must manually select which user can use this service.
        // (Administration > Plugins > Web services > Manage services > Authorised users)
        'enabled' => 1, // if 0, then token linked to this service won't work
        'shortname' => 's3restore' //the short name used to refer to this service from elsewhere including when fetching a token
    ]
);
