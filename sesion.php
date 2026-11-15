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
 * What the player needs to start in an activity: who is playing, where to save and what was already saved.
 *
 * The player, at reproductor/index.html?cm=…, asks for it with the session cookie. Without a session it gets an error
 * in JSON, never the login page.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

use mod_oksigeniajepyso\local\catalog;
use mod_oksigeniajepyso\local\sessions;
use mod_oksigeniajepyso\output\view_data;

$cmid = required_param('cm', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'oksigeniajepyso');
$jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm, false, true);
$context = context_module::instance($cm->id);
require_capability('mod/oksigeniajepyso:view', $context);

$data = [
    'sesskey' => sesskey(),
    'servicio' => $CFG->wwwroot . '/lib/ajax/service.php',
    'volver' => (new moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $cm->id]))->out(false),
    'curso' => view_data::plain($course->shortname, context_course::instance($course->id)),
    'actividad' => view_data::plain($jepyso->name, $context),
    'nombre' => $USER->firstname,
    'puede' => has_capability('mod/oksigeniajepyso:submit', $context, null, false),
    'sesiones' => sessions::for_player((int) $jepyso->id, (int) $USER->id),
    'disponibles' => catalog::available(),
];

echo $OUTPUT->header();
// The ajax header already says no-cache; this is the one the player relies on.
header('Cache-Control: no-store');
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
