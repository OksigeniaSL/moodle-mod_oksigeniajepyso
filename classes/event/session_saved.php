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

namespace mod_oksigeniajepyso\event;

/**
 * A student saved sessions done with the routine of an activity.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int saved: the records new or changed.
 * }
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_saved extends \core\event\base {
    /**
     * The data of the event.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'oksigeniajepyso';
    }

    /**
     * The name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsessionsaved', 'mod_oksigeniajepyso');
    }

    /**
     * What happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' saved {$this->other['saved']} sessions of the activity with course module id " .
            "'$this->contextinstanceid'.";
    }

    /**
     * Where it can be seen.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Checks the data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['saved'])) {
            throw new \coding_exception('The \'saved\' value must be set in other.');
        }
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * Where the activity goes when restored.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'oksigeniajepyso', 'restore' => 'oksigeniajepyso'];
    }

    /**
     * The other values need no mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
