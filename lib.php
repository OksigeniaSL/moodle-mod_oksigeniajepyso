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
 * Library of functions of mod_oksigeniajepyso: the activity, its grades and its course reset.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_oksigeniajepyso\local\routine;
use mod_oksigeniajepyso\local\sessions;

/**
 * What the activity supports.
 *
 * @param string $feature A FEATURE_xx constant.
 * @return mixed True or a value if supported, null if the feature is unknown.
 */
function oksigeniajepyso_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            // Not «assessment»: its icon would take the theme's colour for it, and the activity keeps the look of Jepyso
            // (Enrique, 15-11-2026: Oksigenia's plugins do not look like stock Moodle). It still counts for completion
            // and grade.
            return MOD_PURPOSE_OTHER;
        default:
            return null;
    }
}

/**
 * Adds an activity.
 *
 * @param stdClass $data The data of the form.
 * @param mod_oksigeniajepyso_mod_form|null $mform The form.
 * @return int The id of the new activity.
 */
function oksigeniajepyso_add_instance(stdClass $data, $mform = null) {
    global $DB;
    $data->routine = routine::code_from((string) ($data->routine ?? ''));
    $data->sessions = max(0, min(365, (int) ($data->sessions ?? 0)));
    $data->completionsessions = empty($data->completionsessions) ? 0 : 1;
    $data->grade = (int) ($data->grade ?? 0);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('oksigeniajepyso', $data);
    oksigeniajepyso_grade_item_update($data);
    return $data->id;
}

/**
 * Updates an activity.
 *
 * @param stdClass $data The data of the form.
 * @param mod_oksigeniajepyso_mod_form|null $mform The form.
 * @return bool
 */
function oksigeniajepyso_update_instance(stdClass $data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->routine = routine::code_from((string) ($data->routine ?? ''));
    $data->sessions = max(0, min(365, (int) ($data->sessions ?? 0)));
    $data->completionsessions = empty($data->completionsessions) ? 0 : 1;
    $data->grade = (int) ($data->grade ?? 0);
    $data->timemodified = time();
    $before = $DB->get_record('oksigeniajepyso', ['id' => $data->id], 'id, routine', MUST_EXIST);
    $DB->update_record('oksigeniajepyso', $data);
    $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $data->id], '*', MUST_EXIST);
    if (isset($data->cmidnumber)) {
        $jepyso->cmidnumber = $data->cmidnumber;
    }
    if ($before->routine !== $jepyso->routine) {
        // Another routine may accept another discomfort.
        sessions::reflag($jepyso);
    }
    oksigeniajepyso_update_grades($jepyso, 0, false);
    return true;
}

/**
 * Deletes an activity with its sessions and its grade item.
 *
 * @param int $id The id of the activity.
 * @return bool
 */
function oksigeniajepyso_delete_instance($id) {
    global $DB;
    $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $id]);
    if (!$jepyso) {
        return false;
    }
    $DB->delete_records('oksigeniajepyso_session', ['jepysoid' => $jepyso->id]);
    oksigeniajepyso_grade_item_delete($jepyso);
    $DB->delete_records('oksigeniajepyso', ['id' => $jepyso->id]);
    return true;
}

/**
 * What the course page needs of an activity: its name, its description if shown and its completion rules.
 *
 * @param stdClass $coursemodule The course module.
 * @return cached_cm_info|false
 */
function oksigeniajepyso_get_coursemodule_info($coursemodule) {
    global $DB;
    $fields = 'id, name, intro, introformat, sessions, completionsessions';
    $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $coursemodule->instance], $fields);
    if (!$jepyso) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $jepyso->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('oksigeniajepyso', $jepyso, $coursemodule->id, false);
    }
    $info->customdata = ['sessions' => (int) $jepyso->sessions];
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsessions'] = (int) $jepyso->completionsessions;
    }
    return $info;
}

/**
 * Marks the activity as viewed: the event, and completion by view.
 *
 * @param stdClass $jepyso The activity.
 * @param stdClass $course Its course.
 * @param cm_info|stdClass $cm Its course module.
 * @param context_module $context Its context.
 */
function oksigeniajepyso_view($jepyso, $course, $cm, $context) {
    global $CFG, $USER;
    require_once($CFG->libdir . '/completionlib.php');

    $event = \mod_oksigeniajepyso\event\course_module_viewed::create([
        'objectid' => $jepyso->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('oksigeniajepyso', $jepyso);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
    // The number of sessions asked for may have changed since the last one was saved.
    if (
        !empty($jepyso->completionsessions) && $completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
            has_capability('mod/oksigeniajepyso:submit', $context, null, false)
    ) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
    }
}

/**
 * Whether the grade is worked out from the sessions: points, and a number of sessions asked for.
 *
 * @param stdClass $jepyso The activity.
 * @return bool
 */
function oksigeniajepyso_grades_automatically($jepyso) {
    return (int) $jepyso->grade > 0 && (int) $jepyso->sessions > 0;
}

/**
 * Creates or updates the grade item of an activity, and its grades if given.
 *
 * @param stdClass $jepyso The activity, with cmidnumber if it changed.
 * @param array|object|string|null $grades Grades, 'reset' to reset them, or null.
 * @return int A GRADE_UPDATE_xx constant.
 */
function oksigeniajepyso_grade_item_update($jepyso, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $jepyso->name];
    if (isset($jepyso->cmidnumber)) {
        $params['idnumber'] = $jepyso->cmidnumber;
    }
    if ($jepyso->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $jepyso->grade;
        $params['grademin'] = 0;
    } else if ($jepyso->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$jepyso->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/oksigeniajepyso', $jepyso->course, 'mod', 'oksigeniajepyso', $jepyso->id, 0, $grades, $params);
}

/**
 * Deletes the grade item of an activity.
 *
 * @param stdClass $jepyso The activity.
 * @return int A GRADE_UPDATE_xx constant.
 */
function oksigeniajepyso_grade_item_delete($jepyso) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/oksigeniajepyso', $jepyso->course, 'mod', 'oksigeniajepyso', $jepyso->id, 0, null, ['deleted' => 1]);
}

