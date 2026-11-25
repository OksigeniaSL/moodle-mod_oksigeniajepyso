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
 * The form of the activity: opens the builder in a window of its own, and takes the routine it sends back.
 *
 * @module     mod_oksigeniajepyso/form
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    BUILD: 'mod_oksigeniajepyso_build',
    FIELD: 'id_routine',
    PREVIEW: 'mod_oksigeniajepyso_preview',
    STALE: 'mod_oksigeniajepyso_stale',
};

/** What the code of a routine looks like. */
const CODE = /^[A-Za-z0-9._-]{1,20000}$/;

/**
 * A code that changes in the form is not the one its preview shows: the preview goes until the form is saved.
 */
const outdated = () => {
    const preview = document.getElementById(SELECTORS.PREVIEW);
    const stale = document.getElementById(SELECTORS.STALE);
    if (preview && stale) {
        preview.hidden = true;
        stale.hidden = false;
    }
};

/**
 * Opens the builder. If the browser refuses the window, the link opens it in another tab.
 *
 * @param {MouseEvent} event The click.
 */
const open = (event) => {
    const builder = window.open(event.currentTarget.href, 'jepyso-armar', 'popup,width=1180,height=860');
    if (builder) {
        event.preventDefault();
        builder.focus();
    }
};

/**
 * Puts in the form the routine the builder sends, from a window of this same site.
 *
 * @param {MessageEvent} event The message.
 */
const take = (event) => {
    const field = document.getElementById(SELECTORS.FIELD);
    const data = event.data;
    if (!field || event.origin !== window.location.origin || !data || data.tipo !== 'jepyso-rutina') {
        return;
    }
    if (typeof data.codigo !== 'string' || !CODE.test(data.codigo)) {
        return;
    }
    field.value = data.codigo;
    outdated();
    field.dispatchEvent(new Event('change', {bubbles: true}));
    field.focus();
};

/**
 * Starts listening.
 */
export const init = () => {
    const link = document.getElementById(SELECTORS.BUILD);
    const field = document.getElementById(SELECTORS.FIELD);
    if (!link || !field) {
        return;
    }
    field.addEventListener('input', outdated);
    link.addEventListener('click', open);
    window.addEventListener('message', take);
};
