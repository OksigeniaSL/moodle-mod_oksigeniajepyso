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

use mod_oksigeniajepyso\local\catalog;
use mod_oksigeniajepyso\local\routine;
use mod_oksigeniajepyso\local\sessions;

/**
 * What the page of an activity shows, on the web and in the Moodle app: the routine, the progress and the way in.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_data {
    /**
     * The data of the page for a user.
     *
     * @param \stdClass $jepyso The activity.
     * @param \cm_info $cm Its course module.
     * @param int $userid Who sees it.
     * @return array
     */
    public static function for_page(\stdClass $jepyso, \cm_info $cm, int $userid): array {
        $context = \context_module::instance($cm->id);
        $parsed = routine::parse((string) $jepyso->routine);
        $routine = $parsed && catalog::exists() ? routine::describe($parsed) : null;
        $cansubmit = has_capability('mod/oksigeniajepyso:submit', $context, $userid, false);
        $canplay = $routine && $routine['hasexercises'];

        $data = [
            'cmid' => $cm->id,
            'hasroutine' => (bool) $canplay,
            'routine' => $canplay ? $routine : null,
            'noroutine' => get_string('noroutine', 'mod_oksigeniajepyso'),
            'progress' => '',
            'last' => '',
            'starturl' => (new \moodle_url('/mod/oksigeniajepyso/player.php', ['id' => $cm->id]))->out(false),
            'startlabel' => get_string($cansubmit ? 'start' : 'preview', 'mod_oksigeniajepyso'),
            'previewnote' => $cansubmit ? '' : get_string('previewnote', 'mod_oksigeniajepyso'),
            'reporturl' => '',
            'reportlabel' => get_string('viewfollowup', 'mod_oksigeniajepyso'),
        ];
        if ($cansubmit) {
            $data['progress'] = self::progress(sessions::done((int) $jepyso->id, $userid), (int) $jepyso->sessions);
            $last = sessions::last_day((int) $jepyso->id, $userid);
            if ($last) {
                $data['last'] = get_string('lastsession', 'mod_oksigeniajepyso', self::day($last));
            }
        }
        if (has_capability('mod/oksigeniajepyso:viewreports', $context, $userid)) {
            $data['reporturl'] = (new \moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id]))->out(false);
        }
        return $data;
    }

    /**
     * How far a student has gone: «You have done 2 of 6 sessions».
     *
     * @param int $done The sessions done.
     * @param int $target The sessions asked for, or 0.
     * @return string
     */
    public static function progress(int $done, int $target): string {
        $component = 'mod_oksigeniajepyso';
        if ($target === 1) {
            return get_string('progressone', $component, $done);
        }
        if ($target > 0) {
            return get_string('progress', $component, (object) ['done' => $done, 'target' => $target]);
        }
        if ($done === 0) {
            return get_string('progressnone', $component);
        }
        return $done === 1 ? get_string('progressnotargetone', $component) : get_string('progressnotarget', $component, $done);
    }

    /**
     * A name as plain text, filtered, for a script to show as text.
     *
     * @param string $text The name.
     * @param \context $context Where it belongs.
     * @return string
     */
    public static function plain(string $text, \context $context): string {
        return html_entity_decode(
            strip_tags(format_string($text, true, ['context' => $context])),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    /**
     * A day in words, in the language and time zone of the user: «15 November 2026».
     *
     * @param string $day A day, YYYY-MM-DD.
     * @return string
     */
    public static function day(string $day): string {
        [$year, $month, $date] = array_map('intval', explode('-', $day));
        return userdate(make_timestamp($year, $month, $date, 12), get_string('strftimedate', 'langconfig'));
    }
}
