
/*
let bindWidgets = new CustomEvent('bindWidgets', {
  detail: {
      element: true
  }
});
document.dispatchEvent(bindWidgets);
*/

function closeFilePickerWindow(filePicker: Element)
{
    document.body.classList.remove('file-picker-open');
    filePicker.classList.remove('d-flex');
    filePicker.classList.add('d-none');
    filePicker.remove();
}

function bindFilePickerWindow(filePicker: Element, filePickerUploadField: string, targetId: string)
{
    let files = filePicker.querySelectorAll('[data-file-path]');

    files.forEach((file: HTMLElement) => {
        file.addEventListener('click', (event) => {
            event.preventDefault();

            let thumbPath = file.dataset.thumbPath;
            let filePath = file.dataset.filePath;
            let title = file.dataset.title;
            let mimeType = file.dataset.mimeType;
            let size = file.dataset.size;
            let width = file.dataset.width;
            let height = file.dataset.height;

            let fileInput = document.getElementById(targetId+'_file');
            if (null !== fileInput) {
                fileInput.setAttribute('value', filePath);
            }

            let titleInput = document.getElementById(targetId+'_title');
            if (null !== titleInput) {
                titleInput.setAttribute('value', title);
            }

            let mimeTypeInput = document.getElementById(targetId+'_mimeType');
            if (null !== mimeTypeInput) {
                mimeTypeInput.setAttribute('value', mimeType);
            }

            let sizeInput = document.getElementById(targetId+'_size');
            if (null !== sizeInput) {
                sizeInput.setAttribute('value', size);
            }

            let widthInput = document.getElementById(targetId+'_width');
            if (null !== widthInput) {
                widthInput.setAttribute('value', width);
            }

            let heightInput = document.getElementById(targetId+'_height');
            if (null !== heightInput) {
                heightInput.setAttribute('value', height);
            }

            let filePickerUploadFieldInput = document.getElementById(filePickerUploadField);
            if (null !== filePickerUploadFieldInput) {
                filePickerUploadFieldInput.removeAttribute('required');
            }

            // Set preview thumbnail.
            let thumb = document.getElementById('cms-img-'+targetId+'_file');
            if (null !== thumb) {
                thumb.setAttribute('src', thumbPath);
                thumb.classList.remove('d-none');
            }

            // Close filePicker window.
            closeFilePickerWindow(filePicker);
        });
    });

    // Bind close button.
    let closeButton = filePicker.querySelector('.cms-file-picker-close');
    if (null !== closeButton) {
        closeButton.addEventListener('click', (event) => {
            event.preventDefault();
            closeFilePickerWindow(filePicker);
        });
    }
}

function bindFilePicker(element: HTMLElement)
{
    let url = '/admin/file/picker';
    let filePickerButtons = element.querySelectorAll('[data-file-picker]');
    let filePickerSelector = '.cms-file-picker';

    // Bind filePicker buttons.
    filePickerButtons.forEach((button: HTMLElement) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            // Destroy existing instances.
            let filePickers = element.querySelectorAll(filePickerSelector);
            filePickers.forEach((filePicker) => {
                filePicker.remove();
            });

            let targetId = button.dataset.filePicker;
            console.log(targetId);
            let filePickerUploadField = button.dataset.filePickerUpload;
            let filePickerMimeTypes = button.dataset.filePickerMimeTypes;

            // Create new file picker element.
            document.body.insertAdjacentHTML('beforeend', '<div class="cms-file-picker d-none flex-column"></div>');

            // Get file picker content.
            let filePicker = document.querySelector(filePickerSelector);
            if (null !== filePicker) {
                let queryParams = filePickerMimeTypes ? {
                    mimeTypes: filePickerMimeTypes
                } : null;
                axios.get(url, {
                    params: queryParams
                })
                    .then(function (response: any) {
                        // handle success
                        document.body.classList.add('file-picker-open');
                        filePicker.classList.remove('d-none');
                        filePicker.classList.add('d-flex');
                        filePicker.innerHTML = response.data;
                        bindFilePickerWindow(filePicker, filePickerUploadField, targetId);
                    })
                    .catch(function (error: any) {
                        // handle error
                        console.log(error);
                    })
                    .finally(function () {
                        // always executed
                    });
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    bindFilePicker(document.body);

    document.addEventListener('bindWidgets', ((event: CustomEvent) => {
        bindFilePicker(event.detail.element);
    }) as EventListener);
});
