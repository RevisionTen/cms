const axios = require('axios').default;

import bindForm from './forms';
import onSubmit from './onsubmit';
import updateElement from './element';
import updateCKEditorInstances from "./ckeditor";
import scriptReplace from "./scriptreplace";

function bindModal(url: string, editorModal: bootstrap.Modal)
{
    let modalElement = <HTMLDivElement>document.getElementById('editor-modal');

    if (null === modalElement) {
        return;
    }

    // Execute dynamically inserted script tags in modal.
    scriptReplace(modalElement);

    // Convert page header to modal header.
    let header = modalElement.querySelector('.sticky-top');
    if (header) {
        header.classList.remove('sticky-top');
        header.classList.add('d-block');
        header.classList.add('modal-header');
        let contentTitle = header.querySelector('.content-title');
        if (contentTitle) {
            let closeBtn = document.createElement('button');
            closeBtn.innerHTML = '<span class="fas fa-times"></span>';
            closeBtn.className = 'btn btn-sm';
            closeBtn.addEventListener('click', (event) => {
                event.preventDefault();
                editorModal.hide();
            });
            contentTitle.insertAdjacentElement('afterbegin', closeBtn);
        }
    }

    // Get first form in content.
    let form = <HTMLFormElement>modalElement.querySelector('form');
    if (null !== form) {
        let formSelector = 'form';
        if (form.name) {
            // This modals form has a name, always get forms with this name.
            formSelector = 'form[name="'+form.name+'"]';
        }

        // Set the action on the form.
        form.action = url;

        // Bind widgets and conditional form fields.
        bindForm(formSelector, () => {
            bindModal(url, editorModal);
        });

        // Add an ajax submit handler to the form.
        onSubmit(form, updateCKEditorInstances, (data: any, success: boolean) => {
            if (success && data.success) {
                editorModal.hide();
                updateElement(data);
            } else {
                let html = data;

                // Get element from response.
                let parser = new DOMParser();
                let htmlDoc = <HTMLDocument>parser.parseFromString(html, 'text/html');
                // Get first form from standalone form page.
                let newForm = <HTMLElement>htmlDoc.querySelector(formSelector);

                // Replace old form with new form and bind it.
                if (null !== newForm) {
                    form.parentNode.replaceChild(newForm, form);
                    bindModal(url, editorModal);
                }
            }
        });
    }

    // Show the modal.
    editorModal.show();

    // Focus autofocus input after modal is shown.
    let autofocusElement = modalElement.querySelector('[autofocus]') as HTMLElement|null;
    if (autofocusElement) {
        autofocusElement.focus();
    }
}

let openModal = function(url: string, editorModal: bootstrap.Modal) {
    let modalContent = document.querySelector('#editor-modal .modal-content');
    if (null === modalContent) {
        return;
    }
    // Clear modal content.
    modalContent.innerHTML = '';

    url += '?ajax=1';

    // Get new modal content.
    axios.get(url)
        .then(function (response: any) {
            // handle success
            let html = response.data;
            // Get element from response.
            let parser = new DOMParser();
            let htmlDoc = <HTMLDocument>parser.parseFromString(html, 'text/html');
            let newModalContent = <HTMLElement>htmlDoc.querySelector('form');
            if (null !== newModalContent) {
                modalContent.insertAdjacentElement('beforeend', newModalContent);
                bindModal(url, editorModal);
            }
        })
        .catch(function (error: any) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
};

export default openModal;
