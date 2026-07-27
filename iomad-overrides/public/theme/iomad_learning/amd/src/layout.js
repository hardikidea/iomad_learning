// This file is part of Moodle - http://moodle.org/

define([], function() {
    const tableSelector = 'table.generaltable, table.reportbuilder-table, table.flexible, table.admintable';
    const wrapperClass = 'iomad-learning-table-scroll';
    const projectToolRoutes = [
        '/local/aicoursecreator/',
        '/local/global_events/',
        '/local/iomadcommerce/',
        '/local/iomadpagebuilder/',
        '/local/rapidgrader/',
        '/local/tenantanalytics/',
        '/local/tenantmaster/',
    ];

    const refreshWrapper = (wrapper) => {
        const scrollable = wrapper.scrollWidth > wrapper.clientWidth + 1;
        wrapper.classList.toggle('is-scrollable', scrollable);
        if (scrollable) {
            wrapper.tabIndex = 0;
        } else {
            wrapper.removeAttribute('tabindex');
        }
    };

    const resizeObserver = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver((entries) => {
        entries.forEach(({target}) => refreshWrapper(target));
    });

    const hasMeaningfulContent = (container) => {
        const hasText = container.textContent.trim() !== '';
        const hasInteractiveContent = container.querySelector(
            'a[href], button, input, select, textarea, img, svg, [role="button"]',
        ) !== null;
        return hasText || hasInteractiveContent;
    };

    const updatePageHeader = (header) => {
        if (!(header instanceof HTMLElement)) {
            return;
        }
        header.querySelectorAll(':scope > .w-100 > .d-flex').forEach((row) => {
            row.classList.toggle('is-empty', !hasMeaningfulContent(row));
        });
        const isempty = !hasMeaningfulContent(header);

        header.classList.add('iomad-learning-page-header');
        header.classList.toggle('is-empty', isempty);
        if (isempty) {
            header.setAttribute('aria-hidden', 'true');
        } else {
            header.removeAttribute('aria-hidden');
        }
    };

    const tableLabel = (table) => {
        const caption = table.querySelector('caption');
        if (caption && caption.textContent.trim()) {
            return caption.textContent.trim();
        }
        const heading = table.querySelector('th');
        return heading && heading.textContent.trim() ? heading.textContent.trim() : 'Data table';
    };

    const updateTable = (table) => {
        if (!(table instanceof HTMLTableElement) || table.closest(`.${wrapperClass}`)) {
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.className = wrapperClass;
        if (table.matches('.groupmanagementtable')) {
            wrapper.classList.add('is-dual-list');
        }
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', tableLabel(table));
        table.before(wrapper);
        wrapper.append(table);

        requestAnimationFrame(() => refreshWrapper(wrapper));
        if (resizeObserver) {
            resizeObserver.observe(wrapper);
        }
    };

    const toolOrigin = (link) => {
        const path = new URL(link.href, document.baseURI).pathname.toLocaleLowerCase();
        return projectToolRoutes.some((route) => path.includes(route)) ? 'custom' : 'native';
    };

    const toolBadge = (origin, labels) => {
        const badge = document.createElement('span');
        badge.className = `iomad-learning-tool-origin iomad-learning-tool-origin--${origin}`;
        badge.textContent = labels[origin];
        return badge;
    };

    const updateAdminPane = (pane, labels) => {
        if (!(pane instanceof HTMLElement)) {
            return;
        }
        const links = [...pane.querySelectorAll(':scope > a')];
        if (!links.length) {
            return;
        }
        links.forEach((link) => {
            const origin = toolOrigin(link);
            link.dataset.toolOrigin = origin;
            if (!link.querySelector('.iomad-learning-tool-origin')) {
                link.querySelector('.iomadlink')?.append(toolBadge(origin, labels));
            }
        });
        if (pane.dataset.toolGroupsReady === '1') {
            return;
        }
        const clearfix = pane.querySelector(':scope > .clearfix');
        ['native', 'custom'].forEach((origin) => {
            const group = links.filter((link) => link.dataset.toolOrigin === origin);
            if (!group.length) {
                return;
            }
            const heading = document.createElement('h3');
            heading.className = `iomad-learning-tool-group iomad-learning-tool-group--${origin}`;
            heading.textContent = labels[origin];
            pane.insertBefore(heading, clearfix);
            group.forEach((link) => pane.insertBefore(link, clearfix));
        });
        pane.dataset.toolGroupsReady = '1';
    };

    const updateAll = (root, labels) => {
        const pageHeader = root instanceof Element && root.matches('#page-header')
            ? root
            : root.querySelector?.('#page-header') || root.closest?.('#page-header');
        if (pageHeader) {
            updatePageHeader(pageHeader);
        }
        if (root instanceof HTMLTableElement && root.matches(tableSelector)) {
            updateTable(root);
            return;
        }
        if (root.querySelectorAll) {
            root.querySelectorAll(tableSelector).forEach(updateTable);
            root.querySelectorAll('.iomad_company_admin .iomadlink_container').forEach(
                (pane) => updateAdminPane(pane, labels),
            );
        }
    };

    return {
        init: function(nativeLabel, customLabel) {
            const labels = {native: nativeLabel, custom: customLabel};
            updateAll(document, labels);
            new MutationObserver((mutations) => mutations.forEach((mutation) => {
                const target = mutation.target instanceof Element
                    ? mutation.target
                    : mutation.target.parentElement;
                const pageHeader = target?.closest('#page-header');
                if (pageHeader) {
                    updatePageHeader(pageHeader);
                }
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        updateAll(node, labels);
                    }
                });
            })).observe(document.body, {childList: true, characterData: true, subtree: true});
        },
    };
});
