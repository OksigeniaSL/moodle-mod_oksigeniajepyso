<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The follow-up of an activity: how each student is doing, and the days of one of them.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_oksigeniajepyso\output\report_data;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'oksigeniajepyso');
$jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/oksigeniajepyso:viewreports', $context);

$url = new moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id]);
$PAGE->set_url($userid ? new moodle_url($url, ['userid' => $userid]) : $url);
$PAGE->set_title(format_string($jepyso->name) . ': ' . get_string('followup', 'mod_oksigeniajepyso'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->set_attrs(['description' => '', 'hidecompletion' => true]);

if ($userid) {
    // One student: someone with the routine to do, whom this teacher can see under the group mode.
    $student = core_user::get_user($userid, '*', MUST_EXIST);
    if (
        !is_enrolled($context, $student, 'mod/oksigeniajepyso:submit') ||
            !groups_user_groups_visible($course, $student->id, $cm)
    ) {
        throw new moodle_exception('invaliduserid', 'error');
    }
    $PAGE->navbar->add(get_string('followup', 'mod_oksigeniajepyso'), $url);
    $PAGE->navbar->add(fullname($student));
    echo $OUTPUT->header();
    // The name of the student heads the file, with their picture, in the template.
    echo $OUTPUT->render_from_template('mod_oksigeniajepyso/report_user', report_data::student($jepyso, $cm, $student));
    echo $OUTPUT->footer();
    exit;
}

// The node of the follow-up in the navigation of the activity already puts it in the breadcrumb.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('followup', 'mod_oksigeniajepyso'), 3);

groups_print_activity_menu($cm, $url);
$groupid = groups_get_activity_group($cm, true);
$seesall = groups_get_activity_groupmode($cm) != SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context);

if (!$seesall && !$groupid) {
    echo $OUTPUT->notification(get_string('notingroup', 'mod_oksigeniajepyso'), \core\output\notification::NOTIFY_INFO);
} else {
    $students = get_enrolled_users($context, 'mod/oksigeniajepyso:submit', (int) $groupid, 'u.*', 'u.lastname, u.firstname');
    echo $OUTPUT->render_from_template('mod_oksigeniajepyso/report', report_data::students($jepyso, $cm, $students));
}

echo $OUTPUT->footer();
