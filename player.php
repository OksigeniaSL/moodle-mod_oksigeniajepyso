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
 * The way into the player: the routine of the activity, in the address the player reads.
 *
 * The routine goes after «#r=» as in the links of the app; moodle_url would encode that sign, so the address is put
 * together here. The code was checked when the activity was saved, and only has letters, digits, «.», «-» and «_».
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_oksigeniajepyso\local\routine;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'oksigeniajepyso');
$jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/oksigeniajepyso:view', $context);

$code = routine::code_from((string) $jepyso->routine);
if (!routine::parse($code)) {
    $back = new moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $cm->id]);
    throw new moodle_exception('noroutine', 'mod_oksigeniajepyso', $back);
}

// The player speaks the language of this user in Moodle (it maps any Moodle language to Spanish or English), unless
// the person chose another one inside the player.
$lang = rawurlencode(current_language());
redirect($CFG->wwwroot . '/mod/oksigeniajepyso/reproductor/index.html?cm=' . $cm->id . '&idioma=' . $lang . '#r=' . $code);
