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
 * The restore task of an activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/oksigeniajepyso/backup/moodle2/restore_oksigeniajepyso_stepslib.php');

/**
 * The restore task of an activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_oksigeniajepyso_activity_task extends restore_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * The steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_oksigeniajepyso_activity_structure_step('oksigeniajepyso_structure', 'oksigeniajepyso.xml'));
    }

    /**
     * The texts whose links are decoded.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('oksigeniajepyso', ['intro'], 'oksigeniajepyso')];
    }

    /**
     * How the links are decoded.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('OKSIGENIAJEPYSOVIEWBYID', '/mod/oksigeniajepyso/view.php?id=$1', 'course_module'),
            new restore_decode_rule('OKSIGENIAJEPYSOINDEX', '/mod/oksigeniajepyso/index.php?id=$1', 'course'),
        ];
    }

    /**
     * The rules for the logs of the activity.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('oksigeniajepyso', 'view', 'view.php?id={course_module}', '{oksigeniajepyso}'),
        ];
    }

    /**
     * The rules for the logs of the course.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('oksigeniajepyso', 'view all', 'index.php?id={course}', null),
        ];
    }
}
