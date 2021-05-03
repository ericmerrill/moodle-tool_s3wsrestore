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
 * S3 web restore tool settings
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die;

use local_aws\admin_settings_aws_region;

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_s3wsrestore', get_string('pluginname', 'tool_s3wsrestore'));

    $settings->add(new admin_settings_aws_region('tool_s3wsrestore/aws_region',
        new \lang_string('settings:aws:region', 'tool_s3wsrestore'),
        '', ''));

    $settings->add(new admin_setting_configtext('tool_s3wsrestore/aws_bucket',
        new lang_string('settings:aws:bucket', 'tool_s3wsrestore'), '', ''));

    $settings->add(new admin_setting_configtext('tool_s3wsrestore/aws_keyid',
        new lang_string('settings:aws:keyid', 'tool_s3wsrestore'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('tool_s3wsrestore/aws_keysecret',
        new lang_string('settings:aws:keysecret', 'tool_s3wsrestore'), '', ''));

    $ADMIN->add('tools', $settings);
}
