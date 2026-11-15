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

namespace mod_oksigeniajepyso\local;

/**
 * One session as the player sends it, cleaned as the app cleans a day (limpiarDia in app/entrada.js of Jepyso): only
 * the known fields pass, with their type and range; integers out of range are brought within it.
 *
 * { f: day, h: time, e: [{ i, l, s, r, p: [repetitions of each set], m, c, n }], a, ed, sinApp }
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_cleaner {
    /** @var int The most exercises a session has. */
    public const EXERCISES = 40;

    /** @var int The most sets an exercise has. */
    public const SETS = 6;

    /** @var int The longest note of the person on an exercise. */
    public const NOTE = 300;

    /**
     * A session cleaned, or null if it is none (no valid day, or no list of exercises).
     *
     * @param mixed $record The session as it came, decoded from JSON into objects.
     * @return array|null
     */
    public static function clean($record): ?array {
        if (!value::is_object($record) || !value::is_day($record->f ?? null) || !is_array($record->e ?? null)) {
            return null;
        }
        $exercises = [];
        foreach (value::items($record->e, self::EXERCISES) as $item) {
            if (!value::is_object($item) || !is_string($item->i ?? null) || !preg_match(catalog::ID, $item->i)) {
                continue;
            }
            $sets = array_map(
                fn($count) => value::integer($count, 0, 50, 0),
                value::items($item->p ?? null, self::SETS)
            );
            $exercise = [
                'i' => $item->i,
                'l' => value::side($item->l ?? null, $item->i),
                's' => value::integer($item->s ?? null, 1, 6, count($sets) ?: 1),
                'r' => value::integer($item->r ?? null, 1, 30, 1),
                'p' => $sets,
            ];
            $discomfort = value::integer($item->m ?? null, 0, 10, null);
            if ($discomfort !== null) {
                $exercise['m'] = $discomfort;
            }
            $could = $item->c ?? null;
            if ($could === 'cuesta' || $could === 'no') {
                $exercise['c'] = $could;
            }
            $note = value::trim(value::text($item->n ?? null, self::NOTE));
            if ($note !== '') {
                $exercise['n'] = $note;
            }
            $exercises[] = $exercise;
        }
        $session = ['f' => $record->f];
        if (value::is_time($record->h ?? null)) {
            $session['h'] = $record->h;
        }
        $session['e'] = $exercises;
        $morning = value::integer($record->a ?? null, 0, 10, null);
        if ($morning !== null) {
            $session['a'] = $morning;
        }
        if (value::is_moment($record->ed ?? null)) {
            $session['ed'] = $record->ed;
        }
        if (($record->sinApp ?? null) === true) {
            $session['sinApp'] = true;
        }
        return $session;
    }

    /**
     * Whether a session counts as done: some repetition made, or a day noted down without the player.
     *
     * @param array $session A cleaned session.
     * @return bool
     */
    public static function is_done(array $session): bool {
        if (!empty($session['sinApp'])) {
            return true;
        }
        foreach ($session['e'] as $exercise) {
            if (array_sum($exercise['p']) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether some discomfort of a session, during an exercise or the next morning, went over the acceptable one.
     *
     * @param array $session A cleaned session.
     * @param int $limit The acceptable discomfort of the routine, out of 10.
     * @return bool
     */
    public static function is_flagged(array $session, int $limit): bool {
        if (isset($session['a']) && $session['a'] > $limit) {
            return true;
        }
        foreach ($session['e'] as $exercise) {
            if (isset($exercise['m']) && $exercise['m'] > $limit) {
                return true;
            }
        }
        return false;
    }
}
