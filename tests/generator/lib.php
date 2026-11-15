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
 * Data generator of mod_oksigeniajepyso.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_oksigeniajepyso_generator extends testing_module_generator {
    /** @var string A routine of three exercises (silla, puente and hombro on the right), compressed. */
    public const ROUTINE = 'd.bc3BCoJAFIXhV4l_fQVnTIn7DO1aigvLiYRRY9QKxHcPlSCo3Vl8h3_igRrhXoYSBaFyKMex7peN7lPhtZInmggNmgoXlBLhim'
        . 'Jjm0XGRCZFcGg-UaP0tfcL6VErBPQgDGhuxYgtBI-2o_ezbPw-unZwm09Wb-LPwf473LrmHLrvQPYToHIBoUU51e2u6nwXmIv5DQ';

    /**
     * Creates an activity.
     *
     * @param array|stdClass|null $record Its settings: routine, sessions, completionsessions and grade among them.
     * @param array|null $options As for create_module().
     * @return stdClass The activity, with cmid.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;
        $defaults = ['routine' => self::ROUTINE, 'sessions' => 0, 'completionsessions' => 0, 'grade' => 0];
        foreach ($defaults as $name => $value) {
            if (!isset($record->$name)) {
                $record->$name = $value;
            }
        }
        return parent::create_instance($record, (array) $options);
    }
}
