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
 * Page overview.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\apibridge;

global $PAGE, $OUTPUT, $CFG, $USER, $COURSE;

$courseid = required_param('courseid', PARAM_INT);
$videoid = required_param('videoid', PARAM_ALPHANUMEXT);
$message = required_param('inputMessage', PARAM_TEXT);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:viewunpublishedvideos', $coursecontext);

// Check if support email is set.
if (empty(get_config('block_opencast', 'support_email'))) {
    redirect($redirecturl,
        get_string('support_setting_notset', 'block_opencast'),
        null, \core\output\notification::NOTIFY_ERROR);
}

// Create email.
$user = new \stdClass();
$user->id = -1;
$user->email = get_config('block_opencast', 'support_email');
$user->mailformat = 1;

$apibridge = apibridge::get_instance();
$result = $apibridge->get_opencast_video($videoid);

if (!$result->error) {
    // Check that series is associated with block.
    $seriesid = $apibridge->get_course_series($courseid);
    if ($seriesid->identifier != $result->video->is_part_of) {
        redirect($redirecturl,
            get_string('reportproblem_notallowed', 'block_opencast'),
            null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $mailinfo = new \stdClass();
    $mailinfo->username = $USER->username;
    $mailinfo->useremail = $USER->email;
    $mailinfo->courselink = (new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid)))->out();
    $mailinfo->course = $COURSE->fullname;
    $mailinfo->series = $result->video->series;
    $mailinfo->seriesid = $result->video->is_part_of;
    $mailinfo->event = $result->video->title;
    $mailinfo->eventid = $result->video->identifier;
    $mailinfo->message = $message;
    $message = get_string('reportproblem_email', 'block_opencast', $mailinfo);

    // Send email to support.
    $success = email_to_user(
        $user,
        get_admin(),
        get_string('reportproblem_subject', 'block_opencast'),
        $message
    );

    if ($success) {
        // Send copy to user.
        \block_opencast\local\notifications::notify_problem_reported(get_string('reportproblem_notification', 'block_opencast') . $message);

        // Redirect with success message.
        redirect($redirecturl,
            get_string('reportproblem_success', 'block_opencast'),
            null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // Redirect with failure message.
    redirect($redirecturl,
        get_string('reportproblem_failure', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
} else {
    // Redirect with failure message.
    redirect($redirecturl,
        get_string('video_retrieval_failed', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}


