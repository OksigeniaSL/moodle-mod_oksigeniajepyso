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

namespace mod_oksigeniajepyso\completion;

use core_completion\activity_custom_completion;
use mod_oksigeniajepyso\local\sessions;

/**
 * The completion rule of the activity: the student has done the sessions asked for.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * The state of a rule for the user.
     *
     * @param string $rule The rule.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $target = (int) $DB->get_field('oksigeniajepyso', 'sessions', ['id' => $this->cm->instance], MUST_EXIST);
        $done = sessions::done((int) $this->cm->instance, $this->userid);
        return $target > 0 && $done >= $target ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The rules of the activity.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsessions'];
    }

    /**
     * What each rule asks for, in words.
     *
     * @return string[]
     */
    public function get_custom_rule_descriptions(): array {
        $target = (int) ($this->cm->customdata['sessions'] ?? 0);
        return [
            'completionsessions' => $target === 1
                ? get_string('completiondetail:sessionsone', 'mod_oksigeniajepyso')
                : get_string('completiondetail:sessions', 'mod_oksigeniajepyso', $target),
        ];
    }

    /**
     * The order the rules are shown in.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionsessions', 'completionusegrade', 'completionpassgrade'];
    }
}
