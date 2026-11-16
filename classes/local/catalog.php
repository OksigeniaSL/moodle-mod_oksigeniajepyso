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
 * The exercises the player knows, from the catalogue that comes with it (reproductor/catalogo.json).
 *
 * The file is read once per request. Each exercise keeps what the server needs: its name, whether it is done on one
 * side, what a repetition is, and whether it is in the sample that comes with the plugin.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /** @var string What an exercise id looks like. */
    public const ID = '/^[a-z0-9_]{1,40}$/';

    /** @var string[] What one repetition can be. */
    public const UNITS = ['reps', 'segundos', 'pasos', 'minutos', 'vueltas'];

    /** @var string|null Another catalogue to read instead of the player's, for the tests. */
    private static $file = null;

    /** @var array|null The exercises, once read in this request. */
    private static $exercises = null;

    /**
     * Reads another catalogue from now on, or the player's again with null. Meant for the tests.
     *
     * @param string|null $file The path of a catalogue file.
     */
    public static function use_file(?string $file): void {
        self::$file = $file;
        self::$exercises = null;
    }

    /**
     * The folder of the player.
     *
     * @return string
     */
    public static function player_dir(): string {
        return dirname(__DIR__, 2) . '/reproductor';
    }

    /**
     * Whether there is a catalogue to read, that is, whether the player is installed.
     *
     * @return bool
     */
    public static function exists(): bool {
        return (bool) self::exercises();
    }

    /**
     * Every exercise, by id.
     *
     * @return array[] Each with name, oneside, unit, position, material and available.
     */
    public static function exercises(): array {
        if (self::$exercises === null) {
            self::$exercises = self::read(self::$file ?? self::player_dir() . '/catalogo.json');
        }
        return self::$exercises;
    }

    /**
     * One exercise, or null if the catalogue does not have it.
     *
     * @param string $id The exercise id.
     * @return array|null
     */
    public static function get(string $id): ?array {
        return self::exercises()[$id] ?? null;
    }

    /**
     * The ids of the exercises that can be done on this site.
     *
     * @return string[]
     */
    public static function available(): array {
        return array_keys(array_filter(self::exercises(), fn($exercise) => $exercise['available']));
    }

    /**
     * The address of the picture of an exercise, if the player has it: with the band of the routine when there is a
     * picture of it, as the app shows it.
     *
     * @param string $id The exercise id.
     * @param string $figure The figure of the routine, «a» or «b».
     * @param string|null $band The band of the exercise in the routine, or null.
     * @return \moodle_url|null
     */
    public static function poster(string $id, string $figure, ?string $band = null): ?\moodle_url {
        if (!preg_match(self::ID, $id)) {
            return null;
        }
        $folder = $figure === 'b' ? 'modelos/b/posters/' : 'modelos/posters/';
        $names = $band !== null && preg_match('/^[a-z]+$/', $band) ? [$id . '-' . $band, $id] : [$id];
        foreach ($names as $name) {
            $path = $folder . $name . '.webp';
            if (is_readable(self::player_dir() . '/' . $path)) {
                return new \moodle_url('/mod/oksigeniajepyso/reproductor/' . $path);
            }
        }
        return null;
    }

    /**
     * Whether the user reads the exercises in Spanish: the languages of Spain and of Paraguay, as the player decides.
     *
     * @return bool
     */
    private static function spanish(): bool {
        return (bool) preg_match('/^(es|ca|gl|eu|ast|an|gn)(_|$)/', current_language());
    }

    /**
     * The exercises of a catalogue file, or none if it cannot be read.
     *
     * @param string $file The path of the file.
     * @return array[]
     */
    private static function read(string $file): array {
        if (!is_readable($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['ejercicios']) || !is_array($data['ejercicios'])) {
            return [];
        }
        $exercises = [];
        foreach ($data['ejercicios'] as $id => $exercise) {
            $id = (string) $id;
            if (!preg_match(self::ID, $id) || !is_array($exercise)) {
                continue;
            }
            // The name, position and material in the user's language: the catalogue brings them in Spanish and, under
            // «en», in English.
            $en = is_array($exercise['en'] ?? null) && !self::spanish() ? $exercise['en'] : [];
            $text = fn(string $key): string => is_string($en[$key] ?? null) && trim($en[$key]) !== ''
                ? trim($en[$key]) : (is_string($exercise[$key] ?? null) ? trim($exercise[$key]) : '');
            $name = $text('nombre');
            $exercises[$id] = [
                'name' => $name !== '' ? $name : $id,
                'oneside' => ($exercise['unLado'] ?? false) === true,
                'unit' => in_array($exercise['unidad'] ?? '', self::UNITS, true) ? $exercise['unidad'] : 'reps',
                'position' => $text('posicion'),
                'material' => $text('material'),
                // An exercise is in the sample unless the catalogue says it is not.
                'available' => ($exercise['disponible'] ?? true) !== false,
            ];
        }
        return $exercises;
    }
}
