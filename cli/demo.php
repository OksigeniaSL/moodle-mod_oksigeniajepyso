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
 * Makes a course with example data to try the activity: a teacher, two students, a routine and some sessions.
 *
 * Run again, it makes nothing twice (it does set the password again). For a development site only.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

use mod_oksigeniajepyso\local\sessions;

[$options] = cli_get_params(['password' => '', 'help' => false], ['h' => 'help']);
if ($options['help'] || $options['password'] === '') {
    cli_writeln("Makes the course «Educación Física (datos de ejemplo)» with example data: the teacher «profe», the\n"
        . "students «ana» and «luis», an exercise routine with six sessions asked for, completion and a grade of 10,\n"
        . "and some sessions of the students. For a development site only.\n\n"
        . "--password=…   The password of the three accounts.\n"
        . "-h, --help     This help.");
    exit($options['help'] ? 0 : 2);
}

\core\session\manager::set_user(get_admin());

// The course.
$course = $DB->get_record('course', ['shortname' => 'jepyso-ejemplo']);
if (!$course) {
    $course = create_course((object) [
        'category' => core_course_category::get_default()->id,
        'fullname' => 'Educación Física (datos de ejemplo)',
        'shortname' => 'jepyso-ejemplo',
        'summary' => '<p>Curso con datos de ejemplo para probar la rutina de ejercicios de Jepyso.</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 2,
        'enablecompletion' => 1,
    ]);
}

// The people, with their roles.
$people = [
    'profe' => ['Carlos', 'Medina', 'editingteacher'],
    'ana' => ['Ana', 'Martín', 'student'],
    'luis' => ['Luis', 'Pérez', 'student'],
];
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$users = [];
foreach ($people as $username => [$firstname, $lastname, $role]) {
    $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
    if (!$user) {
        // Moodle 5.3 moved the function into the user class; 4.5 only has the old one.
        $create = method_exists(\core\user::class, 'create_user') ? [\core\user::class, 'create_user'] : 'user_create_user';
        $id = $create((object) [
            'username' => $username,
            'password' => $options['password'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $username . '@example.com',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => 'es',
        ], true, false);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    } else {
        update_internal_user_password($user, $options['password']);
    }
    $enrol->enrol_user($instance, $user->id, $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST));
    $users[$username] = $user;
}

// The activity.
$routine = 'd.bc3BCoJAFIXhV4l_fQVnTIn7DO1aigvLiYRRY9QKxHcPlSCo3Vl8h3_igRrhXoYSBaFyKMex7peN7lPhtZInmggNmgoXlBLhim'
    . 'Jjm0XGRCZFcGg-UaP0tfcL6VErBPQgDGhuxYgtBI-2o_ezbPw-unZwm09Wb-LPwf473LrmHLrvQPYToHIBoUU51e2u6nwXmIv5DQ';
$cm = null;
foreach (get_fast_modinfo($course)->get_instances_of('oksigeniajepyso') as $existing) {
    $cm = $existing;
    break;
}
if (!$cm) {
    $module = create_module((object) [
        'modulename' => 'oksigeniajepyso',
        'course' => $course->id,
        'section' => 1,
        'visible' => 1,
        'cmidnumber' => '',
        'name' => 'Rutina de fuerza (ejemplo)',
        'introeditor' => [
            'text' => '<p>Haz esta rutina tres días a la semana. Si notas más molestia de la aceptable, para y apúntalo.</p>',
            'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid(),
        ],
        'routine' => $routine,
        'sessions' => 6,
        'grade' => 10,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionview' => 0,
        'completionsessions' => 1,
    ]);
    $cm = get_fast_modinfo($course->id)->get_cm($module->coursemodule);
}
$jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

// Some sessions: Ana has done three, one of them with more discomfort than accepted; Luis noted one down without the app.
$examples = [
    'ana' => [
        ['f' => '2026-11-09', 'h' => '18:05', 'e' => [
            ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2],
            ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 9], 'm' => 3],
            ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => 2],
        ], 'a' => 1],
        ['f' => '2026-11-11', 'h' => '17:40', 'e' => [
            ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 7], 'm' => 3, 'c' => 'cuesta'],
            ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 8, 6], 'm' => 6, 'n' => 'La última serie me molestó en la zona lumbar.'],
            ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 5], 'm' => 4],
        ], 'a' => 3],
        ['f' => '2026-11-13', 'h' => '18:20', 'e' => [
            ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2],
            ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10], 'm' => 3],
            ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [0, 0], 'c' => 'no'],
        ]],
    ],
    'luis' => [
        ['f' => '2026-11-12', 'h' => '19:00', 'sinApp' => true, 'e' => [
            ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8]],
            ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10]],
            ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6]],
        ]],
    ],
];
foreach ($examples as $username => $records) {
    // The records go through the same path as those of the player: JSON decoded into objects, cleaned and kept.
    $result = sessions::save($jepyso, $cm, $course, (int) $users[$username]->id, json_decode(json_encode($records)));
    cli_writeln("$username: {$result['done']} of {$result['target']} sessions done");
}

cli_writeln("Course: {$course->id} ({$course->fullname})");
cli_writeln("Activity: cmid {$cm->id}, " . (new moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $cm->id]))->out(false));
