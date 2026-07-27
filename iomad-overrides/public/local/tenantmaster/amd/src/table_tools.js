// This file is part of Moodle - http://moodle.org/

define([], function() {
    const tableSelector = '#region-main table.generaltable';

    const valueFor = (cell) => {
        const value = cell.textContent.trim();
        const numeric = Number(value.replaceAll(',', ''));
        return value !== '' && Number.isFinite(numeric)
            ? {type: 'number', value: numeric}
            : {type: 'text', value: value.toLocaleLowerCase()};
    };

    const compare = (left, right, direction) => {
        const a = valueFor(left);
        const b = valueFor(right);
        const result = a.type === 'number' && b.type === 'number'
            ? a.value - b.value
            : String(a.value).localeCompare(String(b.value), undefined, {
                numeric: true,
                sensitivity: 'base',
            });
        return direction === 'ascending' ? result : -result;
    };

    const insertBeforeTable = (table, element) => {
        const scrollRegion = table.closest('.iomad-learning-table-scroll');
        (scrollRegion || table).before(element);
    };

    const addFilter = (table, rows, labels, index) => {
        const toolbar = document.createElement('div');
        toolbar.className = 'tenantmaster-table-tools';

        const label = document.createElement('label');
        label.className = 'sr-only';
        label.htmlFor = `tenantmaster-table-filter-${index}`;
        label.textContent = labels.filter;

        const input = document.createElement('input');
        input.className = 'form-control';
        input.id = label.htmlFor;
        input.type = 'search';
        input.placeholder = labels.filter;

        const clear = document.createElement('button');
        clear.className = 'btn btn-secondary tenantmaster-table-tools__clear';
        clear.type = 'button';
        clear.title = labels.clear;
        clear.setAttribute('aria-label', labels.clear);
        clear.textContent = '\u00d7';

        const apply = () => {
            const query = input.value.trim().toLocaleLowerCase();
            rows.forEach((row) => {
                row.hidden = query !== '' && !row.textContent.toLocaleLowerCase().includes(query);
            });
            clear.disabled = query === '';
        };
        input.addEventListener('input', apply);
        clear.addEventListener('click', () => {
            input.value = '';
            apply();
            input.focus();
        });
        apply();

        toolbar.append(label, input, clear);
        insertBeforeTable(table, toolbar);
    };

    const addSorting = (table, rows, labels) => {
        const headers = [...table.querySelectorAll('thead th')];
        headers.forEach((header, column) => {
            const heading = header.textContent.trim();
            if (!heading || heading === labels.actions || header.querySelector('button, a')) {
                return;
            }
            const button = document.createElement('button');
            button.className = 'tenantmaster-sort';
            button.type = 'button';
            button.dataset.direction = 'descending';

            const text = document.createElement('span');
            text.textContent = heading;
            const indicator = document.createElement('span');
            indicator.className = 'tenantmaster-sort__indicator';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.textContent = '\u2195';
            button.append(text, indicator);
            header.replaceChildren(button);

            button.addEventListener('click', () => {
                const direction = button.dataset.direction === 'ascending' ? 'descending' : 'ascending';
                button.dataset.direction = direction;
                header.setAttribute('aria-sort', direction);
                button.title = direction === 'ascending' ? labels.descending : labels.ascending;
                indicator.textContent = direction === 'ascending' ? '\u2191' : '\u2193';
                headers.forEach((other) => {
                    if (other !== header) {
                        other.removeAttribute('aria-sort');
                        const otherIndicator = other.querySelector('.tenantmaster-sort__indicator');
                        if (otherIndicator) {
                            otherIndicator.textContent = '\u2195';
                        }
                    }
                });
                [...rows]
                    .sort((left, right) => compare(left.cells[column], right.cells[column], direction))
                    .forEach((row) => table.tBodies[0].append(row));
            });
        });
    };

    const enhance = (table, labels, index) => {
        if (!(table instanceof HTMLTableElement) || table.dataset.tenantmasterTools === '1') {
            return;
        }
        table.dataset.tenantmasterTools = '1';
        const body = table.tBodies[0];
        if (!body) {
            return;
        }
        const rows = [...body.rows].filter((row) => row.cells.length > 1);
        if (rows.length < 2) {
            return;
        }
        addFilter(table, rows, labels, index);
        addSorting(table, rows, labels);
    };

    return {
        init: function(labels) {
            document.querySelectorAll(tableSelector).forEach(
                (table, index) => enhance(table, labels, index),
            );
        },
    };
});