/**
 * The grades worked out from the sessions: the maximum grade times the share of the sessions asked for that are done,
 * never more than all of them.
 *
 * @param stdClass $jepyso The activity.
 * @param int $userid One student, or 0 for all.
 * @return array Grades by user id, each with userid and rawgrade; none if the grade is not worked out from sessions.
 */
function oksigeniajepyso_get_user_grades($jepyso, $userid = 0) {
    global $DB;
    if (!oksigeniajepyso_grades_automatically($jepyso)) {
        return [];
    }
    $params = ['jepysoid' => $jepyso->id];
    $where = 'jepysoid = :jepysoid AND done = 1';
    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $counts = $DB->get_records_sql(
        "SELECT userid, COUNT(1) AS done FROM {oksigeniajepyso_session} WHERE $where GROUP BY userid",
        $params
    );
    $grades = [];
    foreach ($counts as $count) {
        $grades[$count->userid] = (object) [
            'userid' => (int) $count->userid,
            'rawgrade' => $jepyso->grade * min(1, $count->done / $jepyso->sessions),
        ];
    }
    return $grades;
}

/**
 * Brings the grades of an activity up to date in the gradebook.
 *
 * Without automatic grading the item is only updated: the teacher grades in the gradebook.
 *
 * @param stdClass $jepyso The activity.
 * @param int $userid One student, or 0 for all.
 * @param bool $nullifnone Whether a student without sessions gets an empty grade.
 */
function oksigeniajepyso_update_grades($jepyso, $userid = 0, $nullifnone = true) {
    if (!oksigeniajepyso_grades_automatically($jepyso)) {
        oksigeniajepyso_grade_item_update($jepyso);
        return;
    }
    $grades = oksigeniajepyso_get_user_grades($jepyso, $userid);
    if ($grades) {
        oksigeniajepyso_grade_item_update($jepyso, $grades);
    } else if ($userid && $nullifnone) {
        oksigeniajepyso_grade_item_update($jepyso, (object) ['userid' => $userid, 'rawgrade' => null]);
    } else {
        oksigeniajepyso_grade_item_update($jepyso);
    }
}

/**
 * Whether an activity uses a scale.
 *
 * @param int $jepysoid The activity.
 * @param int $scaleid The scale.
 * @return bool
 */
function oksigeniajepyso_scale_used($jepysoid, $scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('oksigeniajepyso', ['id' => $jepysoid, 'grade' => -$scaleid]);
}

/**
 * Whether any activity uses a scale.
 *
 * @param int $scaleid The scale.
 * @return bool
 */
function oksigeniajepyso_scale_used_anywhere($scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('oksigeniajepyso', ['grade' => -$scaleid]);
}

/**
 * Adds the sessions to the course reset form.
 *
 * @param MoodleQuickForm $mform The form.
 */
function oksigeniajepyso_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'oksigeniajepysoheader', get_string('modulenameplural', 'mod_oksigeniajepyso'));
    $mform->addElement('advcheckbox', 'reset_oksigeniajepyso_sessions', get_string('resetsessions', 'mod_oksigeniajepyso'));
}

/**
 * The defaults of the course reset form.
 *
 * @param stdClass $course The course.
 * @return array
 */
function oksigeniajepyso_reset_course_form_defaults($course) {
    return ['reset_oksigeniajepyso_sessions' => 1];
}

/**
 * Deletes the sessions of a course being reset, and its grades if asked.
 *
 * @param stdClass $data What the reset form asked for.
 * @return array The status of each step.
 */
function oksigeniajepyso_reset_userdata($data) {
    global $DB;
    $status = [];
    $component = get_string('modulenameplural', 'mod_oksigeniajepyso');
    $instances = $DB->get_records('oksigeniajepyso', ['course' => $data->courseid]);
    if (!empty($data->reset_oksigeniajepyso_sessions) && $instances) {
        [$in, $params] = $DB->get_in_or_equal(array_keys($instances));
        $DB->delete_records_select('oksigeniajepyso_session', "jepysoid $in", $params);
        $status[] = ['component' => $component, 'item' => get_string('resetsessions', 'mod_oksigeniajepyso'), 'error' => false];
    }
    if (!empty($data->reset_gradebook_grades) || !empty($data->reset_oksigeniajepyso_sessions)) {
        foreach ($instances as $jepyso) {
            oksigeniajepyso_grade_item_update($jepyso, 'reset');
        }
    }
    return $status;
}

/**
 * Adds the follow-up to the menu of the activity, for whoever can see it.
 *
 * @param settings_navigation $settings The settings navigation.
 * @param navigation_node $node The node of the activity.
 */
function oksigeniajepyso_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    $cm = $settings->get_page()->cm;
    if ($cm && has_capability('mod/oksigeniajepyso:viewreports', $cm->context)) {
        $node->add(
            get_string('followup', 'mod_oksigeniajepyso'),
            new moodle_url('/mod/oksigeniajepyso/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'oksigeniajepysoreport'
        );
    }
}
