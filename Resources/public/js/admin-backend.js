function updateCKEditorInstances() {
    // Update CKEditor Textarea Element.
    for(let i in CKEDITOR.instances) {
        CKEDITOR.instances[i].updateElement();
    }
}

/**
 * Submit a form via ajax.
 *
 * @param {function} preSubmitCallback
 * @param {function} preSubmitCallback
 * @param {function} postSubmitCallback
 */
function onSubmit(form, preSubmitCallback, postSubmitCallback) {
    form.submit(function (e) {
        e.preventDefault();

        preSubmitCallback();

        let formData = new FormData(this);
        $.ajax({
            type: form.attr('method'),
            url: form.attr('action'),
            data: formData,
            success: (data) => {
                postSubmitCallback(data, true);
            },
            error: (data) => {
                postSubmitCallback(data, false);
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });
};

window.pageData = {};

function bindLinks()
{
    let body = $('body');

    $('[data-target=modal], [data-target=parent]').on('click', function (event) {
        event.preventDefault();
        body.trigger('openModal', $(this).attr('href'));
    });

    $('[data-target=ajax]').on('click', function (event) {
        event.preventDefault();
        body.trigger('openAjax', $(this).attr('href'));
    });

    $('[data-target=tab]').on('click', function (event) {
        event.preventDefault();
        body.trigger('openTab', $(this).attr('href'));
    });

    // Toogle view modes.

    $('.toggle-tree').on('click', function (event) {
        event.preventDefault();
        $(this).toggleClass('active');
        $('#page-tree').toggleClass('hidden');
    });

    $('.toggle-editor').on('click', function (event) {
        event.preventDefault();
        $(this).toggleClass('active');
        $('#page-frame')[0].contentWindow.$('body').toggleClass('hide-editor');
    });

    $('.toggle-contrast').on('click', function (event) {
        event.preventDefault();
        $(this).toggleClass('active');
        $('#page-frame')[0].contentWindow.$('body').toggleClass('editor-dark');
    });
}



function bindTree() {

    let pageTree = $('#page-tree > .cms_tree').sortable({
        group: 'serialization',
        containerSelector: '.cms_tree',
        nested: true,
        itemSelector: '.cms_tree-node',
        placeholder: '<div class="placeholder"><i class="fas fa-arrow-right"></i></div>',
        isValidTarget: function ($item, container) {
            return container.el.hasClass('valid-target-tree');
        },
        onCancel: function ($item, container, _super, event) {
            // Clear valid trees.
            $('#page-tree .cms_tree').removeClass('valid-target-tree');
            $('#page-tree .cms_tree-node-item').removeClass('valid-target');
        },
        onDrop: function ($item, container, _super, event) {
            // Clear valid trees.
            $('#page-tree .cms_tree').removeClass('valid-target-tree');
            $('#page-tree .cms_tree-node-item').removeClass('valid-target');
        },
        onDragStart: function ($item, container, _super, event) {
            let elementName = $item.data('elementName');

            // Sections are not draggable.
            if ('Section' === elementName) {
                return false;
            }

            // Look at every tree and see If this item is allowed.
            $('#page-tree .cms_tree').each(function () {
                let isChild = $.contains($item[0], $(this)[0]); // True if this is a child of the items beeing dragged.
                let acceptedTypes = typeof $(this).data('children') !== 'undefined' ? $(this).data('children') : '';

                if (isChild === false && ('all' === acceptedTypes || acceptedTypes.split(',').indexOf(elementName) !== -1)) {
                    $(this).addClass('valid-target-tree');
                    $(this).siblings('.cms_tree-node-item').addClass('valid-target');
                } else {
                    $(this).removeClass('valid-target-tree');
                    $(this).siblings('.cms_tree-node-item').removeClass('valid-target');
                }
            });
        },
        afterMove: function () {
            $('.btn-tree-save.hidden').removeClass('hidden');
        }
    });

    // Save page tree on button click.
    $('.btn-tree-save').on('click', function () {
        let pageUuid = $('#tree-pageUuid').val();
        let onVersion = $('#tree-onVersion').val();

        let data = pageTree.sortable('serialize').get();
        let jsonString = JSON.stringify(data, null, ' ');
        // Submit page tree sort data.
        $.ajax({
            type: 'post',
            url: `/admin/page/save-order/${pageUuid}/${onVersion}?ajax=1`,
            data: jsonString,
            success: function (data) {
                window.location.reload();
            },
            error: function (data) {
                window.location.reload();
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    // Highlight elements on page when hovering them in page tree.
    $('.cms_tree-node-item').hover(function (event) {
        // Hover.
        let uuid = $(this).parent().data('uuid');
        let elementSelector = `[data-uuid="${uuid}"]`;

        // Highlight element on page.
        $('#page-frame')[0].contentWindow.$(elementSelector).addClass('editor-highlight');
    }, function (event) {
        // Unhover.
        let uuid = $(this).parent().data('uuid');
        let elementSelector = `[data-uuid="${uuid}"]`;

        // Un-highlight element on page.
        $('#page-frame')[0].contentWindow.$(elementSelector).removeClass('editor-highlight');
    });
}

function getPageInfo()
{
    let pageUuid = $('#pageUuid').val();
    let userId = $('#userId').val();

    // Get Page Info.
    $.ajax({
        url: '/admin/api/page-info/' + pageUuid + '/' + userId,
        context: document.body
    }).done(function(data) {
        window.pageData = data;
        $('#admin-bar').html(data.html);
        bindLinks();
    });

    // Update tree.
    $.ajax({
        url: '/admin/api/page-tree/' + pageUuid + '/' + userId,
        context: document.body
    }).done(function(html) {
        $('#page-tree').html(html);
        bindTree();
    });
}

function updateElement(data)
{
    getPageInfo();

    if (typeof data.refresh !== 'undefined' && data.refresh) {
        // Trigger a refresh event on the page.
        $('#page-frame')[0].contentWindow.$('body').trigger('refreshElement', data.refresh);
    } else if (typeof data.refresh !== 'undefined' && data.refresh === null) {
        // Reload the full page if refresh isset and is null.
        window.location.reload();
    } else if (typeof data.modal !== 'undefined' && data.modal) {
        // Open a modal.
        $('body').trigger('openModal', data.modal);
    }
}

function bindWidgets(element) {
    // Enabled select2.
    element.find('select[data-widget="select2"]:not(.select2-hidden-accessible)').select2({
        theme: 'bootstrap'
    });
    // Enable CKEditor.
    element.find('.ckeditor-custom').each(function () {
        let textArea = $(this)[0];
        let textAreaInstanceName = textArea.id;
        if (CKEDITOR.instances[textAreaInstanceName]) {
            // CKEditor is already running.
            if ($(this).next('.cke').length < 1) {
                // CKEditor instance is old, destroy and replace.
                CKEDITOR.remove(textAreaInstanceName);
                CKEDITOR.replace(textArea, $(this).data('config'));
            }
        } else {
            CKEDITOR.replace(textArea, $(this).data('config'));
        }
    });
    // Bind file chooser.
    element.find('.btn-file-select').on('click', function (event) {
        event.preventDefault();
        let btn = $(this);
        element.find('.btn-file-select').not(btn).removeClass('text-success');
        btn.addClass('text-success');
        let uuid = btn.data('uuid');
        let version = btn.data('version');
        let title = btn.data('title');
        btn.parentsUntil('.tab-content').parent().find('input.existing-file-uuid').val(uuid);
        btn.parentsUntil('.tab-content').parent().find('input.existing-file-version').val(version);
        btn.parentsUntil('form').parent().find('input.file-title').val(title);
    });
    // Bind create buttons.
    element.find('.btn-create').on('click', function (event) {
        $('body').trigger('createElement', {'parent': $(this).data('uuid'), 'elementName': $(this).data('element-name')});
    });

    // Trigger bindWidgets events for custom widgets.
    $('body').trigger('bindWidgets', element);
    let event = new CustomEvent('bindWidgets', {
        detail: {
            element: element[0]
        }
    });
    document.dispatchEvent(event);
}

function bindForm(formSelector, formReloadCallback = false) {
    let form = $(formSelector).first();
    if (form.length > 0) {

        bindWidgets(form);

        form.find('[data-condition]').on('change', function (event) {

            updateCKEditorInstances();

            let formData = new FormData(form[0]);
            formData.set('ignore_validation', 1);
            $.ajax({
                type: form.attr('method'),
                url: form.attr('action'),
                data: formData,
                success: function (data) {
                    let html = $.parseHTML(data, document, true);
                    let newForm = $(html).find(formSelector);
                    if (newForm.length > 0) {
                        form.replaceWith(newForm);
                        if (formReloadCallback) {
                            formReloadCallback();
                        } else {
                            bindForm(formSelector);
                        }
                    }
                },
                error: function (data) {
                    // Failed.
                },
                cache: false,
                contentType: false,
                processData: false
            });
        });
    }
}

function bindModal(linkSrc) {
    let modalElement = $('#editor-modal');

    // Remove content wrapper css class.
    modalElement.find('.content-wrapper').removeClass('content-wrapper');

    // Get first form in content.
    let form = modalElement.find('form').first();
    let formName = form.attr('name');
    let formSelector = '#main form';
    if (formName) {
        // This modals form has a name, always get forms with this name.
        formSelector = 'form[name="'+formName+'"]';
    }

    // Set the action on the form.
    form.attr('action', linkSrc);

    // Bind widgets and conditional form fields.
    bindForm(formSelector, function () {
        bindModal(linkSrc);
    });

    // Add an ajax submit handler to the form.
    onSubmit(form, updateCKEditorInstances, function(data, success) {
        if (success && data.success) {
            modalElement.modal('hide');
            updateElement(data);
        } else {
            let html = $.parseHTML(data, document, true);
            // Get first form from standalone form page.
            let newForm = $(html).find(formSelector).first();
            if (newForm.length > 0) {
                form.replaceWith(newForm);
                bindModal(linkSrc);
            }
        }
    });

    // Get the original title and set it as the modal title.
    $('#editor-modal-title').html(modalElement.find('.content-header .title').html());
    modalElement.find('.content-header').remove();

    // Show the modal.
    modalElement.modal('show');
}

function bindTab(linkSrc) {
    let pageEditorTab = $('.page-tabs-tab-editor');
    let pageSettingsTab = $('.page-tabs-tab-settings');

    // Get first form in content.
    let form = pageSettingsTab.find('form').first();
    let formSelector = form.attr('name') ? '#main form' : 'form[name="'+form.attr('name')+'"]';

    // Set the action on the form.
    form.attr('action', linkSrc);

    // Bind widgets and conditional form fields.
    bindForm(formSelector, function () {
        bindTab(linkSrc);
    });

    // Add an ajax submit handler to the form.
    onSubmit(form, updateCKEditorInstances, function(data, success) {
        if (success && data.success) {
            pageSettingsTab.removeClass('active');
            pageEditorTab.addClass('active');
            updateElement(data);
        } else {
            let html = $.parseHTML(data, document, true);
            // Get first form from standalone form page.
            let newForm = $(html).find(formSelector).first();
            if (newForm.length > 0) {
                form.replaceWith(newForm);
                bindTab(linkSrc);
            }
        }
    });

    // Add close button.
    pageSettingsTab.find('.content-header .global-actions').append('<button class="btn btn-sm btn-close-tab"><span class="fa fa-times"></span></button>');
    pageSettingsTab.find('.btn-close-tab').click(() => {
        pageSettingsTab.html('');
        pageSettingsTab.removeClass('active');
        pageEditorTab.addClass('active');
    });

    // Show the tab.
    pageEditorTab.removeClass('active');
    pageSettingsTab.addClass('active');
}

$(document).ready(function () {
    // Bind the page form.
    bindForm('form[name=page]');

    let body = $('body');

    // Initialize widgets on "edit" and "new" EasyAdmin entity form pages.
    if ((body.hasClass('edit') || body.hasClass('new')) && (body.find('form.edit-form').length > 0 || body.find('form.new-form').length > 0)) {
        bindWidgets(body);
    }

    // Initialize widgets after they have been added to collections.
    $(document).on('easyadmin.collection.item-added', function (event) {
        bindWidgets(body);
    });

    // Menu sorting and saving.
    $('.cms-admin-menu-root').each(function () {
        let menu = $(this);
        let menuUuid = menu.data('uuid');
        let onVersion = menu.data('version');

        // Make menu sortable.
        menu.sortable({
            group: 'serialization',
            containerSelector: '.cms-admin-menu',
            handle: '.cms-admin-menu-item-move'
        });

        // Make menu savable.
        $('.btn-save-order[data-uuid='+menuUuid+']').on('click', function (event) {
            let data = menu.sortable('serialize').get();
            let jsonString = JSON.stringify(data, null, ' ');
            // Submit menu sort data.
            $.ajax({
                type: 'post',
                url: `/admin/menu/save-order/${menuUuid}/${onVersion}?ajax=1`,
                data: jsonString,
                success: function (data) {
                    window.location.reload();
                },
                error: function (data) {
                    window.location.reload();
                },
                cache: false,
                contentType: false,
                processData: false
            });
        });
    });

    if (!body.hasClass('edit-page')) {
        return;
    }

    // Event that opens a link via ajax.
    body.on('openAjax', function (event, linkSrc) {
        $.ajax({
            url: linkSrc + '?ajax=1',
            context: document.body
        }).done(function(data) {
            updateElement(data);
        });
    });

    // Event that opens a bootstrap modal with dynamic content.
    body.on('openModal', function (event, linkSrc) {
        linkSrc = linkSrc + '?ajax=1';
        let modalContent = $('#editor-modal .modal-body');
        $.ajax({
            type: 'GET',
            url: linkSrc,
            success: function (data) {
                let html = $.parseHTML(data, document, true);
                let newModalContent = $(html).find('.content-wrapper .content');
                if (newModalContent.length > 0) {
                    modalContent.html('');
                    newModalContent.appendTo(modalContent);
                    bindModal(linkSrc);
                }
            },
            error: function (data) {
                // Failed.
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    // Event that open the page settings tab with dynamic content.
    body.on('openTab', function (event, linkSrc) {
        linkSrc = linkSrc + '?ajax=1';
        let pageSettingsTab = $('.page-tabs-tab-settings');
        $.ajax({
            type: 'GET',
            url: linkSrc,
            success: function (data) {
                let html = $.parseHTML(data, document, true);
                let newPageSettingsTabContent = $(html).find('.content-wrapper .content');
                if (newPageSettingsTabContent.length > 0) {
                    pageSettingsTab.html('');
                    newPageSettingsTabContent.appendTo(pageSettingsTab);
                    bindTab(linkSrc);
                }
            },
            error: function (data) {
                // Failed.
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    // Fix mobile preview iframe size.
    body.on('iframeReady', function () {
        let pageFrame = $('#page-frame');
        if (!pageFrame.hasClass('size-AutoWidth')) {
            let contentWidth = pageFrame[0].contentWindow.$('body').innerWidth();
            let iframeWidth = pageFrame.width();
            let scrollbarWidth = iframeWidth - contentWidth;
            if (scrollbarWidth > 0) {
                pageFrame.width(iframeWidth + scrollbarWidth);
            }
        }
    });

    // Events to trigger PageController methods.
    body.on('editElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/edit-element/${pageUuid}/${onVersion}/${elementUuid}`;
        $('body').trigger('openModal', url);
    });
    body.on('shiftElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let direction = data.direction;
        let url = `/admin/page/shift-element/${pageUuid}/${onVersion}/${elementUuid}/${direction}`;
        $('body').trigger('openAjax', url);
    });
    body.on('deleteElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/delete-element/${pageUuid}/${onVersion}/${elementUuid}`;
        $('body').trigger('openAjax', url);
    });
    body.on('addElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let parent = data.parent;
        let url = `/admin/page/add-element/${pageUuid}/${onVersion}/${parent}`;
        $('body').trigger('openModal', url);
    });
    body.on('createElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementName = data.elementName;
        let parent = data.parent;
        let url = `/admin/page/create-element/${elementName}/${pageUuid}/${onVersion}/${parent}`;
        $('body').trigger('openModal', url);
    });
    body.on('disableElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/disable-element/${pageUuid}/${onVersion}/${elementUuid}`;
        $('body').trigger('openAjax', url);
    });
    body.on('enableElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/enable-element/${pageUuid}/${onVersion}/${elementUuid}`;
        $('body').trigger('openAjax', url);
    });
    body.on('duplicateElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/duplicate-element/${pageUuid}/${onVersion}/${elementUuid}`;
        $('body').trigger('openAjax', url);
    });
    body.on('createSection', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let section = data.section;
        let url = `/admin/page/create-section/${pageUuid}/${onVersion}/${section}`;
        $('body').trigger('openAjax', url);
    });
    body.on('createColumn', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let parent = data.parent;
        let size = data.size;
        let breakpoint = data.breakpoint;
        let url = `/admin/page/create-column/${pageUuid}/${onVersion}/${parent}/${size}/${breakpoint}`;
        $('body').trigger('openAjax', url);
    });
    body.on('resizeColumn', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let size = data.size;
        let breakpoint = data.breakpoint;
        let url = `/admin/page/resize-column/${pageUuid}/${onVersion}/${elementUuid}/${size}/${breakpoint}`;
        $('body').trigger('openAjax', url);
    });

    getPageInfo();
});
