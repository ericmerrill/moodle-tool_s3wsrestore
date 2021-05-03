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
 * A helper trait for testing.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A helper trait for testing.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait tool_s3wsrestore_helper_trait {
    protected function create_mock_handler(): \Aws\MockHandler {
        set_config('aws_region', 'us-east-1', 'tool_s3wsrestore');
        set_config('aws_bucket', 'bucket-test', 'tool_s3wsrestore');
        set_config('aws_keyid', 'key-id', 'tool_s3wsrestore');
        set_config('aws_keysecret', 'key-secret', 'tool_s3wsrestore');

        $mock = new \Aws\MockHandler();
        \tool_s3wsrestore\s3::set_test_s3_handler($mock);

        return $mock;
    }

    protected function create_exception_result(): \Aws\S3\Exception\S3Exception {
        return new \Aws\S3\Exception\S3Exception('someError', new \Aws\Command('someCommand'));
    }

    protected function create_aws_result(): \Aws\Result {
        $day = new \DateTime();

        $result = new \Aws\Result([
            'CommonPrefixes' => [['Prefix' => '2020_dir']],
            'Contents' => [['Key' => '2020_f.jpg', 'Size' => 15, 'StorageClass' => 'STANDARD', 'LastModified' => $day]]]);

        return $result;
    }

    protected function create_aws_download_result(string $filepath): \Aws\Result {
        $result = new \Aws\Result([
            'mocktest' => $filepath]);

        return $result;
    }
}
