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
 * The builder of routines, opened from the form of an activity: the routine it makes goes back to that form.
 *
 * Opened with the course of a new activity, or with the course module of one that exists. A page of its own, as the
 * player (player.php).
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_oksigeniajepyso\output\player_page;

$id = optional_param('id', 0, PARAM_INT);
if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'oksigeniajepyso');
    require_login($course, false, $cm);
    require_capability('moodle/course:manageactivities', context_module::instance($cm->id));
    $PAGE->set_url('/mod/oksigeniajepyso/builder.php', ['id' => $cm->id]);
} else {
    $course = get_course(required_param('course', PARAM_INT));
    require_login($course, false);
    require_capability('mod/oksigeniajepyso:addinstance', context_course::instance($course->id));
    $PAGE->set_url('/mod/oksigeniajepyso/builder.php', ['course' => $course->id]);
}

player_page::for_builder()->show();
