// This file is part of Moodle - http://moodle.org/

/**
 * Multi-page and conditional-field behaviour for tenant forms.
 *
 * @module     mod_tenantform/form
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const valueFor = (form, fieldId) => {
    const controls = [...form.querySelectorAll(`[data-field-control="${CSS.escape(fieldId)}"]`)];
    if (!controls.length) {
        return '';
    }
    const first = controls[0];
    if (first.type === 'radio') {
        const selected = controls.find(control => control.checked);
        return selected ? selected.value : '';
    }
    if (first.type === 'checkbox') {
        return first.checked ? '1' : '0';
    }
    return first.value;
};

const conditionMatches = (form, wrapper) => {
    if (!wrapper.dataset.conditionField) {
        return true;
    }
    const actual = valueFor(form, wrapper.dataset.conditionField);
    const expected = wrapper.dataset.conditionValue || '';
    switch (wrapper.dataset.conditionOperator) {
        case 'equals':
            return actual === expected;
        case 'not_equals':
            return actual !== expected;
        case 'contains':
            return expected !== '' && actual.includes(expected);
        case 'empty':
            return actual === '';
        case 'not_empty':
            return actual !== '';
        default:
            return false;
    }
};

const refreshConditions = form => {
    form.querySelectorAll('[data-field-id]').forEach(wrapper => {
        const visible = conditionMatches(form, wrapper);
        wrapper.hidden = !visible;
        wrapper.querySelectorAll('input,select,textarea').forEach(control => {
            control.disabled = !visible;
            if (visible && wrapper.dataset.required === '1') {
                control.required = control.type !== 'radio' || control === wrapper.querySelector('input[type="radio"]');
            } else {
                control.required = false;
            }
        });
    });
};

const initializeForm = form => {
    const pages = [...form.querySelectorAll('[data-form-page]')];
    const previous = form.querySelector('[data-form-previous]');
    const next = form.querySelector('[data-form-next]');
    const submit = form.querySelector('[data-form-submit]');
    const progress = form.querySelector('[data-form-progress]');
    let current = Math.max(0, pages.findIndex(page => page.dataset.pageHasError === '1'));

    const showPage = index => {
        current = Math.max(0, Math.min(index, pages.length - 1));
        pages.forEach((page, pageIndex) => {
            page.hidden = pageIndex !== current;
        });
        previous.hidden = current === 0;
        next.hidden = current === pages.length - 1;
        submit.hidden = current !== pages.length - 1;
        progress.textContent = `${current + 1} / ${pages.length}`;
        refreshConditions(form);
    };

    previous.addEventListener('click', () => showPage(current - 1));
    next.addEventListener('click', () => {
        const controls = [...pages[current].querySelectorAll('input,select,textarea')]
            .filter(control => !control.disabled);
        const invalid = controls.find(control => !control.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            return;
        }
        showPage(current + 1);
        pages[current].querySelector('input,select,textarea')?.focus();
    });
    form.addEventListener('change', () => refreshConditions(form));
    showPage(current);
};

/**
 * Initialise every form on the page.
 */
export const init = () => {
    document.querySelectorAll('[data-tenantform]').forEach(initializeForm);
};
