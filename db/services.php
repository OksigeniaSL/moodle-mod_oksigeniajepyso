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
 * Web services of mod_oksigeniajepyso: what the player calls, in the browser and in the Moodle app.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_oksigeniajepyso_save_sessions' => [
        'classname' => \mod_oksigeniajepyso\external\save_sessions::class,
        'description' => 'Keeps the sessions the user did with the routine of an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/oksigeniajepyso:submit',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_oksigeniajepyso_get_sessions' => [
        'classname' => \mod_oksigeniajepyso\external\get_sessions::class,
        'description' => 'The sessions the user keeps in an activity.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/oksigeniajepyso:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
