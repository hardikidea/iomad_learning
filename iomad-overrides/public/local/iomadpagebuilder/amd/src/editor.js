// This file is part of IOMAD - http://www.iomad.org/

/**
 * Accessible structured page editor.
 *
 * @module local_iomadpagebuilder/editor
 */
define([], function() {
    const escape = (value) => {
        const node = document.createElement('span');
        node.textContent = value || '';
        return node.innerHTML;
    };

    const emptySection = (preset, index) => ({
        id: 'section-' + Date.now() + '-' + index,
        preset: preset.key,
        type: preset.type,
        variant: preset.variant,
        title: preset.defaults.title,
        body: '',
        mediaurl: '',
        primarylabel: '',
        primaryurl: '',
        secondarylabel: '',
        secondaryurl: '',
        items: [],
    });

    const init = (presets, templates) => {
        const root = document.getElementById('iopb-editor');
        const definitionField = document.getElementById('id_definition');
        const presetSelect = document.getElementById('iopb-preset-select');
        const addButton = document.getElementById('iopb-add-component');
        const templateSelect = document.getElementById('id_startertemplate');
        if (!root || !definitionField || !presetSelect || !addButton) {
            return;
        }

        let definition;
        try {
            definition = JSON.parse(definitionField.value);
        } catch (error) {
            definition = templates.school_home.definition;
        }
        presets.forEach((preset) => {
            const option = document.createElement('option');
            option.value = preset.key;
            option.textContent = preset.name;
            presetSelect.append(option);
        });

        const sync = () => {
            definitionField.value = JSON.stringify(definition);
        };
        const field = (section, name, label, multiline) => {
            const value = escape(section[name]);
            if (multiline) {
                return '<label>' + label + '<textarea class="form-control" data-field="' + name
                    + '" rows="3">' + value + '</textarea></label>';
            }
            return '<label>' + label + '<input class="form-control" data-field="' + name
                + '" value="' + value + '"></label>';
        };
        const render = () => {
            root.innerHTML = '';
            definition.sections.forEach((section, index) => {
                const row = document.createElement('section');
                row.className = 'iopb-editor-row';
                row.draggable = true;
                row.dataset.index = index;
                row.innerHTML = '<div class="iopb-editor-rowbar">'
                    + '<span class="iopb-drag-handle" title="Drag component"><i class="fa fa-arrows"></i></span>'
                    + '<strong>' + escape(section.preset) + '</strong>'
                    + '<div class="iopb-editor-actions">'
                    + '<button type="button" class="btn btn-sm btn-secondary" data-action="up" title="Move up">'
                    + '<i class="fa fa-arrow-up" aria-hidden="true"></i><span class="sr-only">Move up</span></button>'
                    + '<button type="button" class="btn btn-sm btn-secondary" data-action="down" title="Move down">'
                    + '<i class="fa fa-arrow-down" aria-hidden="true"></i><span class="sr-only">Move down</span></button>'
                    + '<button type="button" class="btn btn-sm btn-danger" data-action="remove" title="Remove">'
                    + '<i class="fa fa-trash" aria-hidden="true"></i><span class="sr-only">Remove</span></button>'
                    + '</div></div><div class="iopb-editor-fields">'
                    + field(section, 'title', 'Title', false)
                    + field(section, 'body', 'Content', true)
                    + field(section, 'mediaurl', 'Media URL', false)
                    + field(section, 'primarylabel', 'Primary action label', false)
                    + field(section, 'primaryurl', 'Primary action URL', false)
                    + '</div>';
                root.append(row);
            });
            sync();
        };

        addButton.addEventListener('click', () => {
            const preset = presets.find((item) => item.key === presetSelect.value);
            definition.sections.push(emptySection(preset, definition.sections.length));
            render();
        });
        root.addEventListener('input', (event) => {
            const target = event.target;
            if (!target.dataset.field) {
                return;
            }
            const row = target.closest('.iopb-editor-row');
            definition.sections[Number(row.dataset.index)][target.dataset.field] = target.value;
            sync();
        });
        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) {
                return;
            }
            const index = Number(button.closest('.iopb-editor-row').dataset.index);
            if (button.dataset.action === 'remove') {
                definition.sections.splice(index, 1);
            } else if (button.dataset.action === 'up' && index > 0) {
                [definition.sections[index - 1], definition.sections[index]]
                    = [definition.sections[index], definition.sections[index - 1]];
            } else if (button.dataset.action === 'down' && index < definition.sections.length - 1) {
                [definition.sections[index + 1], definition.sections[index]]
                    = [definition.sections[index], definition.sections[index + 1]];
            }
            render();
        });
        root.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', event.target.closest('.iopb-editor-row').dataset.index);
        });
        root.addEventListener('dragover', (event) => event.preventDefault());
        root.addEventListener('drop', (event) => {
            event.preventDefault();
            const from = Number(event.dataTransfer.getData('text/plain'));
            const destination = event.target.closest('.iopb-editor-row');
            if (!destination) {
                return;
            }
            const to = Number(destination.dataset.index);
            const moved = definition.sections.splice(from, 1)[0];
            definition.sections.splice(to, 0, moved);
            render();
        });
        if (templateSelect) {
            templateSelect.addEventListener('change', () => {
                if (templates[templateSelect.value]) {
                    definition = JSON.parse(JSON.stringify(templates[templateSelect.value].definition));
                    render();
                }
            });
        }
        render();
    };

    return {init: init};
});

