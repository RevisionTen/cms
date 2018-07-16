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
        let linkSrc = $(this).attr('href') + '?ajax=1';
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

$(document).ready(function () {
    let body = $('body');

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
        let editorModal = $('#editor-modal');

        editorModal.find('.modal-body').load(linkSrc + ' .content-wrapper', [], function () {
            // Remove content wrapper css class.
            $(this).find('.content-wrapper').removeClass('content-wrapper');
            // Set the action on the form.
            let form = $(this).find('form').first();
            form.attr('action', linkSrc);
            // Ajaxify the form.
            form.postAjax(function(data, type) {
                if ('success' === type && data.success) {
                    editorModal.modal('hide');
                    updateElement(data);
                }
            });
            // Copy the page title.
            let pageTitle = $(this).find('.content-header .title').html();
            $('#editor-modal-title').html(pageTitle);
            // Remove the original title from modal body.
            $(this).find('.content-header').remove();
            // Show the modal.
            editorModal.modal('show');
            // Enable CKEditor.
            $(this).find('.ckeditor').each(function () {
                let textArea = $(this)[0];
                CKEDITOR.replace(textArea, ckeditorConfig);
            });
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
        let url = `/admin/page/shift-element/${pageUuid}/${onVersion}/${elementUuid}/${direction}?ajax=1`;
        $('body').trigger('openAjax', url);
    });
    body.on('deleteElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/delete-element/${pageUuid}/${onVersion}/${elementUuid}?ajax=1`;
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
        let url = `/admin/page/disable-element/${pageUuid}/${onVersion}/${elementUuid}?ajax=1`;
        $('body').trigger('openAjax', url);
    });
    body.on('enableElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/enable-element/${pageUuid}/${onVersion}/${elementUuid}?ajax=1`;
        $('body').trigger('openAjax', url);
    });
    body.on('duplicateElement', function (event, data) {
        let pageUuid = window.pageData.uuid;
        let onVersion = window.pageData.version;
        let elementUuid = data.uuid;
        let url = `/admin/page/duplicate-element/${pageUuid}/${onVersion}/${elementUuid}?ajax=1`;
        $('body').trigger('openAjax', url);
    });

    getPageInfo();
});
