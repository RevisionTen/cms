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
}

function getPageInfo()
{
    // Get Page Info.
    let pageUuid = $('#pageUuid').val();
    $.ajax({
        url: '/admin/api/page-info/' + pageUuid,
        context: document.body
    }).done(function(data) {
        window.pageData = data;
        $('#admin-bar').html(data.html);
        bindLinks();
    });
}

function updateElement(data)
{
    getPageInfo();

    if (typeof data.refresh !== 'undefined' && data.refresh) {
        // Trigger a refresh event on the page.
        $('#page-frame')[0].contentWindow.$('body').trigger('refreshElement', data.refresh);
    } else if (typeof data.refresh !== 'undefined' && data.refresh == null) {
        // Reload the full page if refresh isset and is null.
        window.location.reload();
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
}

$(document).ready(function () {
    // Ajaxify if its the page settings form.
    bindPageSettingsForm();

    let body = $('body');

    $('.cms-admin-menu-root').sortable({
        containerSelector: '.cms-admin-menu',
        handle: '.cms-admin-menu-item-move'
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

    getPageInfo();
});
