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

namespace mod_oksigeniajepyso\local;

/**
 * The sessions the students keep in an activity: one record per student, day and time.
 *
 * The player sends again what it already sent (the last days, a corrected one): a record replaces the one kept for the
 * same day and time unless the kept one was corrected later, and an answer about the next morning is never lost.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions {
    /** @var int The most records one call can bring. */
    public const RECORDS = 400;

    /** @var int The most bytes one call can bring. */
    public const BYTES = 524288;

    /** @var string The table. */
    private const TABLE = 'oksigeniajepyso_session';

    /**
     * The records of a call, decoded: a JSON list, within the limits.
     *
     * @param string $json What the player sent.
     * @return array The records, as objects, not cleaned yet.
     * @throws \invalid_parameter_exception
     */
    public static function decode_list(string $json): array {
        if (strlen($json) > self::BYTES) {
            throw new \invalid_parameter_exception('The sessions take more than ' . self::BYTES . ' bytes');
        }
        $list = json_decode($json, false, 64);
        if (!is_array($list)) {
            throw new \invalid_parameter_exception('The sessions are not a JSON list');
        }
        if (count($list) > self::RECORDS) {
            throw new \invalid_parameter_exception('More than ' . self::RECORDS . ' sessions in one call');
        }
        return $list;
    }

    /**
     * Keeps the sessions a student sent, and then brings completion and grade up to date.
     *
     * @param \stdClass $jepyso The activity.
     * @param \cm_info $cm Its course module.
     * @param \stdClass $course Its course.
     * @param int $userid The student.
     * @param array $records The records, as decode_list() gives them.
     * @return array With saved (records new or changed), done, target and completed.
     */
    public static function save(\stdClass $jepyso, \cm_info $cm, \stdClass $course, int $userid, array $records): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/oksigeniajepyso/lib.php');

        $limit = routine::limit(routine::parse((string) $jepyso->routine));
        $saved = 0;
        foreach ($records as $record) {
            $session = session_cleaner::clean($record);
            if ($session !== null && self::upsert((int) $jepyso->id, $userid, $session, $limit)) {
                $saved++;
            }
        }

        if ($saved) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && !empty($jepyso->completionsessions)) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
            }
            oksigeniajepyso_update_grades($jepyso, $userid);
            \mod_oksigeniajepyso\event\session_saved::create([
                'objectid' => $jepyso->id,
                'context' => \context_module::instance($cm->id),
                'userid' => $userid,
                'other' => ['saved' => $saved],
            ])->trigger();
        }

        $done = self::done((int) $jepyso->id, $userid);
        $target = (int) $jepyso->sessions;
        return ['saved' => $saved, 'done' => $done, 'target' => $target, 'completed' => $target > 0 && $done >= $target];
    }

    /**
     * Keeps one cleaned session: a new one, or in place of the one kept for the same day and time.
     *
     * @param int $jepysoid The activity.
     * @param int $userid The student.
     * @param array $session The session, cleaned.
     * @param int $limit The acceptable discomfort of the routine.
     * @return bool Whether something was written.
     */
    public static function upsert(int $jepysoid, int $userid, array $session, int $limit): bool {
        global $DB;
        $key = ['jepysoid' => $jepysoid, 'userid' => $userid, 'day' => $session['f'], 'hm' => $session['h'] ?? ''];
        $stored = $DB->get_record(self::TABLE, $key);
        if ($stored) {
            $kept = json_decode($stored->data, true);
            $kept = is_array($kept) ? $kept : [];
            if (strcmp($session['ed'] ?? '', $kept['ed'] ?? '') < 0) {
                // An older copy of a day corrected since: it can only bring the answer about the next morning.
                if (isset($kept['a']) || !isset($session['a'])) {
                    return false;
                }
                $kept['a'] = $session['a'];
                $session = $kept;
            } else if (!isset($session['a']) && isset($kept['a'])) {
                $session['a'] = $kept['a'];
            }
        }

        $record = (object) $key;
        $record->data = json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->done = (int) session_cleaner::is_done($session);
        $record->flagged = (int) session_cleaner::is_flagged($session, $limit);
        $record->timemodified = time();
        if ($stored) {
            if ($stored->data === $record->data && (int) $stored->flagged === $record->flagged) {
                return false;
            }
            $record->id = $stored->id;
            $DB->update_record(self::TABLE, $record);
            return true;
        }
        $record->timecreated = $record->timemodified;
        try {
            $DB->insert_record(self::TABLE, $record);
        } catch (\dml_write_exception $e) {
            // The same day came in through another call at the same moment: this one goes over it.
            $record->id = $DB->get_field(self::TABLE, 'id', $key, MUST_EXIST);
            $DB->update_record(self::TABLE, $record);
        }
        return true;
    }

    /**
     * The sessions done by a student.
     *
     * @param int $jepysoid The activity.
     * @param int $userid The student.
     * @return int
     */
    public static function done(int $jepysoid, int $userid): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['jepysoid' => $jepysoid, 'userid' => $userid, 'done' => 1]);
    }

    /**
     * The day of the last session a student did.
     *
     * @param int $jepysoid The activity.
     * @param int $userid The student.
     * @return string|null A day, YYYY-MM-DD, or null if none was done.
     */
    public static function last_day(int $jepysoid, int $userid): ?string {
        global $DB;
        $day = $DB->get_field_sql(
            'SELECT MAX(day) FROM {' . self::TABLE . '} WHERE jepysoid = :jepysoid AND userid = :userid AND done = 1',
            ['jepysoid' => $jepysoid, 'userid' => $userid]
        );
        return $day ? (string) $day : null;
    }

    /**
     * The records of a student, oldest first.
     *
     * @param int $jepysoid The activity.
     * @param int $userid The student.
     * @return \stdClass[] Each with day, hm, done, flagged, timemodified and session (the record, as an array).
     */
    public static function of_user(int $jepysoid, int $userid): array {
        global $DB;
        $rows = $DB->get_records(
            self::TABLE,
            ['jepysoid' => $jepysoid, 'userid' => $userid],
            'day ASC, hm ASC',
            'id, day, hm, data, done, flagged, timemodified'
        );
        foreach ($rows as $row) {
            $session = json_decode($row->data, true);
            $row->session = is_array($session) ? $session : ['f' => $row->day, 'e' => []];
        }
        return array_values($rows);
    }

    /**
     * The records of a student as the player keeps them: a JSON list of objects, oldest first.
     *
     * @param int $jepysoid The activity.
     * @param int $userid The student.
     * @return array The records, decoded into objects.
     */
    public static function for_player(int $jepysoid, int $userid): array {
        global $DB;
        $rows = $DB->get_records(self::TABLE, ['jepysoid' => $jepysoid, 'userid' => $userid], 'day ASC, hm ASC', 'id, data');
        $records = [];
        foreach ($rows as $row) {
            $record = json_decode($row->data);
            if ($record instanceof \stdClass) {
                $records[] = $record;
            }
        }
        return $records;
    }

    /**
     * What the follow-up shows of each student: sessions done, the last one, days over the acceptable discomfort,
     * exercises found hard or not done, and notes.
     *
     * @param int $jepysoid The activity.
     * @param int[] $userids The students.
     * @return array[] By user id, each with done, last (a day or null), flagged, hard, couldnot and notes.
     */
    public static function summaries(int $jepysoid, array $userids): array {
        global $DB;
        $summaries = [];
        foreach ($userids as $userid) {
            $summaries[$userid] = ['done' => 0, 'last' => null, 'flagged' => 0, 'hard' => 0, 'couldnot' => 0, 'notes' => 0];
        }
        if (!$userids) {
            return $summaries;
        }
        [$in, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED);
        $params['jepysoid'] = $jepysoid;
        $flaggeddays = [];
        $rows = $DB->get_recordset_select(
            self::TABLE,
            "jepysoid = :jepysoid AND userid $in",
            $params,
            'day ASC, hm ASC',
            'id, userid, day, data, done, flagged'
        );
        foreach ($rows as $row) {
            $summary = &$summaries[$row->userid];
            if ($row->done) {
                $summary['done']++;
                $summary['last'] = $row->day;
            }
            if ($row->flagged) {
                $flaggeddays[$row->userid][$row->day] = true;
            }
            $session = json_decode($row->data, true);
            foreach (is_array($session) ? ($session['e'] ?? []) : [] as $exercise) {
                $summary['hard'] += (int) (($exercise['c'] ?? '') === 'cuesta');
                $summary['couldnot'] += (int) (($exercise['c'] ?? '') === 'no');
                $summary['notes'] += (int) isset($exercise['n']);
            }
            unset($summary);
        }
        $rows->close();
        foreach ($flaggeddays as $userid => $days) {
            $summaries[$userid]['flagged'] = count($days);
        }
        return $summaries;
    }

    /**
     * Marks again the sessions over the acceptable discomfort, after the routine changed.
     *
     * @param \stdClass $jepyso The activity, with its new routine.
     */
    public static function reflag(\stdClass $jepyso): void {
        global $DB;
        $limit = routine::limit(routine::parse((string) $jepyso->routine));
        $rows = $DB->get_recordset(self::TABLE, ['jepysoid' => $jepyso->id], '', 'id, data, flagged');
        foreach ($rows as $row) {
            $session = json_decode($row->data, true);
            $flagged = (int) (is_array($session) && session_cleaner::is_flagged($session + ['e' => []], $limit));
            if ($flagged !== (int) $row->flagged) {
                $DB->set_field(self::TABLE, 'flagged', $flagged, ['id' => $row->id]);
            }
        }
        $rows->close();
    }
}
