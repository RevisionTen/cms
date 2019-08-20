$(document).ready(function () {

    $('body').on('bindWidgets', function (event, element) {
        // Bind each picker button.
        $(element).find('[data-file-picker]').click(function (event) {
            event.preventDefault();
            let targetId = $(this).data('filePicker');
            let filePickerUploadField = $(this).data('filePickerUpload');

            let filePickerElement = $(element).find('#cms-file-picker-'+targetId);
            if (filePickerElement.length > 0) {
                $.ajax({
                    url: '/admin/file/picker/' + targetId,
                    context: document.body
                }).done(function(html) {
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
                    });
                });
            }
        });
    });
});
