// Submit a form via ajax.
$.fn.postAjax = function(callback) {
    let frm = this;
    frm.submit(function (e) {
        e.preventDefault();

        // Update CKEditor Textarea Element.
        for(let i in CKEDITOR.instances) {
            CKEDITOR.instances[i].updateElement();
        }

        let formData = new FormData(this);
        $.ajax({
            type: frm.attr('method'),
            url: frm.attr('action'),
            data: formData,
            success: function (data) {
                callback(data, 'success');
            },
            error: function (data) {
                callback(data, 'error');
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
    $('[data-target=parent]').on('click', function (event) {
        event.preventDefault();
        let linkSrc = $(this).attr('href');
        parent.$('body').trigger('openModal', linkSrc);
    });

    $('[data-target=ajax]').on('click', function (event) {
        event.preventDefault();
        $('body').trigger('openAjax', $(this).attr('href'));
    });

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
        $('#page-frame')[0].contentWindow.$('body').toggleClass('editor-dark');;
    });
}

function bindTree() {

    let pageTree = $('#page-tree > .tree').sortable({
        group: 'serialization',
        containerSelector: '.tree',
        nested: true,
        itemSelector: '.tree-node',
        placeholder: '<div class="placeholder"><i class="fas fa-arrow-right"></i></div>',
        isValidTarget: function ($item, container) {
            if (container.el.hasClass('valid-target-tree')) {
                return true;
            } else {
                return false;
            }
        },
        onCancel: function ($item, container, _super, event) {
            // Clear valid trees.
            $('#page-tree .tree').removeClass('valid-target-tree');
            $('#page-tree .tree-node-item').removeClass('valid-target');
        },
        onDrop: function ($item, container, _super, event) {
            // Clear valid trees.
            $('#page-tree .tree').removeClass('valid-target-tree');
            $('#page-tree .tree-node-item').removeClass('valid-target');
        },
        onDragStart: function ($item, container, _super, event) {
            let elementName = $item.data('elementName');

            // Sections are not draggable.
            if ('Section' === elementName) {
                return false;
            }

            // Look at every tree and see If this item is allowed.
            $('#page-tree .tree').each(function () {
                let isChild = $.contains($item[0], $(this)[0]); // True if this is a child of the items beeing dragged.
                let acceptedTypes = typeof $(this).data('children') !== 'undefined' ? $(this).data('children') : '';

                if (isChild === false && ('all' === acceptedTypes || acceptedTypes.split(',').indexOf(elementName) !== -1)) {
                    $(this).addClass('valid-target-tree');
                    $(this).siblings('.tree-node-item').addClass('valid-target');
                } else {
                    $(this).removeClass('valid-target-tree');
                    $(this).siblings('.tree-node-item').removeClass('valid-target');
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
    $('.tree-node-item').hover(function (event) {
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

// CKEditor Config.
let blockElements = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
CKEDITOR.stylesSet.add('bootstrap4styles', [
    { name: 'lead', element: 'p', attributes: { 'class': 'lead' } },
    { name: 'align left', element: blockElements, attributes: { 'class': 'text-left' } },
    { name: 'align right', element: blockElements, attributes: { 'class': 'text-right' } },
    { name: 'align center', element: blockElements, attributes: { 'class': 'text-center' } },
    { name: 'justify', element: blockElements, attributes: { 'class': 'text-justify' } },
    { name: 'small', element: 'small' }
]);

const ckeditorConfig = {
    toolbar: [
        { name: 'basicstyles', items: [ 'Source', '-', 'Undo', 'Redo', 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript' ] },
        '/',
        { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'Link', 'Unlink', 'Anchor', 'Table', 'HorizontalRule', 'Iframe' ] },
        '/',
        { name: 'basicstyles', items: [ 'Styles', 'Format' ] },
    ],
    contentsCss: [CKEDITOR.basePath + 'contents.css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css'],
    allowedContent: true,
    stylesSet: 'bootstrap4styles'
};

function bindPageSettingsForm(linkSrc = false) {
    let formSelector = 'form[name=page]';
    let pageForm = $(formSelector);
    if (pageForm.length > 0) {
        pageForm.find('select#page_template').on('change', function (event) {
            // Reload the form with new template.

            // Update CKEditor Textarea Element.
            for(let i in CKEDITOR.instances) {
                CKEDITOR.instances[i].updateElement();
            }

            let formData = new FormData(pageForm[0]);
            formData.set('ignore_validation', 1);
            $.ajax({
                type: pageForm.attr('method'),
                url: pageForm.attr('action'),
                data: formData,
                success: function (data) {
                    newForm = $(data).find(formSelector);
                    if (newForm.length > 0) {
                        pageForm.replaceWith(newForm);
                        if (linkSrc) {
                            bindModal(linkSrc);
                        } else {
                            bindPageSettingsForm();
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
    let editorModal = $('#editor-modal');
    let modalBody = editorModal.find('.modal-body');

    // Remove content wrapper css class.
    modalBody.find('.content-wrapper').removeClass('content-wrapper');
    // Set the action on the form.
    let form = modalBody.find('form').first();
    form.attr('action', linkSrc);
    // Ajaxify the form.
    form.postAjax(function(data, type) {
        if ('success' === type && data.success) {
            editorModal.modal('hide');
            updateElement(data);
        }
    });
    // Copy the page title.
    let pageTitle = modalBody.find('.content-header .title').html();
    $('#editor-modal-title').html(pageTitle);
    // Remove the original title from modal body.
    modalBody.find('.content-header').remove();
    // Show the modal.
    editorModal.modal('show');
    // Enable CKEditor.
    modalBody.find('.ckeditor').each(function () {
        let textArea = $(this)[0];
        CKEDITOR.replace(textArea, ckeditorConfig);
    });
    // Initialize selects.
    let selectsSelector = 'select[data-widget="select2"]:not(.select2-hidden-accessible)';
    modalBody.find(selectsSelector).select2();
    modalBody.on('easyadmin.collection.item-added', function () {
        // Initialize selects.
        modalBody.find(selectsSelector).select2();
    });
    // Ajaxify if its the page settings form.
    bindPageSettingsForm(linkSrc);
    // Bind file chooser.
    modalBody.find('.btn-file-select').on('click', function (event) {
        event.preventDefault();
        let btn = $(this);
        modalBody.find('.btn-file-select').not(btn).removeClass('text-success');
        btn.addClass('text-success');
        let uuid = btn.data('uuid');
        let version = btn.data('version');
        let title = btn.data('title');
        btn.parentsUntil('.tab-content').parent().find('input.existing-file-uuid').val(uuid);
        btn.parentsUntil('.tab-content').parent().find('input.existing-file-version').val(version);
        btn.parentsUntil('form').parent().find('input.file-title').val(title);
    });
}

$(document).ready(function () {
    // Ajaxify if its the page settings form.
    bindPageSettingsForm();

    let body = $('body');

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
        $('#editor-modal .modal-body').load(linkSrc + ' .content-wrapper', [], function () {
            bindModal(linkSrc);
        });
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
