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

namespace mod_oksigeniajepyso\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_oksigeniajepyso\local\sessions;

/**
 * Keeps the sessions a student did with the player: new days, and days corrected since.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_sessions extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
            'sessions' => new external_value(PARAM_RAW, 'The sessions, as a JSON list of the records the player keeps'),
        ]);
    }

    /**
     * Keeps the sessions of the user who calls.
     *
     * @param int $cmid The course module of the activity.
     * @param string $sessions The sessions, as a JSON list.
     * @return array With saved, done, target and completed.
     */
    public static function execute(int $cmid, string $sessions): array {
        global $DB, $USER;
        ['cmid' => $cmid, 'sessions' => $sessions] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'sessions' => $sessions]
        );
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'oksigeniajepyso');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        // Only who does the routine keeps sessions: an administrator or a teacher trying it out does not.
        if (!has_capability('mod/oksigeniajepyso:submit', $context, null, false)) {
            throw new \required_capability_exception($context, 'mod/oksigeniajepyso:submit', 'nopermissions', '');
        }
        $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $cm->instance], '*', MUST_EXIST);
        return sessions::save($jepyso, $cm, $course, (int) $USER->id, sessions::decode_list($sessions));
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_INT, 'Records new or changed'),
            'done' => new external_value(PARAM_INT, 'Sessions done by the user in the activity'),
            'target' => new external_value(PARAM_INT, 'Sessions asked for, 0 for none'),
            'completed' => new external_value(PARAM_BOOL, 'Whether the sessions asked for are done'),
        ]);
    }
}
