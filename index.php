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
 * The exercise routines of a course.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);
$coursecontext = context_course::instance($course->id);

\mod_oksigeniajepyso\event\course_module_instance_list_viewed::create(['context' => $coursecontext])->trigger();

$strplural = get_string('modulenameplural', 'mod_oksigeniajepyso');
$PAGE->set_url('/mod/oksigeniajepyso/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($strplural);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($strplural);

echo $OUTPUT->header();
echo $OUTPUT->heading($strplural);

$instances = get_all_instances_in_course('oksigeniajepyso', $course);
if (!$instances) {
    notice(get_string('thereareno', 'moodle', $strplural), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$activities = [];
foreach ($instances as $instance) {
    $activities[] = [
        'section' => get_section_name($course, $instance->section),
        'url' => (new moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $instance->coursemodule]))->out(false),
        'name' => format_string($instance->name, true, ['context' => context_module::instance($instance->coursemodule)]),
        'sessions' => $instance->sessions ?: '-',
        'dimmed' => !$instance->visible,
    ];
}
echo $OUTPUT->render_from_template('mod_oksigeniajepyso/index', [
    'sectionlabel' => get_string('sectionname', 'format_' . $course->format),
    'activities' => $activities,
]);
echo $OUTPUT->footer();
