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

namespace mod_oksigeniajepyso\output;

use mod_oksigeniajepyso\local\sessions;
use mod_oksigeniajepyso\local\template;

/**
 * The page of the activity in the Moodle app: the description, the exercises with their dose, the progress, and a
 * button that opens the player in the browser of the app with the session. No script of its own runs in the app.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * The page of the activity.
     *
     * @param array $args What the app sends: cmid and courseid among others.
     * @return array
     */
    public static function mobile_course_view(array $args): array {
        global $CFG, $DB, $OUTPUT, $USER;
        require_once($CFG->dirroot . '/mod/oksigeniajepyso/lib.php');

        [$course, $cm] = get_course_and_cm_from_cmid((int) ($args['cmid'] ?? 0), 'oksigeniajepyso');
        require_login($course, false, $cm, true, true);
        $context = \context_module::instance($cm->id);
        require_capability('mod/oksigeniajepyso:view', $context);
        $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);

        oksigeniajepyso_view($jepyso, $course, $cm, $context);

        $page = view_data::for_page($jepyso, $cm, (int) $USER->id);
        $data = [
            'cmid' => $cm->id,
            'courseid' => $course->id,
            'hasroutine' => $page['hasroutine'],
            'noroutine' => $page['noroutine'],
            'progress' => $page['progress'],
            'last' => $page['last'],
            'starturl' => $page['starturl'],
            'startlabel' => $page['startlabel'],
            'previewnote' => $page['previewnote'],
            'casturl' => $page['cast'],
            'size' => $page['size'],
            // The share of the planned sessions done, for the bar of the app, in its colours.
            'hasprogressbar' => $page['progress'] !== '' && (int) $jepyso->sessions > 0,
            'fraction' => (int) $jepyso->sessions > 0
                ? round(min(1, sessions::done((int) $jepyso->id, (int) $USER->id) / (int) $jepyso->sessions), 2) : 0,
            'exerciseshead' => get_string('exercises', 'mod_oksigeniajepyso'),
            'howtohead' => get_string('howto', 'mod_oksigeniajepyso'),
            'facts' => [],
            'limitlabel' => '',
            'limitfigure' => '',
            'exercises' => [],
        ];
        if ($page['routine']) {
            $routine = $page['routine'];
            $data['facts'] = $routine['facts'];
            $data['limitlabel'] = $routine['limitlabel'];
            $data['limitfigure'] = $routine['limitfigure'];
            foreach ($routine['exercises'] as $exercise) {
                $data['exercises'][] = [
                    'name' => $exercise['number'] . '. ' . $exercise['name'],
                    'posterurl' => $exercise['poster'],
                    'mirrored' => $exercise['mirrored'],
                    'lines' => array_values(array_filter([
                        $exercise['dosage'],
                        $exercise['side'],
                        $exercise['band'],
                        $exercise['note'],
                        $exercise['available'] ? '' : get_string('notavailable', 'mod_oksigeniajepyso'),
                    ])),
                ];
            }
        }

        // The description is what the teacher wrote: it goes as data, shown by the app, and never into the template.
        [$intro] = \core_external\util::format_text(
            $jepyso->intro,
            $jepyso->introformat,
            $context,
            'mod_oksigeniajepyso',
            'intro',
            null
        );

        return [
            'templates' => [[
                'id' => 'main',
                'html' => $OUTPUT->render_from_template('mod_oksigeniajepyso/mobile_view', template::inert($data)),
            ]],
            'javascript' => '',
            'otherdata' => ['intro' => $intro],
            'files' => [],
        ];
    }
}
