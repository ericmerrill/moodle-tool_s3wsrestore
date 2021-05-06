A plugin that exposes a webservice that takes a S3 object key, and attempts to down that file, and restore it into a specified course, asynchronously.

### Setup
* The Moodle site will need to have web services enabled.
* In Site Admin > Plugins > External services, add an Authorized User to the S3 Restore Service.
* In Site Admin > Plugins > Manage Tokens, create a new token, setting it for the authorized user, and setting Service to S3 Restore Service.
* In Site Admin > Plugins > S3 Web Service Course Restore, setup the AWS credentials, and the S3 bucket to be used.
    * The credentialed AWS user will only need read access to the bucket.

### Protocol

#### tool_s3wsrestore_restore_course
* Parameters
    * `s3key` - The S3 file object key to use. Should be an mbz file.
    * `courseid` - The ID of the course to restore into
* Return
    * `status` - Status string, either `error` or `success`
    * `message` (optional) - A detailed explanation of an error status
    * `restoreid` (optional) - The ID of request if successful, to be used with status requests

#### tool_s3wsrestore_get_restore_status
* Parameters
    * `restoreid` - The restore ID of the request to get a status about
* Return
    * A string of these possible values:
        * `notfound`
        * `pending`
        * `inprogress`
        * `failed`
        * `complete`
        * `unknownstatus`

#### tool_s3wsrestore_get_detailed_restore_status
* Parameters
    * `restoreid` - The restore ID of the request to get a status about
* Return
    * `status` - The string, current status of the restore, one of these possible values:
        * `notfound`
        * `pending`
        * `downloading`
        * `extracting`
        * `restoring`
        * `error`
        * `failed`
        * `complete`
    * `errormessage` (optional) - An error message string, if there is one. Only with a `status` of `error` or `failed`
    * `progress` (optional) - Float, from 0.0 to 100.0, showing the current progress of the course restore, as reported by the Moodle restore system. Only with a `status` of `restoring`.

### TODO:
* Create a cleanup task
* Reprocessing of errored tasks
* Clear 'stuck' tasks after some (long) timeout
