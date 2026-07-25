// This file is part of Moodle - http://moodle.org/

define([], function() {
    const storageKey = 'theme_iomad_learning_focus';

    const apply = (enabled, button) => {
        document.body.classList.toggle('iomad-learning-focus-mode', enabled);
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        const icon = button.querySelector('i');
        icon.className = enabled ? 'fa fa-compress' : 'fa fa-expand';
        button.title = enabled ? 'Exit focus mode' : 'Focus mode';
        button.setAttribute('aria-label', button.title);
    };

    return {
        init: function() {
            if (!document.body.classList.contains('path-course')) {
                return;
            }
            const available = getComputedStyle(document.documentElement)
                .getPropertyValue('--iomad-learning-coursefocusavailable').trim();
            if (available === '0') {
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-primary iomad-learning-focus-toggle';
            button.innerHTML = '<i class="fa fa-expand" aria-hidden="true"></i>';
            document.body.append(button);
            apply(window.localStorage.getItem(storageKey) === '1', button);
            button.addEventListener('click', () => {
                const enabled = !document.body.classList.contains('iomad-learning-focus-mode');
                window.localStorage.setItem(storageKey, enabled ? '1' : '0');
                apply(enabled, button);
            });
        },
    };
});
