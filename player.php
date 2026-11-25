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
 * The player of an activity: its routine, done by whoever is logged in.
 *
 * A page of its own, without the theme (classes/output/player_page.php): what a phone loads is the figure and little
 * else. What the person does goes to the course through the external functions of the plugin.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_oksigeniajepyso\local\routine;
use mod_oksigeniajepyso\output\player_page;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'oksigeniajepyso');
$jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/oksigeniajepyso:view', $context);

$PAGE->set_url('/mod/oksigeniajepyso/player.php', ['id' => $cm->id]);

if (!routine::parse(routine::code_from((string) $jepyso->routine))) {
    $back = new moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $cm->id]);
    throw new moodle_exception('noroutine', 'mod_oksigeniajepyso', $back);
}

player_page::for_activity($jepyso, $cm)->show();
