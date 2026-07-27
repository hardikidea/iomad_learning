// This file is part of Moodle - http://moodle.org/

define([], function() {
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const storageKey = 'theme_iomad_learning_focus';

    const createIcon = (name, sprite) => {
        const icon = document.createElementNS(svgNamespace, 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-linecap', 'round');
        icon.setAttribute('stroke-linejoin', 'round');
        icon.setAttribute('focusable', 'false');
        icon.setAttribute('aria-hidden', 'true');
        icon.setAttribute('data-icon', name);
        icon.setAttribute('class', 'iomad-learning-svg-icon');
        const use = document.createElementNS(svgNamespace, 'use');
        use.setAttribute('href', `${sprite}#${name}`);
        icon.append(use);
        return icon;
    };

    const apply = (enabled, button, sprite) => {
        document.body.classList.toggle('iomad-learning-focus-mode', enabled);
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        button.replaceChildren(createIcon(enabled ? 'compress' : 'expand', sprite));
        button.title = enabled ? 'Exit focus mode' : 'Focus mode';
        button.setAttribute('aria-label', button.title);
    };

    return {
        init: function(sprite) {
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
            document.body.append(button);
            apply(window.localStorage.getItem(storageKey) === '1', button, sprite);
            button.addEventListener('click', () => {
                const enabled = !document.body.classList.contains('iomad-learning-focus-mode');
                window.localStorage.setItem(storageKey, enabled ? '1' : '0');
                apply(enabled, button, sprite);
            });
        },
    };
});
