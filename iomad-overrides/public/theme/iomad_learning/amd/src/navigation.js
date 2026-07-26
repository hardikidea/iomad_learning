// This file is part of Moodle - http://moodle.org/

define([], function() {
    const selectors = [
        '.primary-navigation .nav-link',
        '.secondary-navigation .nav-link',
        '.tertiary-navigation .nav-link',
        '.drawer .list-group-item-action',
        '.block_navigation .block_tree a',
        '.block_settings .block_tree a',
    ].join(',');

    const labels = [
        ['dashboard', 'fa-gauge-high'],
        ['home', 'fa-house'],
        ['company', 'fa-building'],
        ['department', 'fa-network-wired'],
        ['user', 'fa-user'],
        ['participant', 'fa-user-group'],
        ['course', 'fa-graduation-cap'],
        ['calendar', 'fa-regular fa-calendar'],
        ['grade', 'fa-regular fa-square-check'],
        ['report', 'fa-chart-column'],
        ['support', 'fa-regular fa-life-ring'],
        ['connector', 'fa-plug'],
        ['setting', 'fa-gear'],
        ['administration', 'fa-gear'],
        ['blog', 'fa-blog'],
        ['badge', 'fa-award'],
        ['note', 'fa-note-sticky'],
        ['tag', 'fa-tags'],
        ['global event', 'fa-bolt'],
    ];

    const routes = [
        ['/local/tenantmaster/', 'iomad-learning-icon-custom iomad-learning-icon-institution'],
        ['/local/tenantanalytics/', 'fa-chart-line'],
        ['/local/rapidgrader/', 'fa-table-list'],
        ['/local/global_events/', 'fa-bolt'],
        ['/blocks/iomad_company_admin/', 'fa-building'],
        ['/blog/', 'fa-blog'],
        ['/badges/', 'fa-award'],
        ['/notes/', 'fa-note-sticky'],
        ['/tag/', 'fa-tags'],
        ['/admin/', 'fa-gear'],
        ['/course/', 'fa-graduation-cap'],
        ['/calendar/', 'fa-regular fa-calendar'],
        ['/user/', 'fa-user-group'],
        ['/my/', 'fa-gauge-high'],
    ];

    const iconFor = (link) => {
        const label = link.textContent.trim().toLocaleLowerCase();
        const labelMatch = labels.find(([term]) => label.includes(term));
        if (labelMatch) {
            return labelMatch[1];
        }
        const path = new URL(link.href, document.baseURI).pathname.toLocaleLowerCase();
        const routeMatch = routes.find(([route]) => path.includes(route));
        return routeMatch ? routeMatch[1] : '';
    };

    const decorate = (link) => {
        const existing = link.querySelector('.iomad-learning-nav-icon, i.icon, i.fa');
        const hasSemanticIcon = existing && [...existing.classList]
            .some((className) => className.startsWith('fa-') && className !== 'fa-fw');
        if (hasSemanticIcon) {
            return;
        }
        const icon = iconFor(link) || (existing ? 'fa-link' : '');
        if (!icon) {
            return;
        }
        const node = existing || document.createElement('i');
        const isCustom = icon.includes('iomad-learning-icon-custom');
        node.classList.add(...icon.split(' '), 'fa-fw', 'iomad-learning-nav-icon');
        if (!isCustom) {
            node.classList.add('fa');
        }
        node.setAttribute('aria-hidden', 'true');
        if (!existing) {
            link.prepend(node);
        }
    };

    return {
        init: function() {
            const enabled = getComputedStyle(document.documentElement)
                .getPropertyValue('--iomad-learning-shownavigationicons').trim();
            if (enabled === '0') {
                return;
            }
            document.querySelectorAll(selectors).forEach(decorate);
        },
    };
});
