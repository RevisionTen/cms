const axios = require('axios').default;

import bindForm from './forms';
import onSubmit from './onsubmit';
import updateElement from './element';
import updateCKEditorInstances from "./ckeditor";
import scriptReplace from "./scriptreplace";

function bindTab(url: string)
{
    let pageEditorTab = document.querySelector('.page-tabs-tab-editor');
    let pageSettingsTab = <HTMLElement>document.querySelector('.page-tabs-tab-settings');

    // Execute dynamically inserted script tags in tab.
    scriptReplace(pageSettingsTab);

    // Get first form in content.
    let form = pageSettingsTab.querySelector('form');
    let formSelector = form.getAttribute('name') ? 'form[name="'+form.getAttribute('name')+'"]' : '#main form';

    // Set the action on the form.
    form.setAttribute('action', url);

    // Bind widgets and conditional form fields.
    bindForm(formSelector, () => {
        bindTab(url);
    });

    // Add an ajax submit handler to the form.
    onSubmit(form, updateCKEditorInstances, (data: any, success: boolean) => {
        // handle success
        if (success && data.success) {
            pageSettingsTab.classList.remove('active');
            pageEditorTab.classList.add('active');
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
                bindTab(url);
            }
        }
    });

    // Add close button.
    let actionsDiv = pageSettingsTab.querySelector('.content-header .global-actions');
    if (null !== actionsDiv) {
        let closeButton = actionsDiv.querySelector('.btn-close-tab');
        if (null === closeButton) {
            actionsDiv.insertAdjacentHTML('beforeend', '<button class="btn btn-sm btn-close-tab"><span class="fa fa-times"></span></button>');
            let closeButton = actionsDiv.querySelector('.btn-close-tab');
            if (null !== closeButton) {
                closeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    // Hide the tab.
                    pageSettingsTab.innerHTML = '';
                    pageSettingsTab.classList.remove('active');
                    pageEditorTab.classList.add('active');
                });
            }
        }
    }

    // Show the tab.
    pageEditorTab.classList.remove('active');
    pageSettingsTab.classList.add('active');
}

let openTab = function(url: string) {
    let pageSettingsTab = document.querySelector('.page-tabs-tab-settings');
    if (null === pageSettingsTab) {
        return;
    }
    // Clear tab content.
    pageSettingsTab.innerHTML = '';

    url += '?ajax=1';

    // Get new modal content.
    axios.get(url)
        .then(function (response: any) {
            // handle success
            let html = response.data;
            // Get element from response.
            let parser = new DOMParser();
            let htmlDoc = <HTMLDocument>parser.parseFromString(html, 'text/html');
            let newPageSettingsTabContent = <HTMLElement>htmlDoc.querySelector('.content-wrapper .content');
            if (null !== newPageSettingsTabContent) {
                pageSettingsTab.insertAdjacentElement('beforeend', newPageSettingsTabContent);
                bindTab(url);
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

export default openTab;
