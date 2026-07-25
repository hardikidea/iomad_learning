// This file is part of Moodle - http://moodle.org/

define([], function() {
    const cssValue = (input) => {
        if (input.type === 'checkbox') {
            return input.checked ? '1' : '0';
        }
        if (input.tagName === 'SELECT') {
            return input.selectedOptions[0].dataset.css;
        }
        return input.value;
    };

    const apply = (preview, map, control) => {
        const input = control.querySelector('input, select');
        preview.style.setProperty(map[control.dataset.token], cssValue(input));
    };

    return {
        init: function(map) {
            const root = document.querySelector('[data-region="theme-customizer"]');
            const preview = root.querySelector('[data-region="preview"]');
            const controls = Array.from(root.querySelectorAll('[data-token]'));
            controls.forEach((control) => {
                apply(preview, map, control);
                control.querySelector('input, select').addEventListener('input', () => apply(preview, map, control));
            });
            const filter = () => {
                const search = root.querySelector('[data-action="search"]').value.toLocaleLowerCase();
                const group = root.querySelector('[data-action="group"]').value;
                controls.forEach((control) => {
                    control.hidden = Boolean(
                        (search && !control.dataset.label.includes(search)) ||
                        (group && control.dataset.group !== group)
                    );
                });
            };
            root.querySelector('[data-action="search"]').addEventListener('input', filter);
            root.querySelector('[data-action="group"]').addEventListener('change', filter);
            root.querySelector('[data-action="reset"]').addEventListener('click', () => {
                controls.forEach((control) => {
                    const input = control.querySelector('input, select');
                    if (input.type === 'checkbox') {
                        input.checked = control.dataset.default === '1';
                    } else {
                        input.value = control.dataset.default;
                    }
                    apply(preview, map, control);
                });
            });
        },
    };
});
