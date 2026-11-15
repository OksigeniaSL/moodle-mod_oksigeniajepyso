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
 * The backup step of an activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The structure of an activity in a backup: its settings and, with users, the sessions of its students.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_oksigeniajepyso_activity_structure_step extends backup_activity_structure_step {
    /**
     * The structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $jepyso = new backup_nested_element('oksigeniajepyso', ['id'], [
            'name', 'intro', 'introformat', 'routine', 'sessions', 'completionsessions', 'grade', 'timecreated', 'timemodified',
        ]);
        $records = new backup_nested_element('jepysosessions');
        $record = new backup_nested_element('jepysosession', ['id'], [
            'userid', 'day', 'hm', 'data', 'done', 'flagged', 'timecreated', 'timemodified',
        ]);
        $jepyso->add_child($records);
        $records->add_child($record);

        $jepyso->set_source_table('oksigeniajepyso', ['id' => backup::VAR_ACTIVITYID]);
        if ($this->get_setting_value('userinfo')) {
            $record->set_source_table('oksigeniajepyso_session', ['jepysoid' => backup::VAR_PARENTID], 'id ASC');
        }
        $record->annotate_ids('user', 'userid');
        $jepyso->annotate_files('mod_oksigeniajepyso', 'intro', null);

        return $this->prepare_activity_structure($jepyso);
    }
}
