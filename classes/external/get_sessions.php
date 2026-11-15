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
 * The sessions the user who calls keeps in an activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_sessions extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module of the activity'),
        ]);
    }

    /**
     * The sessions of the user who calls, oldest first.
     *
     * @param int $cmid The course module of the activity.
     * @return array With sessions, a JSON list.
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;
        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [, $cm] = get_course_and_cm_from_cmid($cmid, 'oksigeniajepyso');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/oksigeniajepyso:view', $context);
        $jepysoid = (int) $DB->get_field('oksigeniajepyso', 'id', ['id' => $cm->instance], MUST_EXIST);
        $records = sessions::for_player($jepysoid, (int) $USER->id);
        return ['sessions' => json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessions' => new external_value(PARAM_RAW, 'The sessions, as a JSON list of the records the player keeps'),
        ]);
    }
}
