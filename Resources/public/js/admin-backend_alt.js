
window.pageData = {};


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


});
