// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Keep the profiled-user form sections mutually exclusive.
 *
 * @module     local_orgprofile/accordion
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Collapse from 'theme_boost/bootstrap/collapse';

const FORM_SELECTOR = '#page-local-orgprofile-company_user_create form.mform';
const PANEL_SELECTOR = 'fieldset.collapsible > .fcontainer.collapse';
const ERROR_SELECTOR = '.is-invalid, [aria-invalid="true"], .invalid-feedback[style*="display: block"]';

/**
 * Return the existing Bootstrap controller or create one without changing panel state.
 *
 * @param {HTMLElement} panel
 * @returns {Collapse}
 */
const getCollapse = panel => Collapse.getInstance(panel) ?? new Collapse(panel, {toggle: false});

/**
 * Focus and reveal the first invalid control in a panel.
 *
 * @param {HTMLElement} panel
 */
const focusError = panel => {
    const indicator = panel.querySelector(ERROR_SELECTOR);
    const control = indicator?.matches('input, select, textarea, button')
        ? indicator
        : indicator?.closest('.fitem')?.querySelector('input, select, textarea, button');
    if (control) {
        control.focus({preventScroll: true});
        control.scrollIntoView({block: 'center'});
    }
};

/**
 * Enable accordion behavior for the dynamic profiled-user form.
 */
export const init = () => {
    const form = document.querySelector(FORM_SELECTOR);
    if (!form) {
        return;
    }

    const expandAll = form.querySelector('.collapsible-actions');
    if (expandAll) {
        expandAll.hidden = true;
    }

    const panels = [...form.querySelectorAll(PANEL_SELECTOR)];
    panels.forEach(panel => {
        getCollapse(panel);
        panel.addEventListener('show.bs.collapse', () => {
            panels.forEach(otherPanel => {
                if (otherPanel !== panel && otherPanel.classList.contains('show')) {
                    getCollapse(otherPanel).hide();
                }
            });
        });
    });

    const errorPanel = panels.find(panel => panel.querySelector(ERROR_SELECTOR));
    const activePanel = errorPanel ?? panels.find(panel => panel.classList.contains('show')) ?? panels[0];
    panels.filter(panel => panel !== activePanel && panel.classList.contains('show'))
        .forEach(panel => getCollapse(panel).hide());

    if (activePanel && !activePanel.classList.contains('show')) {
        activePanel.addEventListener('shown.bs.collapse', () => {
            if (errorPanel) {
                focusError(errorPanel);
            }
        }, {once: true});
        getCollapse(activePanel).show();
    } else if (errorPanel) {
        focusError(errorPanel);
    }
};
