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
 * A routine as the app sends it in a link: «d.» and the JSON compressed with raw DEFLATE, or «j.» and the JSON as it is,
 * both in base64url without padding.
 *
 * The activity keeps the code as it came; this class decodes it, cleans it as the app does (limpiarRutina) and says
 * what a page shows of it.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class routine {
    /** @var int The longest code a link can carry. */
    public const MAXLENGTH = 20000;

    /** @var int[] The rests between sets the app offers, in seconds. */
    public const RESTS = [30, 45, 60, 90];

    /** @var int The acceptable discomfort when the routine does not say, out of 10. */
    public const LIMIT = 5;

    /** @var string[] The colours of the elastic bands. */
    public const BANDS = ['amarilla', 'roja', 'verde', 'azul', 'negra'];

    /** @var int The most exercises a routine has. */
    private const EXERCISES = 40;

    /** @var int The longest note of the professional for an exercise. */
    private const NOTE = 4000;

    /** @var int The longest name, of the professional or of the person. */
    private const NAME = 200;

    /** @var int The largest a routine can grow once inflated: a full one is far smaller. */
    private const INFLATED = 1048576;

    /**
     * The code of a routine from what a teacher pastes: a whole link with «#r=…» or the code alone.
     *
     * @param string $input What was pasted.
     * @return string The code, or an empty text.
     */
    public static function code_from(string $input): string {
        $input = trim($input);
        $at = strpos($input, '#r=');
        if ($at !== false) {
            $input = substr($input, $at + 3);
            // Anything after the code in the fragment is not part of it.
            $input = substr($input, 0, strcspn($input, '&#? '));
        }
        return trim($input);
    }

    /**
     * The JSON of a routine code, decoded, or null if it is not one.
     *
     * @param string $code The code.
     * @return \stdClass|null
     */
    public static function decode(string $code): ?\stdClass {
        if (strlen($code) > self::MAXLENGTH || !preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
            return null;
        }
        $kind = substr($code, 0, 2);
        if ($kind !== 'd.' && $kind !== 'j.') {
            return null;
        }
        $bytes = base64_decode(strtr(substr($code, 2), '-_', '+/'), true);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        if ($kind === 'd.') {
            // A broken stream is just a wrong code: the warning PHP gives for it says nothing more.
            $bytes = @gzinflate($bytes, self::INFLATED);
            if ($bytes === false) {
                return null;
            }
        }
        $data = json_decode($bytes, false, 32);
        return $data instanceof \stdClass ? $data : null;
    }

    /**
     * A routine code decoded and cleaned as the app cleans it, or null if it is not one.
     *
     * Exercise ids are checked for their form only: whether the catalogue has them is for check() and describe().
     *
     * @param string $code The code.
     * @return array|null With d, x, w, m, c, f, para, de and e (each exercise with i, s, r, t, l, b and n).
     */
    public static function parse(string $code): ?array {
        $data = self::decode($code);
        if (!$data || !isset($data->e) || !is_array($data->e)) {
            return null;
        }
        $exercises = [];
        foreach (value::items($data->e, self::EXERCISES) as $item) {
            if (!value::is_object($item) || !is_string($item->i ?? null) || !preg_match(catalog::ID, $item->i)) {
                continue;
            }
            $id = $item->i;
            $exercise = catalog::get($id);
            $note = value::text($item->n ?? null, self::NOTE);
            $band = $item->b ?? null;
            $exercises[] = [
                'i' => $id,
                's' => value::integer($item->s ?? null, 1, 6, 1),
                'r' => value::integer($item->r ?? null, 1, $exercise && $exercise['unit'] === 'minutos' ? 60 : 30, 10),
                't' => self::tempo($item->t ?? null),
                'l' => value::side($item->l ?? null, $id),
                'b' => is_string($band) && in_array($band, self::BANDS, true) ? $band : null,
                'n' => $note !== '' ? $note : null,
            ];
        }
        $rest = $data->d ?? null;
        $week = $data->w ?? null;
        return [
            'para' => value::text($data->para ?? null, self::NAME),
            'de' => value::text($data->de ?? null, self::NAME),
            'd' => is_int($rest) && in_array($rest, self::RESTS, true) ? $rest : 45,
            'x' => value::integer($data->x ?? null, 1, 3, 1),
            // Days a week are kept only when they are a number from 1 to 7: none means the professional leaves it free.
            'w' => is_int($week) && $week >= 1 && $week <= 7 ? $week : null,
            'm' => value::integer($data->m ?? null, 0, 9, null),
            'c' => ($data->c ?? null) === 'b' ? 'b' : 'a',
            'f' => value::is_day($data->f ?? null) ? $data->f : null,
            'e' => $exercises,
        ];
    }

    /**
     * What a teacher pasted, checked: the code to keep, what is wrong with it and what the site cannot play.
     *
     * @param string $input A link or a code.
     * @return array With code, error (a string id or null), unavailable (names) and unknown (ids).
     */
    public static function check(string $input): array {
        $result = ['code' => self::code_from($input), 'error' => null, 'unavailable' => [], 'unknown' => []];
        $routine = $result['code'] === '' ? null : self::parse($result['code']);
        if ($result['code'] === '') {
            $result['error'] = 'routineempty';
        } else if ($routine === null) {
            $result['error'] = 'routineinvalid';
        } else if (!catalog::exists()) {
            $result['error'] = 'noplayer';
        } else {
            $known = 0;
            foreach ($routine['e'] as $exercise) {
                $item = catalog::get($exercise['i']);
                if (!$item) {
                    $result['unknown'][] = $exercise['i'];
                    continue;
                }
                $known++;
                if (!$item['available']) {
                    $result['unavailable'][] = $item['name'];
                }
            }
            if (!$known) {
                $result['error'] = 'routinenoexercises';
            }
        }
        $result['unavailable'] = array_values(array_unique($result['unavailable']));
        $result['unknown'] = array_values(array_unique($result['unknown']));
        return $result;
    }

    /**
     * What a teacher should know of a routine the site can only play in part.
     *
     * @param array $check As check() returns it.
     * @return string[]
     */
    public static function warnings(array $check): array {
        $warnings = [];
        if ($check['unavailable']) {
            $warnings[] = get_string('routineunavailable', 'mod_oksigeniajepyso', implode(', ', $check['unavailable']));
        }
        if ($check['unknown']) {
            $warnings[] = get_string('routineunknown', 'mod_oksigeniajepyso', implode(', ', $check['unknown']));
        }
        return $warnings;
    }

    /**
     * What a teacher should know of the routine kept in an activity: the player missing, or what it cannot play.
     *
     * @param string $code The code kept.
     * @return string[]
     */
    public static function warnings_for(string $code): array {
        $check = self::check($code);
        if ($check['error'] === 'noplayer') {
            return [get_string('noplayer', 'mod_oksigeniajepyso')];
        }
        return $check['error'] === null ? self::warnings($check) : [];
    }

    /**
     * The acceptable discomfort of a routine, out of 10.
     *
     * @param array|null $routine As parse() returns it.
     * @return int
     */
    public static function limit(?array $routine): int {
        return $routine['m'] ?? self::LIMIT;
    }

    /**
     * What a page shows of a routine: its exercises with their dose, and how it is done.
     *
     * @param array $routine As parse() returns it.
     * @return array The data for the templates.
     */
    public static function describe(array $routine): array {
        $exercises = [];
        foreach ($routine['e'] as $exercise) {
            $item = catalog::get($exercise['i']);
            if (!$item) {
                continue;
            }
            $poster = catalog::poster($exercise['i'], $routine['c']);
            $exercises[] = [
                'number' => count($exercises) + 1,
                'id' => $exercise['i'],
                'name' => $item['name'],
                'poster' => $poster ? $poster->out(false) : '',
                'hasposter' => (bool) $poster,
                // The picture shows the right side; the left one is its mirror, as in the app.
                'mirrored' => $exercise['l'] === 'izq',
                'dosage' => self::dosage($exercise, $item['unit']),
                'side' => $item['oneside'] && $exercise['l'] ? get_string('side' . $exercise['l'], 'mod_oksigeniajepyso') : '',
                'band' => $exercise['b'] ? get_string('band' . $exercise['b'], 'mod_oksigeniajepyso') : '',
                'note' => $exercise['n'] ?? '',
                'available' => $item['available'],
            ];
        }
        return [
            'exercises' => $exercises,
            'hasexercises' => (bool) $exercises,
            'rest' => get_string('rest', 'mod_oksigeniajepyso', $routine['d']),
            'times' => $routine['x'] > 1 ? get_string('timesaday', 'mod_oksigeniajepyso', $routine['x']) : '',
            'days' => $routine['w'] ? get_string('daysaweek', 'mod_oksigeniajepyso', $routine['w']) : '',
            'limit' => get_string('limit', 'mod_oksigeniajepyso', self::limit($routine)),
        ];
    }

    /**
     * The dose of an exercise in words: «2 sets of 8 · out 2 s, hold 1 s, back 2 s».
     *
     * @param array $exercise An exercise as parse() returns it.
     * @param string $unit What one repetition is (see catalog::UNITS).
     * @return string
     */
    public static function dosage(array $exercise, string $unit): string {
        $amount = self::amount($exercise['r'], $unit);
        $parts = [$exercise['s'] === 1
            ? get_string('setsone', 'mod_oksigeniajepyso', $amount)
            : get_string('sets', 'mod_oksigeniajepyso', (object) ['sets' => $exercise['s'], 'amount' => $amount])];
        if ($unit === 'reps') {
            [$up, $hold, $down] = $exercise['t'];
            $parts[] = get_string($hold ? 'tempohold' : 'tempo', 'mod_oksigeniajepyso', (object) [
                'up' => $up,
                'hold' => $hold,
                'down' => $down,
            ]);
        }
        return implode(' · ', $parts);
    }

    /**
     * How much one set is, in its unit: «8», «30 s», «10 steps», «5 min», «3 laps».
     *
     * @param int $count The repetitions.
     * @param string $unit What one repetition is.
     * @return string
     */
    public static function amount(int $count, string $unit): string {
        switch ($unit) {
            case 'segundos':
                return get_string('amountseconds', 'mod_oksigeniajepyso', $count);
            case 'pasos':
                return $count === 1 ? get_string('amountstepsone', 'mod_oksigeniajepyso')
                    : get_string('amountsteps', 'mod_oksigeniajepyso', $count);
            case 'minutos':
                return get_string('amountminutes', 'mod_oksigeniajepyso', $count);
            case 'vueltas':
                return $count === 1 ? get_string('amountlapsone', 'mod_oksigeniajepyso')
                    : get_string('amountlaps', 'mod_oksigeniajepyso', $count);
            default:
                return (string) $count;
        }
    }

    /**
     * The tempo of an exercise, as the app cleans it: up to 12 s out and back, up to 30 s held.
     *
     * @param mixed $value The tempo given, [out, hold, back].
     * @return int[]
     */
    private static function tempo($value): array {
        $tempo = is_array($value) ? array_values($value) : [];
        return [
            value::integer($tempo[0] ?? null, 1, 12, 2),
            value::integer($tempo[1] ?? null, 0, 30, 1),
            value::integer($tempo[2] ?? null, 1, 12, 2),
        ];
    }
}
