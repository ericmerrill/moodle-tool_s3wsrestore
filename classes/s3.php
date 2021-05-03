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
 * This is the AWS S3 connector, as a wrapper for S3Client.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_s3wsrestore;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\MockHandler;
use Aws\S3\S3Client;
use moodle_exception;

/**
 * This is the AWS S3 connector, as a wrapper for S3Client.
 *
 * @package    tool_s3wsrestore
 * @copyright  2021 Moonami
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class s3 {

    protected $s3client = null;

    protected $config = null;

    protected static $testhandler = null;

    /**
     * Set the testing S3 handler to use when unit testing.
     *
     * @param MockHandler $handler
     */
    public static function set_test_s3_handler(?MockHandler $handler) {
        static::$testhandler = $handler;
    }

    /**
     * Checks to see if a given filekey exists in AWS.
     *
     * @param $filekey
     * @return bool
     * @throws moodle_exception
     */
    public function check_file_exists($filekey): bool {
        try {
            $s3 = $this->get_s3_client();
            $result = $s3->doesObjectExist($this->get_config('aws_bucket'), $filekey);
        } catch (AwsException $e) {
            throw new moodle_exception('errorcommunicatingwithaws', 'tool_s3wsrestore', '', null, $e->getAwsErrorMessage());
        }

        return $result;
    }

    /**
     * Download a AWS filekey into the provided directory.
     *
     * @param $filekey
     * @param $directory
     * @return string       The full path of the resulting file. You should use this return to locate the file.
     * @throws moodle_exception
     */
    public function download_file($filekey, $directory): string {
        $config = $this->get_config();

        $fullpath = sprintf('%s/%s', $directory, clean_filename($filekey));

        $s3 = $this->get_s3_client();
        $options = [
            'Bucket' => $config->aws_bucket,
            'Key' => $filekey,
            'SaveAs' => $fullpath
        ];

        try {
            $result = $s3->execute($s3->getCommand('GetObject', $options));
        } catch (AwsException $e) {
            throw new moodle_exception('errorcommunicatingwithaws', 'tool_s3wsrestore', '', null, $e->getAwsErrorMessage());
        }

        // Now do some error checking.
        if (!is_a($result, Result::class)) {
            throw new moodle_exception('awsresultnotreturned', 'tool_s3wsrestore');
        }
        if (empty($result->get('@metadata')['statusCode']) || $result->get('@metadata')['statusCode'] !== 200) {
            throw new moodle_exception('invalidstatuscode', 'tool_s3wsrestore');
        }
        if (!empty($result->get('mocktest'))) {
            // This means we are in a test, we didn't really download a file. Use the provided path.
            $fullpath = $result->get('mocktest');
        }
        if (!is_readable($fullpath)) {
            throw new moodle_exception('filenotreabable', 'tool_s3wsrestore');
        }

        return $fullpath;
    }

    /**
     * Get the true S3Client object
     * @return S3Client|null
     * @throws moodle_exception
     */
    private function get_s3_client(): ?S3Client {
        if ($this->s3client == null) {
            $config = $this->get_config();

            if (empty($config->aws_region) || empty($config->aws_bucket) || empty($config->aws_keyid)
                    || empty($config->aws_keysecret)) {
                throw new moodle_exception('awsnotconfigured', 'tool_s3wsrestore');
            }

            $settings = $this->add_aws_settings([
                'credentials' => ['key' => $config->aws_keyid, 'secret' => $config->aws_keysecret],
                'region' => $config->aws_region]);
            $this->s3client = new S3Client($settings);
        }
        return $this->s3client;
    }

    /**
     * Adds additional entries to the AWS settings.
     *
     * @param $settings
     * @return array
     */
    private function add_aws_settings($settings): array {
        global $CFG;
        $settings['version'] = 'latest';
        $settings['signature_version'] = 'v4';
        if (!empty($CFG->proxyhost) && !empty($CFG->proxytype) && $CFG->proxytype != 'SOCKS5') {
            $host = (empty($CFG->proxyport)) ? $CFG->proxyhost : $CFG->proxyhost . ':' . $CFG->proxyport;
            $type = (empty($CFG->proxytype)) ? 'http://' : $CFG->proxytype;
            $cond = (!empty($CFG->proxyuser) and !empty($CFG->proxypassword));
            $user = $cond ? $CFG->proxyuser . '.' . $CFG->proxypassword . '@' : '';
            $settings['request.options'] = ['proxy' => "$type$user$host"];
        }

        if (static::$testhandler) {
            // A mock handler for testing.
            $settings['handler'] = static::$testhandler;
        }

        return $settings;
    }

    /**
     * Get the config object (or individual settings) for this plugin.
     *
     * @param string|null $key
     * @return false|mixed|object|string|null
     * @throws \dml_exception
     */
    private function get_config(?string $key = null) {
        if (is_null($this->config)) {
            $this->config = get_config('tool_s3wsrestore');
        }

        if (is_null($key)) {
            return $this->config;
        }

        if (!isset($this->config->$key)) {
            return null;
        }

        return $this->config->$key;
    }

}
