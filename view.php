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
 * The page of an activity: the routine, the progress of the student and the way into the player.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_oksigeniajepyso\local\routine;
use mod_oksigeniajepyso\output\view_data;

$id = optional_param('id', 0, PARAM_INT);
$instance = optional_param('j', 0, PARAM_INT);
if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'oksigeniajepyso');
} else {
    $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $instance], '*', MUST_EXIST);
    [$course, $cm] = get_course_and_cm_from_instance($jepyso, 'oksigeniajepyso');
}
$jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/oksigeniajepyso:view', $context);

oksigeniajepyso_view($jepyso, $course, $cm, $context);

$PAGE->set_url('/mod/oksigeniajepyso/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($jepyso->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();

if (has_capability('mod/oksigeniajepyso:viewreports', $context)) {
    foreach (routine::warnings_for((string) $jepyso->routine) as $warning) {
        echo $OUTPUT->notification($warning, \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->render_from_template('mod_oksigeniajepyso/view', view_data::for_page($jepyso, $cm, (int) $USER->id));
echo $OUTPUT->footer();
