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

namespace mod_oksigeniajepyso\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use mod_oksigeniajepyso\local\routine;

/**
 * The page of the player and of the builder: a document of its own with the app of Jepyso in it, from the template
 * mod_oksigeniajepyso/player. Without the theme nor its scripts: it loads and calls nothing outside the site.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player_page implements renderable, templatable {
    /** @var \stdClass|null The activity, or null in the builder. */
    private $jepyso;

    /** @var \cm_info|null Its course module, or null in the builder. */
    private $cm;

    /**
     * A page.
     *
     * @param \stdClass|null $jepyso The activity, or null for the builder.
     * @param \cm_info|null $cm Its course module, or null for the builder.
     */
    private function __construct(?\stdClass $jepyso, ?\cm_info $cm) {
        $this->jepyso = $jepyso;
        $this->cm = $cm;
    }

    /**
     * The player of an activity, for the user who is logged in.
     *
     * @param \stdClass $jepyso The activity.
     * @param \cm_info $cm Its course module.
     * @return self
     */
    public static function for_activity(\stdClass $jepyso, \cm_info $cm): self {
        return new self($jepyso, $cm);
    }

    /**
     * The builder of routines.
     *
     * @return self
     */
    public static function for_builder(): self {
        return new self(null, null);
    }

    /**
     * What the template paints.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $USER;
        $data = [
            'lang' => get_html_lang_attribute_value(current_language()),
            'title' => get_string('builder', 'mod_oksigeniajepyso'),
            'base' => (new \moodle_url('/mod/oksigeniajepyso/reproductor'))->out(false),
            'version' => (string) get_config('mod_oksigeniajepyso', 'version'),
            // The player speaks the language of this user in Moodle (it maps any Moodle language to Spanish or
            // English), unless the person chose another one inside the player.
            'userlang' => current_language(),
            'cmid' => '',
            'code' => '',
            'sesskey' => '',
            'service' => '',
            'backurl' => '',
            'firstname' => '',
            'cansubmit' => false,
        ];
        if (!$this->cm) {
            return $data;
        }
        $context = \context_module::instance($this->cm->id);
        return array_merge($data, [
            'title' => view_data::plain($this->jepyso->name, $context),
            'cmid' => (string) $this->cm->id,
            // The code was checked when the activity was saved, and only has letters, digits, «.», «-» and «_».
            'code' => routine::code_from((string) $this->jepyso->routine),
            'sesskey' => sesskey(),
            'service' => $CFG->wwwroot . '/lib/ajax/service.php',
            'backurl' => (new \moodle_url('/mod/oksigeniajepyso/view.php', ['id' => $this->cm->id]))->out(false),
            'firstname' => isguestuser() ? '' : $USER->firstname,
            'cansubmit' => has_capability('mod/oksigeniajepyso:submit', $context, null, false),
        ]);
    }

    /**
     * Sends the page. Its address is not kept by the browser: it carries the session of who asked for it.
     */
    public function show(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        send_headers('text/html; charset=utf-8', false);
        echo $output->render_from_template('mod_oksigeniajepyso/player', $this->export_for_template($output));
    }
}
