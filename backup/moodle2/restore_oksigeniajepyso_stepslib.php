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
 * The restore step of an activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The structure of an activity in a restore: its settings and, with users, the sessions of its students.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_oksigeniajepyso_activity_structure_step extends restore_activity_structure_step {
    /**
     * The paths to read.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [new restore_path_element('oksigeniajepyso', '/activity/oksigeniajepyso')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element(
                'oksigeniajepyso_session',
                '/activity/oksigeniajepyso/jepysosessions/jepysosession'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity.
     *
     * @param array $data The activity, from the backup.
     */
    protected function process_oksigeniajepyso($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        if ($data->grade < 0) {
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }
        $newid = $DB->insert_record('oksigeniajepyso', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restores a session of a student.
     *
     * @param array $data The session, from the backup.
     */
    protected function process_oksigeniajepyso_session($data) {
        global $DB;
        $data = (object) $data;
        $data->jepysoid = $this->get_new_parentid('oksigeniajepyso');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->hm = (string) ($data->hm ?? '');
        $key = ['jepysoid' => $data->jepysoid, 'userid' => $data->userid, 'day' => $data->day, 'hm' => $data->hm];
        if (!$DB->record_exists('oksigeniajepyso_session', $key)) {
            $DB->insert_record('oksigeniajepyso_session', $data);
        }
    }

    /**
     * Restores the files of the description.
     */
    protected function after_execute() {
        $this->add_related_files('mod_oksigeniajepyso', 'intro', null);
    }
}
