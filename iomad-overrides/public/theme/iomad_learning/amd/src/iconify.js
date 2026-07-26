// This file is part of Moodle - http://moodle.org/

define([], function() {
    const imageSelector = 'img.activityicon, img.dash-mod-icon';
    const spacerSelector = 'img.icon.spacer';

    const componentFromImage = (image, map) => {
        let path;
        try {
            path = decodeURIComponent(new URL(image.src, document.baseURI).pathname);
        } catch (error) {
            return '';
        }
        const match = path.match(
            /\/theme\/image\.php\/[^/]+\/([^/]+)\/[^/]+\/(?:.*\/)?(?:icon|monologo)(?:\.svg)?$/,
        );
        if (!match) {
            return '';
        }
        const source = match[1].toLocaleLowerCase();
        const candidates = [source, `mod_${source}`];
        return candidates.find((candidate) => Object.hasOwn(map, candidate)) || '';
    };

    const replaceImage = (image, map) => {
        const component = componentFromImage(image, map);
        if (!component) {
            return;
        }
        const icon = map[component];
        const node = document.createElement('i');
        const isCustom = icon.includes('iomad-learning-icon-custom');
        const classes = [...image.classList, ...icon.split(' '), 'iomad-learning-fa-icon'];
        if (!isCustom) {
            classes.push('fa');
        }
        node.className = [...new Set(classes)].join(' ');

        for (const attribute of image.attributes) {
            if (attribute.name.startsWith('data-')) {
                node.setAttribute(attribute.name, attribute.value);
            }
        }
        const label = image.alt.trim();
        if (label) {
            node.setAttribute('role', 'img');
            node.setAttribute('aria-label', label);
        } else {
            node.setAttribute('aria-hidden', 'true');
        }
        if (image.title) {
            node.title = image.title;
        }
        image.replaceWith(node);
    };

    const replaceAll = (root, map) => {
        if (root instanceof HTMLImageElement && root.matches(imageSelector)) {
            replaceImage(root, map);
        }
        if (root instanceof HTMLImageElement && root.matches(spacerSelector)) {
            const spacer = document.createElement('span');
            spacer.className = `${root.className} iomad-learning-icon-spacer`;
            spacer.setAttribute('aria-hidden', 'true');
            root.replaceWith(spacer);
            return;
        }
        if (root.querySelectorAll) {
            root.querySelectorAll(imageSelector).forEach((image) => replaceImage(image, map));
            root.querySelectorAll(spacerSelector).forEach((image) => {
                const spacer = document.createElement('span');
                spacer.className = `${image.className} iomad-learning-icon-spacer`;
                spacer.setAttribute('aria-hidden', 'true');
                image.replaceWith(spacer);
            });
        }
    };

    return {
        init: function(map) {
            replaceAll(document, map);
            new MutationObserver((mutations) => mutations.forEach((mutation) => mutation.addedNodes.forEach(
                (node) => {
                    if (node instanceof Element) {
                        replaceAll(node, map);
                    }
                },
            ))).observe(document.body, {childList: true, subtree: true});
        },
    };
});
