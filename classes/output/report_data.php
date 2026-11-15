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

use mod_oksigeniajepyso\local\catalog;
use mod_oksigeniajepyso\local\routine;
use mod_oksigeniajepyso\local\sessions;

/**
 * What the follow-up of an activity shows: a row for each student, and the days of one of them.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_data {
    /** @var string The component, for the strings. */
    private const COMPONENT = 'mod_oksigeniajepyso';

    /**
     * The rows of the students.
     *
     * @param \stdClass $jepyso The activity.
     * @param \cm_info $cm Its course module.
     * @param \stdClass[] $students The students, as get_enrolled_users() gives them.
     * @return array
     */
    public static function students(\stdClass $jepyso, \cm_info $cm, array $students): array {
        global $OUTPUT;
        $summaries = sessions::summaries((int) $jepyso->id, array_map('intval', array_keys($students)));
        $target = (int) $jepyso->sessions;
        $rows = [];
        foreach ($students as $student) {
            $summary = $summaries[$student->id];
            $rows[] = [
                'picture' => $OUTPUT->user_picture($student, ['size' => 35, 'link' => false, 'alttext' => false]),
                'fullname' => fullname($student),
                'url' => (new \moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id, 'userid' => $student->id]))
                    ->out(false),
                'sessions' => $target > 0
                    ? get_string('sessionsshare', self::COMPONENT, (object) ['done' => $summary['done'], 'target' => $target])
                    : (string) $summary['done'],
                'last' => $summary['last'] ? view_data::day($summary['last']) : get_string('never', self::COMPONENT),
                'flagged' => $summary['flagged'],
                'hasflagged' => $summary['flagged'] > 0,
                'hard' => $summary['hard'],
                'couldnot' => $summary['couldnot'],
                'notes' => $summary['notes'],
            ];
        }
        return ['students' => $rows, 'hasstudents' => (bool) $rows];
    }

    /**
     * The days of one student, newest first.
     *
     * @param \stdClass $jepyso The activity.
     * @param \cm_info $cm Its course module.
     * @param \stdClass $student The student.
     * @return array
     */
    public static function student(\stdClass $jepyso, \cm_info $cm, \stdClass $student): array {
        global $OUTPUT;
        $parsed = routine::parse((string) $jepyso->routine);
        $limit = routine::limit($parsed);
        $prescribed = $parsed['e'] ?? [];
        $records = array_reverse(sessions::of_user((int) $jepyso->id, (int) $student->id));
        $done = 0;
        $days = [];
        foreach ($records as $record) {
            $done += (int) $record->done;
            $session = $record->session;
            $exercises = [];
            foreach ($session['e'] ?? [] as $exercise) {
                $exercises[] = self::exercise($exercise, $prescribed, $limit);
            }
            $morning = $session['a'] ?? null;
            $days[] = [
                'date' => view_data::day($record->day),
                'time' => $record->hm,
                'flagged' => (bool) $record->flagged,
                'withoutapp' => !empty($session['sinApp']),
                'corrected' => !empty($session['ed']),
                'exercises' => $exercises,
                'nextmorning' => $morning === null ? '' : self::over(
                    get_string('nextmorning', self::COMPONENT, $morning),
                    $morning > $limit
                ),
            ];
        }
        return [
            'backurl' => (new \moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id]))->out(false),
            'picture' => $OUTPUT->user_picture($student, ['size' => 64, 'link' => false, 'alttext' => false]),
            'fullname' => fullname($student),
            'progress' => view_data::progress(sessions::done((int) $jepyso->id, (int) $student->id), (int) $jepyso->sessions),
            'limit' => get_string('limit', self::COMPONENT, $limit),
            'days' => $days,
            'hasdays' => (bool) $days,
        ];
    }

    /**
     * One exercise of a day: what was done against what the routine asks for.
     *
     * @param array $exercise The exercise, as kept.
     * @param array[] $prescribed The exercises of the routine.
     * @param int $limit The acceptable discomfort.
     * @return array
     */
    private static function exercise(array $exercise, array $prescribed, int $limit): array {
        $id = (string) ($exercise['i'] ?? '');
        $item = catalog::get($id);
        $unit = $item['unit'] ?? 'reps';
        $sets = array_map(fn($count) => routine::amount((int) $count, $unit), $exercise['p'] ?? []);
        $asked = null;
        foreach ($prescribed as $candidate) {
            if ($candidate['i'] === $id && ($asked === null || $candidate['l'] === ($exercise['l'] ?? null))) {
                $asked = $candidate;
            }
        }
        $discomfort = $exercise['m'] ?? null;
        $side = $exercise['l'] ?? null;
        return [
            'name' => $item['name'] ?? $id,
            'side' => $side && ($item['oneside'] ?? false) ? get_string('side' . $side, self::COMPONENT) : '',
            'done' => array_sum($exercise['p'] ?? []) > 0
                ? get_string('repsdone', self::COMPONENT, implode(', ', $sets))
                : get_string('nothingdone', self::COMPONENT),
            'prescribed' => $asked
                ? get_string('prescribed', self::COMPONENT, routine::dosage($asked, $unit))
                : get_string('notinroutine', self::COMPONENT),
            'discomfort' => $discomfort === null ? '' : self::over(
                get_string('discomfort', self::COMPONENT, $discomfort),
                $discomfort > $limit
            ),
            'hard' => ($exercise['c'] ?? '') === 'cuesta',
            'couldnot' => ($exercise['c'] ?? '') === 'no',
            'note' => isset($exercise['n']) ? get_string('note', self::COMPONENT, (string) $exercise['n']) : '',
        ];
    }

    /**
     * A discomfort, said to be above the acceptable one when it is: in words, not by colour alone.
     *
     * @param string $text The discomfort in words.
     * @param bool $over Whether it is above the acceptable one.
     * @return string
     */
    private static function over(string $text, bool $over): string {
        return $over ? $text . ' · ' . get_string('abovelimit', self::COMPONENT) : $text;
    }
}
