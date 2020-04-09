const axios = require('axios').default;

import bindForm from "./backend/forms";
import bindWidgets from "./backend/widgets";
import bindFilePicker from "./backend/filepicker";
import bindMenu from "./backend/menu";
import updateElement from "./backend/element";
import openTab from "./backend/tab";
import openModal from "./backend/modal";
import fireCustomEvent from "./backend/events";
import getPageInfo from "./backend/pageinfo";

let translations = typeof (window as any).translations !== 'undefined' ? (window as any).translations : {
    confirmDelete: 'Delete?',
    confirmDuplicate: 'Duplicate?',
};

document.addEventListener('DOMContentLoaded', () => {
    // Bind menu editor.
    bindMenu();

    // Allow relative urls in trix editor link dialog.
    document.addEventListener("trix-initialize", event => {
        let toolbarElement = <HTMLElement>event.target;
        let inputElement = <HTMLInputElement>toolbarElement.parentElement.querySelector("input[name=href]");
        if (null !== inputElement) {
            inputElement.type = 'text';
            inputElement.pattern = '(https?://|/).+';
        }
    });

    // Bind the page form.
    bindForm('form[name=page]');

    // Initialize widgets on "edit" and "new" EasyAdmin entity form pages.
    if ((document.body.classList.contains('edit') || document.body.classList.contains('new'))) {
        bindWidgets(document.body);
    }
    // Initialize widgets on menu item form.
    let menuItemForm = document.querySelector('form[name="element"]');
    if (menuItemForm) {
        bindWidgets(document.body);
    }
    // Initialize widgets after they have been added to collections.
    $(document).on('easyadmin.collection.item-added', () => {
        bindWidgets(document.body);
    });

    // Bind file picker.
    bindFilePicker(document.body);
    document.addEventListener('bindWidgets', ((event: CustomEvent) => {
        bindFilePicker(event.detail.element);
    }) as EventListener);

    // Confirm delete action in page list.
    let deleteButtons = document.querySelectorAll('.action-cms_delete_aggregate');
    deleteButtons.forEach((deleteButton) => {
        deleteButton.addEventListener('click', () => {
            if (!confirm(translations.confirmDelete)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });
    // Confirm duplicate action in page list.
    let duplicateButtons = document.querySelectorAll('.action-cms_clone_aggregate');
    duplicateButtons.forEach((duplicateButton) => {
        duplicateButton.addEventListener('click', () => {
            if (!confirm(translations.confirmDuplicate)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

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
        openModal(event.detail.url);
    });

    // Event that open the page settings tab with dynamic content.
    document.addEventListener('openTab', (event: CustomEvent) => {
        openTab(event.detail.url);
    });

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

    document.addEventListener('changePadding', (event: CustomEvent) => {
        let pageUuid = (window as any).pageData.uuid;
        let onVersion = (window as any).pageData.version;
        let elementUuid = event.detail.uuid;
        let padding = event.detail.padding;
        let detail = {
            url: `/admin/page/change-element-padding/${pageUuid}/${onVersion}/${elementUuid}/${padding}`
        };
        fireCustomEvent('openAjax', detail, document);
    });

});
