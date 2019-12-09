const axios = require('axios').default;
import "./backend/file-picker";
import "./backend/misc";
import "./backend/forms";
import "./backend/tree";
import "./backend/admin-bar";

function fireCustomEvent(eventName: string, detail: any, target: any)
{
    let openModalEvent = new CustomEvent(eventName, {
        detail: detail
    });
    target.dispatchEvent(openModalEvent);
}

function updateElement(data: any)
{
    getPageInfo();

    if (typeof data.refresh !== 'undefined' && data.refresh) {
        // Trigger a refresh event on the page.
        let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
        if (null !== pageFrame) {
            let refreshElementEvent = new CustomEvent('refreshElement', {
                detail: {
                    elementUuid: data.refresh
                }
            });
            pageFrame.contentDocument.dispatchEvent(refreshElementEvent);
        }
    } else if (typeof data.refresh !== 'undefined' && data.refresh === null) {
        // Reload the full page if refresh isset and is null.
        window.location.reload();
    } else if (typeof data.modal !== 'undefined' && data.modal) {
        // Open a modal.
        let detail = {
            url: data.modal
        };
        fireCustomEvent('openModal', detail, document);
    }
}

function bindTab(url: string)
{
    let pageEditorTab = document.querySelector('.page-tabs-tab-editor');
    let pageSettingsTab = document.querySelector('.page-tabs-tab-settings');

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

document.addEventListener('DOMContentLoaded', () => {
    // Only execute on editor pages.
    if (!document.body.classList.contains('edit-page')) {
        return;
    }

    getPageInfo();

    // Fix mobile preview iframe size.
    document.addEventListener('iframeReady', () => {
        let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
        if (null !== pageFrame && !pageFrame.classList.contains('size-AutoWidth')) {
            let contentWidth = pageFrame.contentDocument.body.clientWidth;
            let iframeWidth = pageFrame.clientWidth;
            let scrollbarWidth = iframeWidth - contentWidth;
            if (scrollbarWidth > 0) {
                pageFrame.setAttribute('width', (iframeWidth + scrollbarWidth) + 'px');
            }
        }
    });

    // Event that opens a link via ajax.
    document.addEventListener('openAjax', (event: CustomEvent) => {
        let url = event.detail.url + '?ajax=1';
        axios.get(url)
            .then(function (response: any) {
                // handle success
                updateElement(response.data);
            })
            .catch(function (error: any) {
                // handle error
                console.log(error);
            })
            .finally(function () {
                // always executed
            });
    });

    // Event that opens a bootstrap modal with dynamic content.
    document.addEventListener('openModal', (event: CustomEvent) => {
        let url = event.detail.url;
        let modalContent = document.querySelector('#editor-modal .modal-body');
        if (null === modalContent) {
            return;
        }
        // Clear modal content.
        modalContent.innerHTML = '';

        // Get new modal content.
        axios.get(url + '?ajax=1')
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
    });

    // Event that open the page settings tab with dynamic content.
    document.addEventListener('openTab', (event: CustomEvent) => {
        let url = event.detail.url;
        let pageSettingsTab = document.querySelector('.page-tabs-tab-settings');
        if (null === pageSettingsTab) {
            return;
        }
        // Clear tab content.
        pageSettingsTab.innerHTML = '';

        // Get new modal content.
        axios.get(url + '?ajax=1')
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
    });

    // Bind events.
    document.addEventListener('editElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let detail = {
            url: `/admin/page/edit-element/${pageUuid}/${onVersion}/${elementUuid}`
        };
        fireCustomEvent('openModal', detail, document);
    });

    document.addEventListener('shiftElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let direction = event.detail.direction;
        let detail = {
            url: `/admin/page/shift-element/${pageUuid}/${onVersion}/${elementUuid}/${direction}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('deleteElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let detail = {
            url: `/admin/page/delete-element/${pageUuid}/${onVersion}/${elementUuid}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('addElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let parent = event.detail.parent;
        let detail = {
            url: `/admin/page/add-element/${pageUuid}/${onVersion}/${parent}`
        };
        fireCustomEvent('openModal', detail, document);
    });

    document.addEventListener('createElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let parent = event.detail.parent;
        let elementName = event.detail.elementName;
        let detail = {
            url: `/admin/page/create-element/${elementName}/${pageUuid}/${onVersion}/${parent}`
        };
        fireCustomEvent('openModal', detail, document);
    });

    document.addEventListener('disableElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let detail = {
            url: `/admin/page/disable-element/${pageUuid}/${onVersion}/${elementUuid}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('enableElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let detail = {
            url: `/admin/page/enable-element/${pageUuid}/${onVersion}/${elementUuid}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('duplicateElement', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let detail = {
            url: `/admin/page/duplicate-element/${pageUuid}/${onVersion}/${elementUuid}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('createSection', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let section = event.detail.section;
        let detail = {
            url: `/admin/page/create-section/${pageUuid}/${onVersion}/${section}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('createColumn', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let parent = event.detail.parent;
        let size = event.detail.size;
        let breakpoint = event.detail.breakpoint;
        let detail = {
            url: `/admin/page/create-column/${pageUuid}/${onVersion}/${parent}/${size}/${breakpoint}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

    document.addEventListener('resizeColumn', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let size = event.detail.size;
        let breakpoint = event.detail.breakpoint;
        let detail = {
            url: `/admin/page/resize-column/${pageUuid}/${onVersion}/${elementUuid}/${size}/${breakpoint}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

});
