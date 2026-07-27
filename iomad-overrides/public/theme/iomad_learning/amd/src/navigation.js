// This file is part of Moodle - http://moodle.org/

define([], function() {
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const selectors = [
        '.primary-navigation .nav-link',
        '.secondary-navigation .nav-link',
        '.tertiary-navigation .nav-link',
        '.drawer .list-group-item-action',
        '.block_navigation .block_tree a',
        '.block_settings .block_tree a',
        '#page-navbar .breadcrumb-item',
    ].join(',');

    const labels = [
        ['dashboard', 'dashboard'], ['home', 'home'], ['company', 'building'],
        ['department', 'building'], ['user', 'user'], ['participant', 'group'],
        ['course', 'course'], ['calendar', 'calendar'], ['grade', 'list'],
        ['report', 'report'], ['support', 'help'], ['connector', 'link'],
        ['setting', 'settings'], ['administration', 'settings'], ['blog', 'file'],
        ['badge', 'award'], ['competency', 'award'], ['licence', 'key'], ['license', 'key'],
        ['commerce', 'store'], ['payment', 'creditCard'], ['microlearning', 'activity'],
        ['audit', 'report'], ['import', 'fileImport'], ['certificate', 'certificate'],
        ['theme', 'palette'], ['customizer', 'palette'],
        ['note', 'file'], ['tag', 'tag'], ['global event', 'activity'],
    ];

    const routes = [
        ['/local/tenantmaster/', 'institution'], ['/local/tenantanalytics/', 'report'],
        ['/local/rapidgrader/', 'list'], ['/local/global_events/', 'activity'],
        ['/local/iomadcommerce/', 'store'], ['/local/institutionpack/', 'fileImport'],
        ['/blocks/iomad_company_admin/', 'building'], ['/blog/', 'file'],
        ['/badges/', 'award'], ['/notes/', 'file'], ['/tag/', 'tag'],
        ['/admin/', 'settings'], ['/course/', 'course'], ['/calendar/', 'calendar'],
        ['/user/', 'group'], ['/my/', 'dashboard'],
    ];

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
        icon.setAttribute('class', 'iomad-learning-svg-icon iomad-learning-nav-icon');
        const use = document.createElementNS(svgNamespace, 'use');
        use.setAttribute('href', `${sprite}#${name}`);
        icon.append(use);
        return icon;
    };

    const iconFor = (node) => {
        const label = node.textContent.trim().toLocaleLowerCase();
        const labelMatch = labels.find(([term]) => label.includes(term));
        if (labelMatch) {
            return labelMatch[1];
        }
        const link = node.matches('a') ? node : node.querySelector('a');
        const path = new URL(link ? link.href : window.location.href, document.baseURI).pathname.toLocaleLowerCase();
        const routeMatch = routes.find(([route]) => path.includes(route));
        return routeMatch ? routeMatch[1] : 'link';
    };

    const decorate = (node, sprite) => {
        const target = node.matches('.breadcrumb-item') ? (node.querySelector('a') || node) : node;
        if (target.querySelector('.iomad-learning-nav-icon')) {
            return;
        }
        const existing = target.querySelector('.iomad-learning-svg-icon, i.icon, i.fa, span.fa');
        const icon = createIcon(iconFor(node), sprite);
        if (existing) {
            existing.replaceWith(icon);
        } else {
            target.prepend(icon);
        }
    };

    return {
        init: function(sprite) {
            const enabled = getComputedStyle(document.documentElement)
                .getPropertyValue('--iomad-learning-shownavigationicons').trim();
            if (enabled === '0') {
                return;
            }
            document.querySelectorAll(selectors).forEach((link) => decorate(link, sprite));
        },
    };
});
