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

namespace mod_oksigeniajepyso\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The personal data of mod_oksigeniajepyso: the sessions each student keeps. Nothing leaves the site: the player is
 * served by the site and saves through its web services.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string The table of the sessions. */
    private const TABLE = 'oksigeniajepyso_session';

    /**
     * What is kept.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $prefix = 'privacy:metadata:' . self::TABLE;
        $collection->add_database_table(self::TABLE, [
            'userid' => $prefix . ':userid',
            'day' => $prefix . ':day',
            'hm' => $prefix . ':hm',
            'data' => $prefix . ':data',
            'done' => $prefix . ':done',
            'flagged' => $prefix . ':flagged',
            'timecreated' => $prefix . ':timecreated',
            'timemodified' => $prefix . ':timemodified',
        ], $prefix);
        return $collection;
    }

    /**
     * The activities where a user keeps sessions.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT DISTINCT ctx.id
               FROM {context} ctx
               JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {" . self::TABLE . "} s ON s.jepysoid = cm.instance
              WHERE s.userid = :userid",
            ['contextlevel' => CONTEXT_MODULE, 'modname' => 'oksigeniajepyso', 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * The users who keep sessions in an activity.
     *
     * @param userlist $userlist The list to add to.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT s.userid
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {" . self::TABLE . "} s ON s.jepysoid = cm.instance
              WHERE cm.id = :cmid",
            ['modname' => 'oksigeniajepyso', 'cmid' => $context->instanceid]
        );
    }

    /**
     * Exports the sessions of a user, one activity at a time.
     *
     * @param approved_contextlist $contextlist The activities.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $jepysoid = self::instance_of($context);
            if (!$jepysoid) {
                continue;
            }
            $records = $DB->get_records(
                self::TABLE,
                ['jepysoid' => $jepysoid, 'userid' => $user->id],
                'day ASC, hm ASC'
            );
            if (!$records) {
                continue;
            }
            $sessions = [];
            foreach ($records as $record) {
                $sessions[] = (object) [
                    'day' => $record->day,
                    'time' => $record->hm,
                    'done' => transform::yesno($record->done),
                    'abovelimit' => transform::yesno($record->flagged),
                    'record' => json_decode($record->data),
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }
            $data = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $data);
            writer::with_context($context)->export_data(
                [get_string('privacy:sessions', 'mod_oksigeniajepyso')],
                (object) ['sessions' => $sessions]
            );
        }
    }

    /**
     * Deletes the sessions of everyone in an activity.
     *
     * @param \context $context The activity.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $jepysoid = self::instance_of($context);
        if ($jepysoid) {
            $DB->delete_records(self::TABLE, ['jepysoid' => $jepysoid]);
        }
    }

    /**
     * Deletes the sessions of a user in some activities.
     *
     * @param approved_contextlist $contextlist The activities and the user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $jepysoid = self::instance_of($context);
            if ($jepysoid) {
                $DB->delete_records(self::TABLE, ['jepysoid' => $jepysoid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Deletes the sessions of some users in an activity.
     *
     * @param approved_userlist $userlist The activity and the users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $jepysoid = self::instance_of($userlist->get_context());
        $userids = $userlist->get_userids();
        if (!$jepysoid || !$userids) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['jepysoid'] = $jepysoid;
        $DB->delete_records_select(self::TABLE, "jepysoid = :jepysoid AND userid $in", $params);
    }

    /**
     * The activity of a context, if it is one of this plugin.
     *
     * @param \context $context The context.
     * @return int The id of the activity, or 0.
     */
    private static function instance_of(\context $context): int {
        if (!$context instanceof \context_module) {
            return 0;
        }
        $cm = get_coursemodule_from_id('oksigeniajepyso', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }
}
