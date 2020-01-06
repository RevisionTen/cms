const axios = require('axios').default;

import bindForm from './forms';
import onSubmit from './onsubmit';
import updateElement from './element';
import updateCKEditorInstances from "./ckeditor";
import scriptReplace from "./scriptreplace";

function bindModal(url: string)
{
    let modalElement = <HTMLDivElement>document.getElementById('editor-modal');

    if (null === modalElement) {
        return;
    }

    // Execute dynamically inserted script tags in modal.
    scriptReplace(modalElement);

    // Remove content wrapper css class.
    let contentWrapper = modalElement.querySelector('.content-wrapper');
    if (null !== contentWrapper) {
        contentWrapper.classList.remove('content-wrapper');
    }

    // Get first form in content.
    let form = <HTMLFormElement>modalElement.querySelector('form');
    if (null !== form) {
        let formSelector = '#main form';
        if (form.name) {
            // This modals form has a name, always get forms with this name.
            formSelector = 'form[name="'+form.name+'"]';
        }

        // Set the action on the form.
        form.action = url;

        // Bind widgets and conditional form fields.
        bindForm(formSelector, () => {
            bindModal(url);
        });

        // Add an ajax submit handler to the form.
        onSubmit(form, updateCKEditorInstances, (data: any, success: boolean) => {
            if (success && data.success) {
                $(modalElement).modal('hide'); // Todo: Update when Bootstrap 5 releases.
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
                    bindModal(url);
                }
            }
        });
    }

    // Get the original title and set it as the modal title.
    let oldTitle = document.getElementById('editor-modal-title');
    let newTitle = modalElement.querySelector('.content-header .title');
    if (null !== newTitle) {
        oldTitle.innerHTML = newTitle.innerHTML;
    }
    let contentHeader = modalElement.querySelector('.content-header');
    if (null !== contentHeader) {
        contentHeader.remove();
    }

    // Move the modal nav into the modal header.
    let modalNavContent = modalElement.querySelector('.modal-nav-content');
    let modalNav = modalElement.querySelector('.modal-nav');
    if (null !== modalNav && null !== modalNavContent) {
        modalNav.innerHTML = modalNavContent.innerHTML;
        modalNavContent.remove();
    } else if (null !== modalNav) {
        modalNav.innerHTML = ''
    }

    // Show the modal.
    $(modalElement).modal('show'); // Todo: Update when Bootstrap 5 releases.
}

let openModal = function(url: string) {
    let modalContent = document.querySelector('#editor-modal .modal-body');
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
            let newModalContent = <HTMLElement>htmlDoc.querySelector('.content-wrapper .content');
            if (null !== newModalContent) {
                modalContent.insertAdjacentElement('beforeend', newModalContent);
                bindModal(url);
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
