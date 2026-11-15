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
 * The settings form of the activity.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_oksigeniajepyso\local\routine;

/**
 * The settings form of the activity: the routine, the sessions asked for, the grade and completion.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_oksigeniajepyso_mod_form extends moodleform_mod {
    /**
     * The fields of the form.
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'routinehdr', get_string('routinehdr', 'mod_oksigeniajepyso'));
        // The builder of the player opens in a window of its own and sends the routine back here. Without JavaScript,
        // the link opens it in another tab and the link of the routine is copied from there.
        $builder = new moodle_url('/mod/oksigeniajepyso/reproductor/index.html', ['armar' => 1]);
        $mform->addElement('static', 'buildroutine', '', html_writer::link(
            $builder,
            get_string('buildroutine', 'mod_oksigeniajepyso'),
            ['class' => 'btn btn-secondary', 'id' => 'mod_oksigeniajepyso_build', 'target' => 'jepyso-armar', 'rel' => 'opener']
        ));
        $mform->addElement('textarea', 'routine', get_string('routine', 'mod_oksigeniajepyso'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('routine', PARAM_RAW_TRIMMED);
        $mform->addRule('routine', null, 'required', null, 'client');
        $mform->addHelpButton('routine', 'routine', 'mod_oksigeniajepyso');

        $mform->addElement('text', 'sessions', get_string('sessions', 'mod_oksigeniajepyso'), ['size' => 4]);
        $mform->setType('sessions', PARAM_INT);
        $mform->setDefault('sessions', 0);
        $mform->addHelpButton('sessions', 'sessions', 'mod_oksigeniajepyso');

        $PAGE->requires->js_amd_inline(self::builder_script($builder));

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * The script that opens the builder and takes the routine it sends back.
     *
     * @param moodle_url $builder The address of the builder.
     * @return string
     */
    private static function builder_script(moodle_url $builder): string {
        $url = json_encode($builder->out(false));
        return <<<JS
require([], function() {
    var link = document.getElementById('mod_oksigeniajepyso_build');
    var field = document.getElementById('id_routine');
    if (!link || !field) {
        return;
    }
    link.addEventListener('click', function(e) {
        // A window of its own; if the browser refuses it, the link opens the builder in another tab.
        var builder = window.open({$url}, 'jepyso-armar', 'popup,width=1180,height=860');
        if (builder) {
            e.preventDefault();
            builder.focus();
        }
    });
    window.addEventListener('message', function(e) {
        var data = e.data;
        if (e.origin !== window.location.origin || !data || data.tipo !== 'jepyso-rutina') {
            return;
        }
        if (typeof data.codigo !== 'string' || !/^[A-Za-z0-9._-]{1,20000}$/.test(data.codigo)) {
            return;
        }
        field.value = data.codigo;
        field.dispatchEvent(new Event('change', {bubbles: true}));
        field.focus();
    });
});
JS;
    }

    /**
     * Shows what the site cannot play of the routine below its field.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        $value = $mform->elementExists('routine') ? (string) $mform->getElementValue('routine') : '';
        if (trim($value) === '') {
            return;
        }
        $check = routine::check($value);
        $warnings = routine::warnings($check);
        if ($check['error'] === null && $warnings) {
            $notice = html_writer::div(implode('<br>', array_map('s', $warnings)), 'alert alert-warning mb-0');
            $element = $mform->createElement('static', 'routinewarning', '', $notice);
            $mform->insertElementBefore($element, 'sessions');
        }
    }

    /**
     * Checks the routine and the sessions asked for.
     *
     * @param array $data The data sent.
     * @param array $files The files sent.
     * @return array Errors by field.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $check = routine::check((string) ($data['routine'] ?? ''));
        if ($check['error'] !== null) {
            $errors['routine'] = get_string($check['error'], 'mod_oksigeniajepyso');
        }
        $sessions = (int) ($data['sessions'] ?? 0);
        if ($sessions < 0 || $sessions > 365) {
            $errors['sessions'] = get_string('sessionsrange', 'mod_oksigeniajepyso');
        }
        $suffix = $this->get_suffix();
        if (!empty($data['completionsessions' . $suffix]) && $sessions < 1) {
            $errors['completionsessions' . $suffix] = get_string('completionsessionsneeded', 'mod_oksigeniajepyso');
        }
        return $errors;
    }

    /**
     * The completion rule of the activity: do the sessions asked for.
     *
     * @return string[] The names of the fields.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $name = 'completionsessions' . $this->get_suffix();
        $mform->addElement(
            'advcheckbox',
            $name,
            get_string('completionsessionsgroup', 'mod_oksigeniajepyso'),
            get_string('completionsessions', 'mod_oksigeniajepyso')
        );
        $mform->addHelpButton($name, 'completionsessions', 'mod_oksigeniajepyso');
        return [$name];
    }

    /**
     * Whether the completion rule is on.
     *
     * @param array $data The data of the form.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsessions' . $this->get_suffix()]);
    }

    /**
     * Turns the rule off when completion is not automatic.
     *
     * @param stdClass $data The data of the form.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix} ?? null;
            if (empty($data->{'completionsessions' . $suffix}) || $completion != COMPLETION_TRACKING_AUTOMATIC) {
                $data->{'completionsessions' . $suffix} = 0;
            }
        }
    }
}
