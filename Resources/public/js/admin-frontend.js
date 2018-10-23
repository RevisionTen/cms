(function($) {

    // Binds editor controls to the element.
    function bindElement(element, bindChildren = false)
    {
        let translations = typeof window.translations !== 'undefined' ? window.translations : {
            addElement: 'Add Element',
            delete: 'Delete',
            edit: 'Edit',
            duplicate: 'Duplicate',
            shift: 'Shift',
            enable: 'Enable',
            disable: 'Disable'
        };

        // Remove already existing controls.
        element.find('.editor').remove();

        // Get the type of the element.
        let type = element.data('type');

        // Get controls background color from element options.
        let bg = 'bg-primary';
        if (element.hasClass('row')) {
            bg = 'bg-secondary';
        } else if (element.is('[class |= col]')) {
            bg = 'bg-info';
        }
        if (element.data('bg')) {
            bg = element.data('bg');
        }

        // Get the state of the element.
        let enabled = element.data('enabled');

        // Get disabled actions from element options.
        let disabledActions = element.data('disabled-actions');
        if (disabledActions) {
            disabledActions = disabledActions.split(',');
        } else {
            disabledActions = [];
        }

        let label = element.data('label');

        let textColor = 'text-muted';
        if ('Section' === type ||'Row' === type ||'Column' === type) {
            textColor = 'text-white';
        }
        let actionButtonClasses = 'btn btn-sm '+textColor;

        // Build controls.
        let html = `<div class="editor editor-header button-group p-1 text-right w-100 align-self-start">`
            + (label ? `<span class="btn btn-sm float-left ${textColor}">${label}</span>` : '')
            + ((disabledActions.indexOf('enable') === -1 && enabled == 0) ? `<span class="btn-enable ${actionButtonClasses}" title="${translations.enable}"><span class="fa fa-eye-slash"></span></span>` : '')
            + ((disabledActions.indexOf('disable') === -1 && enabled == 1) ? `<span class="btn-disable ${actionButtonClasses}" title="${translations.disable}"><span class="fa fa-eye"></span></span>` : '')
            + (disabledActions.indexOf('shift') === -1 ? `<span class="btn-up ${actionButtonClasses}" title="${translations.shift}"><span class="fa fa-arrow-up"></span></span>` : '')
            + (disabledActions.indexOf('shift') === -1 ? `<span class="btn-down ${actionButtonClasses}" title="${translations.shift}"><span class="fa fa-arrow-down"></span></span>` : '')
            + (disabledActions.indexOf('duplicate') === -1 ? `<span class="btn-duplicate ${actionButtonClasses}" title="${translations.duplicate}"><span class="fas fa-clone"></span></span>` : '')
            + (disabledActions.indexOf('edit') === -1 ? `<span class="btn-edit ${actionButtonClasses}" title="${translations.edit}"><span class="fa fa-edit"></span></span>` : '')
            + (disabledActions.indexOf('delete') === -1 ? `<span class="btn-delete ${actionButtonClasses}" title="${translations.delete}"><span class="fas fa-times"></span></span>` : '')
            +`</div>`;

        // Wrap the controls in a column if the element is a row.
        if (element.hasClass('row')) {
            html = '<div class="editor col-12">' + html + '</div>';
        }

        // Add controls.
        element.prepend(html);

        // Get child elements.
        let children = {};
        let childrenNames = element.data('children');
        if (typeof childrenNames !== 'undefined' && childrenNames.length > 0 && childrenNames === 'all') {
            // Get all publicly available elements.
            let publicElements = {};
            if (window.pageElements.constructor === Object && Object.keys(window.pageElements).length > 0) {
                Object.keys(window.pageElements).forEach(childName => {
                    let child = window.pageElements[childName];
                    if (child.public) {
                        publicElements[childName] = child;
                    }
                });
            }
            children = publicElements;
        } else if (typeof childrenNames !== 'undefined' && childrenNames.length > 0) {
            // Get defined child elements for this element.
            for (let childName of childrenNames.split(',')) {
                children[childName] = window.pageElements[childName];
            }
        }

        // Build child controls.
        let childCount = Object.keys(children).length;
        if (children.constructor === Object && childCount > 0) {
            let addButton = '';

            let editorClasses = [
                'editor',
                'editor-footer',
            ];

            let buttonClasses = [
                'editor-add-button',
                //bg,
                'btn',
                'btn-block',
                'btn-sm',
                textColor,
            ];

            if ('Row' === type) {
                editorClasses.push('col-auto align-self-stretch d-flex');
                buttonClasses.push('flex-grow d-flex align-items-center');
            } else {
                editorClasses.push('w-100 align-self-end');
            }

            if (childCount === 1) {
                // Only one possible child type.
                let child = children[Object.keys(children)[0]];
                addButton = `<span data-element-name="${child.name}" class="btn-add `+(buttonClasses.join(' '))+`"><i class="fa fa-plus"></i> ${child.label}</span>`;
            } else {
                // Multiple child types.
                Object.keys(children).forEach(key => {
                    let child = children[key];
                    addButton += `<span data-element-name="${child.name}" class="btn-add dropdown-item"><span class="${child.icon}"></span> ${child.label}</span>`;
                });
                addButton = `<div class="dropup"><button class="`+(buttonClasses.join(' '))+`" type="button" data-toggle="dropdown"><i class="fa fa-plus"></i> ${translations.addElement}</button><div class="dropdown-menu">${addButton}</div>`;
            }

            // Add child controls.
            element.append('<div class="'+(editorClasses.join(' '))+'">'+addButton+'</div>');
        }

        // Add grid to section.
        if ('Section' === type) {
            element.prepend('<div class="editor editor-grid row"><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div></div>');
        }

        // Bind actions to the control elements.
        element.find('.btn-add').on('click', function (event) {
            parent.$('body').trigger('createElement', {'parent': element.data('uuid'), 'elementName': $(this).data('element-name')});
        });
        element.find('.btn-edit').on('click', function (event) {
            parent.$('body').trigger('editElement', {'uuid': element.data('uuid')});
        });
        element.find('.btn-delete').on('click', function (event) {
            parent.$('body').trigger('deleteElement', {'uuid': element.data('uuid')});
        });
        element.find('.btn-up').on('click', function (event) {
            parent.$('body').trigger('shiftElement', {'uuid': element.data('uuid'), 'direction': 'up'});
        });
        element.find('.btn-down').on('click', function (event) {
            parent.$('body').trigger('shiftElement', {'uuid': element.data('uuid'), 'direction': 'down'});
        });
        element.find('.btn-disable').on('click', function (event) {
            parent.$('body').trigger('disableElement', {'uuid': element.data('uuid')});
        });
        element.find('.btn-enable').on('click', function (event) {
            parent.$('body').trigger('enableElement', {'uuid': element.data('uuid')});
        });
        element.find('.btn-duplicate').on('click', function (event) {
            parent.$('body').trigger('duplicateElement', {'uuid': element.data('uuid')});
        });

        // Trigger event after element is initialized.
        $('body').trigger('bindElement', element.data('uuid'));

        if (bindChildren) {
            element.find('[data-uuid]').each(function() {
                let subElement = $(this);
                bindElement(subElement, false);
            });
        }
    }

    // Bind all elements.
    $(document).ready(function () {
        let elements = $('[data-uuid]');
        elements.each(function() {
            let element = $(this);
            bindElement(element, false);
        });

        $('.btn-create-section').on('click', function (event) {
            event.preventDefault();
            parent.$('body').trigger('createSection', {'section': $(this).data('section')});
        });
    });

    // Refresh element called from iframe parent.
    $('body').on('refreshElement', function (event, elementUuid) {
        let elementSelector = `[data-uuid="${elementUuid}"]`;
        let element = $(elementSelector);
        $('.loadedcontent').remove();
        $('body').append('<div style="display:none!important" class="hidden loadedcontent"></div>');
        $('.loadedcontent').load(window.location.href + ' ' + elementSelector, [], function (data) {
            let newElement = $('.loadedcontent ' + elementSelector);
            element.replaceWith(newElement);
            $('.loadedcontent').remove();
            bindElement(newElement, true);
        });
    });

})(jQuery);
