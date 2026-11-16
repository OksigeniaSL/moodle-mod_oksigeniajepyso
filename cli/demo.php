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
 * Makes a course with example data to try the activity: a teacher, some students, a routine and some sessions.
 *
 * There is one course in Spanish and one in English, each with its own accounts. Run again, it makes nothing twice (it
 * does set the password again). For a development site only.
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

[$options] = cli_get_params(['password' => '', 'lang' => 'es', 'help' => false], ['h' => 'help']);
if ($options['help'] || $options['password'] === '' || !in_array($options['lang'], ['es', 'en'], true)) {
    cli_writeln("Makes a course with example data: a teacher, some students, an exercise routine with six sessions asked for,\n"
        . "completion and a grade of 10, and some sessions of the students. For a development site only.\n\n"
        . "--password=…   The password of the accounts.\n"
        . "--lang=es      «Educación Física (datos de ejemplo)», with the teacher «profe» and the students «ana» and\n"
        . "               «luis». The default.\n"
        . "--lang=en      «Physical Education (sample data)», with the teacher «daniel» and the students «emma», «james»\n"
        . "               and «priya», all of them with English as their language.\n"
        . "-h, --help     This help.");
    exit($options['help'] ? 0 : 2);
}

// What changes with the language: the course, the people, the activity, the routine and the sessions.
$samples = [
    'es' => [
        'fullname' => 'Educación Física (datos de ejemplo)',
        'shortname' => 'jepyso-ejemplo',
        'summary' => '<p>Curso con datos de ejemplo para probar la rutina de ejercicios de Jepyso.</p>',
        'section' => 'Fuerza y movilidad',
        'people' => [
            'profe' => ['Carlos', 'Medina', 'editingteacher'],
            'ana' => ['Ana', 'Martín', 'student'],
            'luis' => ['Luis', 'Pérez', 'student'],
        ],
        'name' => 'Rutina de fuerza (ejemplo)',
        'intro' => '<p>Haz esta rutina tres días a la semana. Si notas más molestia de la aceptable, para y apúntalo.</p>',
        // Signed by the teacher, Carlos: the students see «Tu rutina de Carlos».
        'routine' => [
            'v' => 1, 'para' => '', 'de' => 'Carlos', 'd' => 45, 'x' => 1, 'w' => 3, 'm' => 5, 'c' => 'a', 'f' => '2026-11-15',
            'e' => [
                ['i' => 'silla', 's' => 2, 'r' => 8, 't' => [2, 1, 2], 'l' => null],
                ['i' => 'puente', 's' => 3, 'r' => 10, 't' => [2, 2, 2], 'l' => null],
                ['i' => 'hombro', 's' => 2, 'r' => 6, 't' => [2, 1, 2], 'l' => 'der', 'n' => 'Sin dolor'],
            ],
        ],
        // Ana has done three, one of them with more discomfort than accepted; Luis noted one down without the app.
        'sessions' => [
            'ana' => [
                ['f' => '2026-11-09', 'h' => '18:05', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 9], 'm' => 3],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => 2],
                ], 'a' => 1],
                ['f' => '2026-11-11', 'h' => '17:40', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 7], 'm' => 3, 'c' => 'cuesta'],
                    [
                        'i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 8, 6], 'm' => 6,
                        'n' => 'La última serie me molestó en la zona lumbar.',
                    ],
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
        ],
    ],
    'en' => [
        'fullname' => 'Physical Education (sample data)',
        'shortname' => 'PE-sample',
        'summary' => '<p>A course with sample data to try the exercise routine of Jepyso.</p>',
        'section' => 'Strength and mobility',
        'people' => [
            'daniel' => ['Daniel', 'Hughes', 'editingteacher'],
            'emma' => ['Emma', 'Clarke', 'student'],
            'james' => ['James', 'Walker', 'student'],
            'priya' => ['Priya', 'Shah', 'student'],
        ],
        'name' => 'Strength routine (sample)',
        'intro' => '<p>Do this routine three days a week. If you feel more discomfort than the acceptable level, stop and '
            . 'note it down.</p>',
        // Signed by the teacher, Daniel: the students see «Your routine from Daniel».
        'routine' => [
            'v' => 1, 'para' => '', 'de' => 'Daniel', 'd' => 45, 'x' => 1, 'w' => 3, 'm' => 5, 'c' => 'a', 'f' => '2026-10-26',
            'e' => [
                ['i' => 'silla', 's' => 2, 'r' => 8, 't' => [2, 1, 2], 'l' => null],
                ['i' => 'puente', 's' => 3, 'r' => 10, 't' => [2, 2, 2], 'l' => null],
                [
                    'i' => 'hombro', 's' => 2, 'r' => 6, 't' => [2, 1, 2], 'l' => 'der', 'b' => 'roja',
                    'n' => 'Pain-free range only.',
                ],
                ['i' => 'talones', 's' => 2, 'r' => 12, 't' => [2, 1, 2], 'l' => null],
            ],
        ],
        // Emma has done five over three weeks: one found hard, one above the acceptable discomfort with a note, and in
        // the last one an exercise she could not do. James has done two, the second noted down without the app; Priya,
        // all six.
        'sessions' => [
            'emma' => [
                ['f' => '2026-10-27', 'h' => '18:10', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10], 'm' => 3],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => 1],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12], 'm' => 1],
                ], 'a' => 1],
                ['f' => '2026-10-29', 'h' => '17:50', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 3],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 9], 'm' => 4, 'c' => 'cuesta'],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => 2],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12], 'm' => 2],
                ], 'a' => 2],
                ['f' => '2026-11-03', 'h' => '18:05', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10], 'm' => 3],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => 2],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12], 'm' => 1],
                ], 'a' => 2],
                ['f' => '2026-11-05', 'h' => '18:00', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 3],
                    [
                        'i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 8, 6], 'm' => 6,
                        'n' => 'The last set bothered my lower back.',
                    ],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 5], 'm' => 3],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 10], 'm' => 2],
                ], 'a' => 3],
                ['f' => '2026-11-10', 'h' => '17:40', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 8], 'm' => 3],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [0, 0], 'c' => 'no'],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12], 'm' => 1],
                ], 'a' => 1],
            ],
            'james' => [
                ['f' => '2026-11-04', 'h' => '19:15', 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 7], 'm' => 2, 'c' => 'cuesta'],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10], 'm' => 2],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => 1],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12], 'm' => 1],
                ], 'a' => 1],
                ['f' => '2026-11-11', 'h' => '19:00', 'sinApp' => true, 'e' => [
                    ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8]],
                    ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10]],
                    ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6]],
                    ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12]],
                ]],
            ],
            'priya' => array_map(fn($day) => ['f' => $day[0], 'h' => $day[1], 'e' => [
                ['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => $day[2]],
                ['i' => 'puente', 's' => 3, 'r' => 10, 'p' => [10, 10, 10], 'm' => $day[2] + 1],
                ['i' => 'hombro', 'l' => 'der', 's' => 2, 'r' => 6, 'p' => [6, 6], 'm' => $day[2]],
                // A note left empty is not kept: the sessions are cleaned as those of the player.
                ['i' => 'talones', 's' => 2, 'r' => 12, 'p' => [12, 12], 'm' => $day[2], 'n' => $day[3]],
            ], 'a' => $day[2] ? 1 : 0], [
                ['2026-10-28', '07:40', 2, null],
                ['2026-10-30', '07:35', 2, null],
                ['2026-11-04', '07:45', 1, null],
                ['2026-11-06', '07:30', 1, 'Easier with a hand on the wall.'],
                ['2026-11-11', '07:40', 1, null],
                ['2026-11-13', '07:35', 0, null],
            ]),
        ],
    ],
];
$sample = $samples[$options['lang']];

