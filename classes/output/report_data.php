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
            $alerts = self::alerts($summary);
            $rows[] = [
                'picture' => $OUTPUT->user_picture($student, ['size' => 35, 'link' => false, 'alttext' => false]),
                'fullname' => fullname($student),
                'url' => (new \moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id, 'userid' => $student->id]))
                    ->out(false),
                'sessions' => self::share($summary['done'], $target),
                'last' => $summary['last'] ? view_data::day($summary['last']) : get_string('never', self::COMPONENT),
                'flagged' => $summary['flagged'],
                'hasflagged' => $summary['flagged'] > 0,
                'hard' => $summary['hard'],
                'couldnot' => $summary['couldnot'],
                'notes' => $summary['notes'],
                'alerts' => $alerts,
                'hasalerts' => (bool) $alerts,
                'nosessions' => $summary['done'] === 0,
                'hastarget' => $target > 0,
                'twentieths' => $target > 0 ? (int) floor(min($summary['done'], $target) * 20 / $target) : 0,
            ];
        }
        return ['students' => $rows, 'hasstudents' => (bool) $rows];
    }

    /**
     * What there is to look at in a student, each said in words and only when there is something: the days above the
     * acceptable discomfort first, which are the ones that matter.
     *
     * @param array $summary As sessions::summaries() gives it.
     * @return array Each with alerttext and alertstrong.
     */
    public static function alerts(array $summary): array {
        $alerts = [];
        $counts = [
            ['flagged', 'flaggeddaysone', 'flaggeddayscount', true],
            ['hard', 'hardonce', 'hardtimes', false],
            ['couldnot', 'couldnotonce', 'couldnottimes', false],
            ['notes', 'notesone', 'notescount', false],
        ];
        foreach ($counts as [$key, $one, $many, $strong]) {
            $count = (int) $summary[$key];
            if ($count > 0) {
                $alerts[] = [
                    'alerttext' => $count === 1 ? get_string($one, self::COMPONENT) : get_string($many, self::COMPONENT, $count),
                    'alertstrong' => $strong,
                ];
            }
        }
        return $alerts;
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
                'title' => self::weekday($record->day),
                'time' => $record->hm,
                'flagged' => (bool) $record->flagged,
                'withoutapp' => !empty($session['sinApp']),
                'corrected' => !empty($session['ed']),
                'hasdaytags' => (bool) $record->flagged || !empty($session['sinApp']) || !empty($session['ed']),
                'exercises' => $exercises,
                'nextmorning' => $morning === null ? '' : self::over(
                    get_string('nextmorning', self::COMPONENT, $morning),
                    $morning > $limit
                ),
                'morningover' => $morning !== null && $morning > $limit,
            ];
        }
        $done = sessions::done((int) $jepyso->id, (int) $student->id);
        $weeks = self::weeks($records, $limit);
        return [
            'backurl' => (new \moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id]))->out(false),
            'picture' => $OUTPUT->user_picture($student, ['size' => 64, 'link' => false, 'alttext' => false]),
            'fullname' => fullname($student),
            'progress' => get_string('studentprogress', self::COMPONENT, self::share($done, (int) $jepyso->sessions)),
            'limit' => get_string('limit', self::COMPONENT, $limit),
            'days' => $days,
            'hasdays' => (bool) $days,
            'weeks' => $weeks,
            'hasweeks' => (bool) $weeks,
            'weekshelp' => get_string('weekshelp', self::COMPONENT, $limit),
            'limitlevel' => $limit,
        ] + view_data::tally($done, (int) $jepyso->sessions);
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
        $each = (int) ($exercise['r'] ?? ($asked['r'] ?? 0));
        $cells = [];
        foreach ($exercise['p'] ?? [] as $count) {
            $cells[] = ['setcount' => routine::amount((int) $count, $unit), 'setshort' => $each > 0 && (int) $count < $each];
        }
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
            'sets' => array_sum($exercise['p'] ?? []) > 0 ? $cells : [],
            'hassets' => array_sum($exercise['p'] ?? []) > 0,
            'askedeach' => $each > 0 ? get_string('askedeach', self::COMPONENT, routine::amount($each, $unit)) : '',
            'inroutine' => $asked !== null,
            'discomfortover' => $discomfort !== null && $discomfort > $limit,
            'hard' => ($exercise['c'] ?? '') === 'cuesta',
            'couldnot' => ($exercise['c'] ?? '') === 'no',
            'note' => isset($exercise['n']) ? get_string('note', self::COMPONENT, (string) $exercise['n']) : '',
        ];
    }

    /**
     * The third rule of the pain-monitoring model, as the app shows it: week by week, oldest first, the sessions and
     * the highest discomfort during the exercises and on getting up the next morning. A week is marked when either went
     * up from the week before.
     *
     * @param \stdClass[] $records The sessions of a student, as sessions::of_user() gives them, in any order.
     * @param int $limit The acceptable discomfort.
     * @return array
     */
    public static function weeks(array $records, int $limit): array {
        $groups = [];
        foreach ($records as $record) {
            [$year, $month, $date] = array_map('intval', explode('-', $record->day));
            $noon = gmmktime(12, 0, 0, $month, $date, $year);
            $monday = gmdate('Y-m-d', $noon - ((int) gmdate('N', $noon) - 1) * DAYSECS);
            $groups[$monday][] = $record->session;
        }
        ksort($groups);
        $weeks = [];
        $before = null;
        foreach ($groups as $monday => $list) {
            $during = [];
            $waking = [];
            foreach ($list as $session) {
                foreach ($session['e'] ?? [] as $exercise) {
                    if (isset($exercise['m'])) {
                        $during[] = (int) $exercise['m'];
                    }
                }
                if (isset($session['a'])) {
                    $waking[] = (int) $session['a'];
                }
            }
            $week = [
                'during' => $during ? max($during) : null,
                'waking' => $waking ? max($waking) : null,
            ];
            $rose = fn($now, $then) => $now !== null && $then !== null && $now > $then;
            $up = $before && ($rose($week['during'], $before['during']) || $rose($week['waking'], $before['waking']));
            $weeks[] = [
                'weekof' => self::shortday($monday),
                'weeksessions' => count($list),
                'during' => self::level($week['during'], $limit),
                'waking' => self::level($week['waking'], $limit),
                'weekup' => (bool) $up,
            ];
            $before = $week;
        }
        return $weeks;
    }

    /**
     * A discomfort for the bars of the weeks: its value, whether it passes the limit, or that there is none.
     *
     * @param int|null $value From 0 to 10, or null.
     * @param int $limit The acceptable discomfort.
     * @return array
     */
    private static function level(?int $value, int $limit): array {
        return [
            'hasvalue' => $value !== null,
            'value' => $value ?? 0,
            'valueover' => $value !== null && $value > $limit,
            'nonzero' => (bool) $value,
        ];
    }

    /**
     * A day with its weekday, for the head of a session: «Sunday, 15 November 2026».
     *
     * @param string $day A day, YYYY-MM-DD.
     * @return string
     */
    public static function weekday(string $day): string {
        [$year, $month, $date] = array_map('intval', explode('-', $day));
        $text = userdate(make_timestamp($year, $month, $date, 12), get_string('strftimedaydate', 'langconfig'));
        return \core_text::strtoupper(\core_text::substr($text, 0, 1)) . \core_text::substr($text, 1);
    }

    /**
     * A day without its year, for the weeks: «9 November».
     *
     * @param string $day A day, YYYY-MM-DD.
     * @return string
     */
    private static function shortday(string $day): string {
        [$year, $month, $date] = array_map('intval', explode('-', $day));
        return userdate(make_timestamp($year, $month, $date, 12), get_string('strftimedateshort', 'langconfig'));
    }

    /**
     * The sessions done, out of those asked for if there is a number: «2 of 6», or «2».
     *
     * @param int $done The sessions done.
     * @param int $target The sessions asked for, or 0.
     * @return string
     */
    private static function share(int $done, int $target): string {
        return $target > 0
            ? get_string('sessionsshare', self::COMPONENT, (object) ['done' => $done, 'target' => $target])
            : (string) $done;
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
