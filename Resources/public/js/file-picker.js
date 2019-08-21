$(document).ready(function () {

    $('body').on('bindWidgets', function (event, element) {
        // Bind each picker button.
        $(element).find('[data-file-picker]').click(function (event) {
            event.preventDefault();
            let targetId = $(this).data('filePicker');
            let filePickerUploadField = $(this).data('filePickerUpload');
            let filePickerMimeTypes = $(this).data('filePickerMimeTypes');

            // Destroy existing instances.
            let filePickerSelector = '.cms-file-picker';
            $(filePickerSelector).remove();
            // Create new file picker element.
            $('body').append('<div class="cms-file-picker d-none flex-column"></div>');

            let url = '/admin/file/picker/' + targetId;
            let query = null;
            if (filePickerMimeTypes) {
                query = {
                    mimeTypes: filePickerMimeTypes
                };
            }

            let filePickerElement = $(filePickerSelector);
            if (filePickerElement.length > 0) {
                $.ajax({
                    method: 'GET',
                    url: url,
                    data: query,
                    context: document.body
                }).done(function (html) {
                    $('body').addClass('file-picker-open');
                    filePickerElement.removeClass('d-none').addClass('d-flex');
                    filePickerElement.html(html);

                    filePickerElement.find('[data-file-path]').click(function (event) {
                        event.preventDefault();
                        let filePath = $(this).data('filePath');
                        let thumbPath = $(this).data('thumbPath');
                        $('#'+targetId).val(filePath);
                        $('#'+filePickerUploadField).removeAttr('required');
                        $('#cms-img-'+targetId).attr('src', thumbPath).removeClass('d-none');
                        $('body').removeClass('file-picker-open');
                        filePickerElement.removeClass('d-flex').addClass('d-none');
                        filePickerElement.remove();
                    });

                    filePickerElement.find('.cms-file-picker-close').click(function (event) {
                        event.preventDefault();
                        $('body').removeClass('file-picker-open');
                        filePickerElement.removeClass('d-flex').addClass('d-none');
                        filePickerElement.remove();
                    });
                });
            }
        });
    });
});