// What the site names by itself, such as the announcements forum, in the language of the course.
force_current_language($options['lang']);
\core\session\manager::set_user(get_admin());

// The course.
$course = $DB->get_record('course', ['shortname' => $sample['shortname']]);
if (!$course) {
    $course = create_course((object) [
        'category' => core_course_category::get_default()->id,
        'fullname' => $sample['fullname'],
        'shortname' => $sample['shortname'],
        'summary' => $sample['summary'],
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 2,
        'enablecompletion' => 1,
    ]);
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
    course_update_section($course, $section, ['name' => $sample['section']]);
}

// The people, with their roles and the language of the course.
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$users = [];
foreach ($sample['people'] as $username => [$firstname, $lastname, $role]) {
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
            'lang' => $options['lang'],
        ], true, false);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    } else {
        update_internal_user_password($user, $options['password']);
    }
    // Enrolled once: enrolling again would send the welcome message of the course again.
    if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id])) {
        $enrol->enrol_user($instance, $user->id, $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST));
    }
    $users[$username] = $user;
}

// The activity, with the routine as the app puts it in a link: its JSON compressed with raw DEFLATE, in base64url.
$json = json_encode($sample['routine'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$routine = 'd.' . rtrim(strtr(base64_encode(gzdeflate($json)), '+/', '-_'), '=');
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
        'name' => $sample['name'],
        'introeditor' => [
            'text' => $sample['intro'],
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

// The sessions of the students.
foreach ($sample['sessions'] as $username => $records) {
    // The records go through the same path as those of the player: JSON decoded into objects, cleaned and kept.
    $result = sessions::save($jepyso, $cm, $course, (int) $users[$username]->id, json_decode(json_encode($records)));
    cli_writeln("$username: {$result['done']} of {$result['target']} sessions done");
}

cli_writeln("Course: {$course->id} ({$course->fullname})");
cli_writeln("Activity: cmid {$cm->id}, " . (new moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $cm->id]))->out(false));
