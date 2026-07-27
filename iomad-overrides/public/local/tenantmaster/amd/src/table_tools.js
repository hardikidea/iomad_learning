// This file is part of Moodle - http://moodle.org/

define([], function() {
    const tableSelector = '#region-main table.generaltable';
    const defaultPageSize = 20;

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

    const addFilter = (table, state, labels, index) => {
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
            state.query = input.value.trim().toLocaleLowerCase();
            state.page = 1;
            clear.disabled = state.query === '';
            state.render();
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

    const addSorting = (table, state, labels) => {
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
                const sorted = [...state.rows]
                    .sort((left, right) => compare(left.cells[column], right.cells[column], direction))
                state.rows.splice(0, state.rows.length, ...sorted);
                sorted.forEach((row) => table.tBodies[0].append(row));
                state.page = 1;
                state.render();
            });
        });
    };

    const addPagination = (table, state, labels) => {
        const pagination = document.createElement('nav');
        pagination.className = 'tenantmaster-pagination';
        pagination.setAttribute('aria-label', labels.page);

        const previous = document.createElement('button');
        previous.className = 'btn btn-secondary';
        previous.type = 'button';
        previous.textContent = labels.previous;

        const status = document.createElement('span');
        status.className = 'tenantmaster-pagination__status';
        status.setAttribute('aria-live', 'polite');

        const next = document.createElement('button');
        next.className = 'btn btn-secondary';
        next.type = 'button';
        next.textContent = labels.next;

        previous.addEventListener('click', () => {
            state.page = Math.max(1, state.page - 1);
            state.render();
        });
        next.addEventListener('click', () => {
            state.page += 1;
            state.render();
        });

        pagination.append(previous, status, next);
        const scrollRegion = table.closest('.iomad-learning-table-scroll');
        (scrollRegion || table).after(pagination);
        return {pagination, previous, status, next};
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
        const requestedPageSize = Number(table.dataset.tenantmasterPageSize);
        const state = {
            rows,
            query: '',
            page: 1,
            pageSize: Number.isInteger(requestedPageSize) && requestedPageSize > 0
                ? requestedPageSize
                : defaultPageSize,
            pagination: null,
            render: null,
        };
        if (rows.length > state.pageSize) {
            state.pagination = addPagination(table, state, labels);
        }
        state.render = () => {
            const matches = state.rows.filter(
                (row) => state.query === ''
                    || row.textContent.toLocaleLowerCase().includes(state.query),
            );
            const pages = Math.max(1, Math.ceil(matches.length / state.pageSize));
            state.page = Math.min(Math.max(1, state.page), pages);
            const first = (state.page - 1) * state.pageSize;
            const visible = new Set(matches.slice(first, first + state.pageSize));
            state.rows.forEach((row) => {
                row.hidden = !visible.has(row);
            });
            if (state.pagination) {
                state.pagination.pagination.hidden = matches.length <= state.pageSize;
                state.pagination.previous.disabled = state.page <= 1;
                state.pagination.next.disabled = state.page >= pages;
                state.pagination.status.textContent = [
                    labels.page,
                    state.page,
                    labels.of,
                    pages,
                    '\u00b7',
                    matches.length,
                    labels.records,
                ].join(' ');
            }
        };
        addFilter(table, state, labels, index);
        addSorting(table, state, labels);
        state.render();
    };

    return {
        init: function(labels) {
            document.querySelectorAll(tableSelector).forEach(
                (table, index) => enhance(table, labels, index),
            );
        },
    };
});
