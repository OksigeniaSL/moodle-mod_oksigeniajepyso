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
 * The backup task of an activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/oksigeniajepyso/backup/moodle2/backup_oksigeniajepyso_stepslib.php');

/**
 * The backup task of an activity: one step, the activity with the sessions of its students if users are included.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_oksigeniajepyso_activity_task extends backup_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * The steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_oksigeniajepyso_activity_structure_step('oksigeniajepyso_structure', 'oksigeniajepyso.xml'));
    }

    /**
     * Encodes the links to the activities in a text, to restore them anywhere.
     *
     * @param string $content The text.
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot . '/mod/oksigeniajepyso', '#');
        $content = preg_replace('#' . $base . '/index\.php\?id=([0-9]+)#', '$@OKSIGENIAJEPYSOINDEX*$1@$', $content);
        $content = preg_replace('#' . $base . '/view\.php\?id=([0-9]+)#', '$@OKSIGENIAJEPYSOVIEWBYID*$1@$', $content);
        return $content;
    }
}
