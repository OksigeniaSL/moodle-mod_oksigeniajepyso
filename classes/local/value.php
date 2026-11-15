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
 * The checks the app makes on what comes from outside (app/entrada.js of Jepyso), in PHP: each value passes with its
 * type and range, or not at all.
 *
 * JSON is decoded into objects, so that an object and a list stay apart as they do in JavaScript.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class value {
    /** @var string[] The sides an exercise can be done on. */
    public const SIDES = ['der', 'izq', 'ambos'];

    /**
     * An integer within its range, or the default when it is no integer (as Number.isInteger: 2.0 is one, «2» is not).
     *
     * @param mixed $value The value.
     * @param int $min The lowest it can be.
     * @param int $max The highest it can be.
     * @param int|null $default What it is when it is no integer.
     * @return int|null
     */
    public static function integer($value, int $min, int $max, ?int $default): ?int {
        if (is_float($value) && is_finite($value) && floor($value) == $value) {
            return (int) max($min, min($max, $value));
        }
        if (!is_int($value)) {
            return $default;
        }
        return max($min, min($max, $value));
    }

    /**
     * A text cut to its length in characters, or an empty one when it is no text.
     *
     * @param mixed $value The value.
     * @param int $max The most characters it can have.
     * @return string
     */
    public static function text($value, int $max): string {
        return is_string($value) ? \core_text::substr($value, 0, $max) : '';
    }

    /**
     * A text without white space at either end, Unicode spaces too, as String.prototype.trim().
     *
     * @param string $text The text.
     * @return string
     */
    public static function trim(string $text): string {
        return (string) preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $text);
    }

    /**
     * Whether a value is a JSON object (not a list).
     *
     * @param mixed $value The value.
     * @return bool
     */
    public static function is_object($value): bool {
        return $value instanceof \stdClass;
    }

    /**
     * A list cut to its length, or an empty one when it is no list.
     *
     * @param mixed $value The value.
     * @param int $max The most items it can have.
     * @return array
     */
    public static function items($value, int $max): array {
        return is_array($value) ? array_slice(array_values($value), 0, $max) : [];
    }

    /**
     * Whether a value is a calendar day, YYYY-MM-DD, that exists.
     *
     * @param mixed $value The value.
     * @return bool
     */
    public static function is_day($value): bool {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return false;
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * Whether a value is a time of the day, HH:MM.
     *
     * @param mixed $value The value.
     * @return bool
     */
    public static function is_time($value): bool {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
    }

    /**
     * Whether a value is a moment as the app writes it (an ISO 8601 date and time, as toISOString()), in 40 characters
     * at most. Moments are compared as texts, so only this form makes sense.
     *
     * @param mixed $value The value.
     * @return bool
     */
    public static function is_moment($value): bool {
        if (!is_string($value) || strlen($value) > 40) {
            return false;
        }
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})(?:T([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,9})?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/';
        if (!preg_match($pattern, $value, $parts)) {
            return false;
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * The side of an exercise: the one given if it is one, else the right one for an exercise done on one side, else
     * none.
     *
     * @param mixed $value The value.
     * @param string $id The exercise id.
     * @return string|null
     */
    public static function side($value, string $id): ?string {
        if (is_string($value) && in_array($value, self::SIDES, true)) {
            return $value;
        }
        $exercise = catalog::get($id);
        return $exercise && $exercise['oneside'] ? 'der' : null;
    }
}
