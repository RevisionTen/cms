function bindFilePicker(element)
{
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

        let url = '/admin/file/picker';
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
                    let thumbPath = $(this).data('thumbPath');
                    let filePath = $(this).data('filePath');
                    let title = $(this).data('title');
                    let mimeType = $(this).data('mimeType');
                    let size = $(this).data('size');
                    let width = $(this).data('width');
                    let height = $(this).data('height');

                    $('#'+targetId+'_file').val(filePath);
                    $('#'+targetId+'_title').val(title);
                    $('#'+targetId+'_mimeType').val(mimeType);
                    $('#'+targetId+'_size').val(size);
                    $('#'+targetId+'_width').val(width);
                    $('#'+targetId+'_height').val(height);

                    $('#'+filePickerUploadField).removeAttr('required');
                    $('#cms-img-'+targetId+'_file').attr('src', thumbPath).removeClass('d-none');
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
}

$(document).ready(function () {
    bindFilePicker(document);

    $('body').on('bindWidgets', function (event, element) {
        bindFilePicker(element);
    });
});
