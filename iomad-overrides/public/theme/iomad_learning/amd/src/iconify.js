// This file is part of Moodle - http://moodle.org/

define([], function() {
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const imageSelector = 'img.icon:not(.userpicture), img.activityicon, img.dash-mod-icon';
    const spacerSelector = 'img.icon.spacer';
    const legacySelector = '.fa, .fas, .far, .fab';
    const preservedLegacyClasses = ['fa-action', 'fa-fw', 'fa-spin', 'fa-topic'];

    const exactImageIcons = new Map([
        ['e/emoticons', 'smile'],
        ['i/contentbank', 'archive'],
        ['i/emojicategoryactivities', 'trophy'],
        ['i/emojicategoryanimalsnature', 'leaf'],
        ['i/emojicategoryflags', 'flag'],
        ['i/emojicategoryfooddrink', 'coffee'],
        ['i/emojicategoryobjects', 'lightbulb'],
        ['i/emojicategorypeoplebody', 'group'],
        ['i/emojicategoryrecent', 'clock'],
        ['i/emojicategorysmileysemotion', 'smile'],
        ['i/emojicategorysymbols', 'shapes'],
        ['i/emojicategorytravelplaces', 'mapPin'],
        ['i/loading', 'spinner'],
        ['i/loading_small', 'spinner'],
        ['i/navigationitem', 'menu'],
        ['i/next', 'chevronRight'],
        ['i/permissions', 'shield'],
        ['i/previous', 'chevronLeft'],
        ['i/restore', 'restore'],
        ['i/trash', 'trash'],
        ['i/viewcategory', 'eye'],
        ['i/withsubcat', 'folderPlus'],
        ['t/cohort', 'group'],
        ['t/down', 'arrowDown'],
        ['t/switch_minus', 'eyeOff'],
        ['t/switch_plus', 'eye'],
        ['t/up', 'arrowUp'],
        ['fp/view_icon_active', 'dashboard'],
        ['fp/view_tree_active', 'list'],
    ]);

    const keywordIcons = [
        ['certificate', 'certificate'], ['completion', 'checkCircle'], ['dashboard', 'dashboard'],
        ['download', 'download'], ['calendar', 'calendar'], ['company', 'building'],
        ['department', 'building'], ['participant', 'group'], ['question', 'help'],
        ['permission', 'shield'], ['content bank', 'archive'], ['subcategory', 'folderPlus'],
        ['restore', 'restore'], ['loading', 'spinner'], ['language', 'language'],
        ['setting', 'settings'], ['archive', 'archive'], ['badge', 'award'], ['course', 'course'],
        ['delete', 'trash'], ['edit', 'edit'], ['email', 'mail'], ['export', 'fileExport'],
        ['folder', 'folder'], ['grade', 'list'], ['group', 'group'], ['help', 'help'],
        ['home', 'home'], ['import', 'fileImport'], ['info', 'info'], ['link', 'link'],
        ['lock', 'lock'], ['message', 'message'], ['notification', 'bell'], ['play', 'play'],
        ['print', 'print'], ['refresh', 'refresh'], ['report', 'report'], ['save', 'save'],
        ['search', 'search'], ['show', 'eye'], ['star', 'star'], ['tag', 'tag'],
        ['time', 'clock'], ['upload', 'upload'], ['user', 'user'], ['video', 'video'],
        ['warning', 'alert'],
    ];

    const keywordName = (value) => {
        const normalized = value.toLocaleLowerCase();
        const match = keywordIcons.find(([keyword]) => normalized.includes(keyword));
        return match ? match[1] : '';
    };

    const createIcon = (name, classes, sprite, label = '', title = '') => {
        const icon = document.createElementNS(svgNamespace, 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-linecap', 'round');
        icon.setAttribute('stroke-linejoin', 'round');
        icon.setAttribute('focusable', 'false');
        icon.setAttribute('data-icon', name);
        icon.setAttribute('class', [...new Set([...classes, 'iomad-learning-svg-icon'])].join(' '));
        if (label) {
            icon.setAttribute('role', 'img');
            icon.setAttribute('aria-label', label);
        } else {
            icon.setAttribute('aria-hidden', 'true');
        }
        if (title) {
            icon.setAttribute('title', title);
        }
        const use = document.createElementNS(svgNamespace, 'use');
        use.setAttribute('href', `${sprite}#${name}`);
        icon.append(use);
        return icon;
    };

    const sourceParts = (image) => {
        try {
            const path = decodeURIComponent(new URL(image.src, document.baseURI).pathname);
            const match = path.match(/\/theme\/image\.php\/[^/]+\/([^/]+)\/[^/]+\/(.+)$/);
            if (!match) {
                return ['', path.toLocaleLowerCase()];
            }
            const source = match[1].toLocaleLowerCase();
            return [source, `${source}/${match[2]}`.toLocaleLowerCase()];
        } catch (error) {
            return ['', ''];
        }
    };

    const iconFromImage = (image, componentMap) => {
        const [source, path] = sourceParts(image);
        for (const [suffix, icon] of exactImageIcons) {
            if (path.includes(suffix)) {
                return icon;
            }
        }
        const candidates = [source, `mod_${source}`];
        const component = candidates.find((candidate) => Object.hasOwn(componentMap, candidate));
        if (component) {
            return componentMap[component];
        }
        return keywordName(`${path} ${image.alt || ''} ${image.title || ''}`) || 'activity';
    };

    const replaceImage = (image, componentMap, sprite) => {
        if (image.matches(spacerSelector)) {
            const spacer = document.createElement('span');
            spacer.className = `${image.className} iomad-learning-icon-spacer`;
            spacer.setAttribute('aria-hidden', 'true');
            image.replaceWith(spacer);
            return;
        }

        const classes = [...image.classList].filter((className) => className !== 'spacer');
        const icon = createIcon(
            iconFromImage(image, componentMap),
            [...classes, 'iomad-learning-replaced-icon'],
            sprite,
            (image.alt || '').trim(),
            image.title || '',
        );
        for (const attribute of image.attributes) {
            if (attribute.name.startsWith('data-')) {
                icon.setAttribute(attribute.name, attribute.value);
            }
        }
        icon.setAttribute('data-image-source', image.getAttribute('src') || '');
        image.replaceWith(icon);
    };

    const legacyName = (node, legacyMap) => {
        for (const className of node.classList) {
            if (Object.hasOwn(legacyMap, className)) {
                return legacyMap[className];
            }
        }
        return keywordName(`${node.className} ${node.title || ''} ${node.getAttribute('aria-label') || ''}`) || 'activity';
    };

    const replaceLegacy = (node, legacyMap, sprite) => {
        const classes = [...node.classList].filter((className) => {
            if (!className.startsWith('fa-')) {
                return className !== 'fa' && className !== 'fas' && className !== 'far' && className !== 'fab';
            }
            return preservedLegacyClasses.includes(className);
        });
        const label = (node.getAttribute('aria-label') || '').trim();
        const icon = createIcon(
            legacyName(node, legacyMap),
            [...classes, 'iomad-learning-legacy-icon'],
            sprite,
            label,
            node.title || '',
        );
        for (const attribute of node.attributes) {
            if (attribute.name.startsWith('data-')) {
                icon.setAttribute(attribute.name, attribute.value);
            }
        }
        icon.setAttribute(
            'data-legacy-source',
            [...node.classList].filter((className) => className.startsWith('fa-')).join(' '),
        );
        node.replaceWith(icon);
    };

    const replaceAll = (root, componentMap, legacyMap, sprite) => {
        if (root instanceof HTMLImageElement && root.matches(imageSelector)) {
            replaceImage(root, componentMap, sprite);
            return;
        }
        if (root instanceof HTMLElement && root.matches(legacySelector)) {
            replaceLegacy(root, legacyMap, sprite);
            return;
        }
        if (!root.querySelectorAll) {
            return;
        }
        root.querySelectorAll(imageSelector).forEach((image) => replaceImage(image, componentMap, sprite));
        root.querySelectorAll(legacySelector).forEach((node) => replaceLegacy(node, legacyMap, sprite));
    };

    return {
        init: function(componentMap, legacyMap, sprite) {
            replaceAll(document, componentMap, legacyMap, sprite);
            new MutationObserver((mutations) => mutations.forEach((mutation) => mutation.addedNodes.forEach(
                (node) => {
                    if (node instanceof Element) {
                        replaceAll(node, componentMap, legacyMap, sprite);
                    }
                },
            ))).observe(document.body, {childList: true, subtree: true});
        },
    };
});
