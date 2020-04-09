import { updatePadding } from "./backend/padding";

const axios = require('axios').default;

// Requires jQuery for column resizing and firing of old jQuery fallback events.
declare var $: any;

let translations = typeof (window as any).translations !== 'undefined' ? (window as any).translations : {
    addElement: 'Add Element',
    delete: 'Delete',
    edit: 'Edit',
    duplicate: 'Duplicate',
    shift: 'Shift',
    enable: 'Enable',
    disable: 'Disable',
    savePadding: 'Save Padding'
};

// Make new column button resizable.
function bindNewColumnButton(element: HTMLElement, newColumnButton: HTMLElement, fullWidth: number) {
    let columnWidth = fullWidth / 12;

    // Destroy if already initialised.
    if ('uiResizable' in newColumnButton.dataset) {
        $(newColumnButton).resizable('destroy');
    }

    // Make resizable.
    $(newColumnButton).resizable({
        maxWidth: fullWidth,
        start: function() {
            newColumnButton.classList.add('editor-resizing');
        },
        stop: function( event: any, ui: any ) {
            let endSize = ui.size.width;
            let colSpan = Math.round(12 / (fullWidth / endSize));
            let colSize = colSpan * columnWidth;
            $(newColumnButton).stop().animate({
                width: colSize
            }, 250, function() {
                // Insert a new column.
                let detail = {'parent': element.dataset.uuid, 'size': colSpan, 'breakpoint': (window as any).bootstrapBreakpoint};
                let customEvent = new CustomEvent('createColumn', {
                    detail: detail
                });
                // Trigger event on parent frame.
                window.parent.document.dispatchEvent(customEvent);
            });
        }
    });
}

// Make column resizable.
function bindColumnResize(element: HTMLElement, fullWidth: number) {
    let columnWidth = fullWidth / 12;

    // Destroy if already initialised.
    if ('uiResizable' in element.dataset) {
        $(element).resizable('destroy');
    }

    // Make resizable.
    $(element).resizable({
        maxWidth: fullWidth,
        start: function() {
            // Remove column classes.
            element.className = element.className.replace(/(^|\s)col-\S+/g, '');
            // Add resizing classes.
            element.classList.add('col-auto');
            element.classList.add('editor-resizing');
        },
        stop: function( event: any, ui: any ) {
            let endSize = ui.size.width;
            let colSpan = Math.round(12 / (fullWidth / endSize));
            let colSize = colSpan * columnWidth;
            $(element).stop().animate({
                width: colSize
            }, 250, function() {
                // Resize column.
                let detail = {'uuid': element.dataset.uuid, 'size': colSpan, 'breakpoint': (window as any).bootstrapBreakpoint};
                let customEvent = new CustomEvent('resizeColumn', {
                    detail: detail
                });
                // Trigger event on parent frame.
                window.parent.document.dispatchEvent(customEvent);
            });
        }
    });
}

/**
 * Trigger jQuery events for backwards compatibility.
 *
 * @param eventName
 * @param detail
 * @param target
 */
function triggerJqueryEvent(eventName: string, detail: any, target: any)
{
    $(target).trigger(eventName, detail);
}

/**
 * Binds a custom event to button elements.
 *
 * @param element
 * @param buttonSelector
 * @param eventName
 * @param detail
 */
function bindButton(element: HTMLElement, buttonSelector: string, eventName: string, detail: any)
{
    let buttons = element.querySelectorAll(buttonSelector);

    buttons.forEach((button: HTMLElement) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            // Trigger event on parent frame.
            let customEvent = new CustomEvent(eventName, {
                detail: detail
            });
            window.parent.document.dispatchEvent(customEvent);
        });
    });
}
/**
 * Binds a custom event to button elements.
 *
 * @param element
 */
function bindCreateButton(element: HTMLElement)
{
    let buttons = element.querySelectorAll('.btn-create');

    buttons.forEach((button: HTMLElement) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            let detail = {
                parent: element.dataset.uuid,
                elementName: button.dataset.elementName
            };
            // Trigger event on parent frame.
            let createElementEvent = new CustomEvent('createElement', {
                detail: detail
            });
            window.parent.document.dispatchEvent(createElementEvent);
        });
    });
}
/**
 * Binds all padding buttons
 *
 * @param element
 */
function bindPaddingButtons(element: HTMLElement)
{
    let addButtons = element.querySelectorAll('.btn-padding');

    addButtons.forEach((button: HTMLElement) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            let uuid = element.dataset.uuid;
            let side = button.dataset.side;
            let action = button.dataset.action;
            updatePadding(uuid, side, action);
        });
    });
}

/**
 * Bind actions to the control elements.
 *
 * @param element
 */
function bindControls(element: HTMLElement)
{
    let uuid = element.dataset.uuid;

    bindCreateButton(element);
    bindPaddingButtons(element);

    bindButton(element, '.btn-add', 'addElement', {'parent': uuid});
    bindButton(element, '.btn-edit', 'editElement', {'uuid': uuid});
    bindButton(element, '.btn-delete', 'deleteElement', {'uuid': uuid});
    bindButton(element, '.btn-up', 'shiftElement', {'uuid': uuid, 'direction': 'up'});
    bindButton(element, '.btn-down', 'shiftElement', {'uuid': uuid, 'direction': 'down'});
    bindButton(element, '.btn-disable', 'disableElement', {'uuid': uuid});
    bindButton(element, '.btn-enable', 'enableElement', {'uuid': uuid});
    bindButton(element, '.btn-duplicate', 'duplicateElement', {'uuid': uuid});
}

function getPaddingButton(side: string, element: HTMLElement): string
{
    let cssProperty = 'top' === side || 'bottom' === side ? 'height' : 'width';
    let padding = window.getComputedStyle(element, null).getPropertyValue('padding-'+side);

    return `<div class="editor-padding editor-padding-${side}" style="${cssProperty}: ${padding};"><div class="editor-padding-controls"><span class="btn-padding" data-side="${side}" data-action="decrease"><span class="fas fa-minus"></span></span><span class="btn-padding" data-side="${side}" data-action="increase"><span class="fas fa-plus"></span></span></div></div>`;
}

/**
 * Bind an editor element.
 *
 * @param element
 * @param bindChildren
 */
function bindElement(element: HTMLElement, bindChildren = false)
{
    // Remove already existing controls.
    let editorElements = element.querySelectorAll('.editor');
    editorElements.forEach((editorElement) => {
        editorElement.remove();
    });

    // Get the type of the element.
    let type = element.dataset.type;

    // Get the state of the element.
    let enabled: boolean = '1' === element.dataset.enabled;

    // Get disabled actions from element options.
    let disabledActions: any = 'disabledActions' in element.dataset ? element.dataset.disabledActions : null;
    if (disabledActions) {
        disabledActions = disabledActions.split(',');
    } else {
        disabledActions = [];
    }

    let label: string = 'label' in element.dataset ? element.dataset.label : '';

    let textColor = 'text-dark';
    if ('Section' === type ||'Row' === type ||'Column' === type) {
        textColor = 'text-white';
    }
    let actionButtonClasses = 'btn btn-sm '+textColor;

    // Build controls.
    let html = `<div class="editor editor-header button-group p-1 text-right w-100 align-self-start">`
        + (label ? `<span class="btn btn-sm float-left ${textColor} font-weight-bold">${label}</span>` : '')
        + ((disabledActions.indexOf('enable') === -1 && enabled === false) ? `<span class="btn-enable ${actionButtonClasses}" title="${translations.enable}"><span class="fa fa-eye-slash"></span></span>` : '')
        + ((disabledActions.indexOf('disable') === -1 && enabled) ? `<span class="btn-disable ${actionButtonClasses}" title="${translations.disable}"><span class="fa fa-eye"></span></span>` : '')
        + (disabledActions.indexOf('shift') === -1 ? `<span class="btn-up ${actionButtonClasses}" title="${translations.shift}"><span class="fa fa-arrow-up"></span></span>` : '')
        + (disabledActions.indexOf('shift') === -1 ? `<span class="btn-down ${actionButtonClasses}" title="${translations.shift}"><span class="fa fa-arrow-down"></span></span>` : '')
        + (disabledActions.indexOf('duplicate') === -1 ? `<span class="btn-duplicate ${actionButtonClasses}" title="${translations.duplicate}"><span class="fas fa-clone"></span></span>` : '')
        + (disabledActions.indexOf('edit') === -1 ? `<span class="btn-edit ${actionButtonClasses}" title="${translations.edit}"><span class="fa fa-edit"></span></span>` : '')
        + (disabledActions.indexOf('delete') === -1 ? `<span class="btn-delete ${actionButtonClasses}" title="${translations.delete}"><span class="fas fa-times"></span></span>` : '')
        +`</div>`;

    if ('Row' !== type && 'Column' !== type && 'Section' !== type) {
        // Add padding controls.
        html += getPaddingButton('top', element);
        html += getPaddingButton('bottom', element);
        html += getPaddingButton('left', element);
        html += getPaddingButton('right', element);
    }

    // Wrap the controls in a column if the element is a row.
    if ('Row' === type) {
        html = '<div class="editor col-12">' + html + '</div>';
    }

    // Add controls.
    element.insertAdjacentHTML('afterbegin', html);

    // Get all available elements.
    let pageElements: any = {};
    if ((window as any).pageElements.constructor === Object && Object.keys((window as any).pageElements).length > 0) {
        pageElements = (window as any).pageElements;
    }

    // Get child elements.
    let children: any = {};
    let childrenNames = 'children' in element.dataset ? element.dataset.children : null;

    if (null !== childrenNames && childrenNames.length > 0) {
        if (childrenNames === 'all') {
            // Get all public elements.
            let publicElements: any = {};
            Object.keys(pageElements).forEach(childName => {
                let child = pageElements[childName];
                if (child.public) {
                    publicElements[childName] = child;
                }
            });
            children = publicElements;
        } else {
            // Get defined child elements for this element.
            for (let childName of childrenNames.split(',')) {
                children[childName] = pageElements[childName];
            }
        }
    }

    // Build child controls.
    let childCount = Object.keys(children).length;
    if (children.constructor === Object && childCount > 0) {
        let editorClasses = [
            'editor',
            'editor-footer',
        ];

        let buttonClasses = [
            'editor-add-button',
            //bg,
            'btn',
            'btn-block',
            'btn-sm',
            'font-weight-bold',
            textColor,
        ];

        if ('Row' === type) {
            editorClasses.push('col-auto align-self-stretch d-flex');
            buttonClasses.push('flex-grow d-flex align-items-center');
        } else {
            editorClasses.push('w-100 align-self-end');
        }

        let addButton = '';
        if (childCount === 1) {
            // Only one possible child type.
            let child = children[Object.keys(children)[0]];
            addButton = `<span data-element-name="${child.name}" class="btn-create `+(buttonClasses.join(' '))+`"><i class="fa fa-plus"></i> ${child.label}</span>`;
        } else {
            // Multiple child types, open add-element modal.
            addButton = `<span class="btn-add `+(buttonClasses.join(' '))+`"><i class="fa fa-plus"></i> ${translations.addElement}</span>`;
        }

        // Add child controls.
        element.insertAdjacentHTML('beforeend', '<div class="'+(editorClasses.join(' '))+'">'+addButton+'</div>');
    }

    // Add grid to section.
    if ('Section' === type) {
        element.insertAdjacentHTML('afterbegin', '<div class="editor editor-grid row"><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div><div class="col"></div></div>');
    }

    // Make new-column button resizable.
    if ('Row' === type) {
        let newColumnButton = <HTMLElement>element.querySelector('.editor-footer.col-auto');
        if (null !== newColumnButton) {
            setTimeout(function () {
                let fullWidth = element.clientWidth;
                bindNewColumnButton(element, newColumnButton, fullWidth);
            }, 1000);
            document.addEventListener('afterChangedBreakpoint', () => {
                let fullWidth = element.clientWidth;
                bindNewColumnButton(element, newColumnButton, fullWidth);
            });
        }
    }

    // Make column resizable.
    if ('Column' === type) {
        setTimeout(function () {
            let fullWidth = element.parentElement.clientWidth;
            bindColumnResize(element, fullWidth);
        }, 1000);
        document.addEventListener('afterChangedBreakpoint', () => {
            let fullWidth = element.parentElement.clientWidth;
            bindColumnResize(element, fullWidth);
        });
    }

    // Bind controls.
    bindControls(element);

    // Trigger event after element is initialized.
    let event = new CustomEvent('bindElement', {
        detail: {
            elementUuid: element.dataset.uuid
        }
    });
    document.dispatchEvent(event);
    // Trigger JQuery event for backwards compatibility.
    triggerJqueryEvent('bindElement', element.dataset.uuid, 'body');

    // Bind children.
    if (bindChildren) {
        let childElements = element.querySelectorAll('[data-uuid]');
        childElements.forEach((childElement: HTMLElement) => {
            bindElement(childElement, false);
        });
    }
}

function refreshElement(elementSelector: string)
{
    let oldElement = document.querySelector(elementSelector);

    // Load updated content of element.
    let url = window.location.href;
    axios.get(url)
        .then(function (response: any) {
            // handle success
            let html = response.data;

            // Get element from response.
            let parser = new DOMParser();
            let htmlDoc = <HTMLDocument>parser.parseFromString(html, 'text/html');
            let newElement = <HTMLElement>htmlDoc.querySelector(elementSelector);

            // Replace old element with new element and bind it.
            oldElement.parentNode.replaceChild(newElement, oldElement);
            bindElement(newElement, true);
        })
        .catch(function (error: any) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}

/**
 * Returns true if the given element is visible.
 *
 * @param element
 */
function isVisible(element: HTMLElement)
{
    return !(element.offsetParent === null)
}

/**
 * Returns true if the element is visible.
 *
 * @param elementSelector
 */
function isElementVisible(elementSelector: string)
{
    let element: HTMLElement = document.querySelector(elementSelector);

    if (null == element) {
        return false;
    }

    return isVisible(element);
}

function detectBreakpoint() {
    let breakpoint = 'xs';
    document.body.insertAdjacentHTML('beforeend', '<div class="detect-breakpoint d-none d-sm-block"></div>');
    document.body.insertAdjacentHTML('beforeend', '<div class="detect-breakpoint d-none d-md-block"></div>');
    document.body.insertAdjacentHTML('beforeend', '<div class="detect-breakpoint d-none d-lg-block"></div>');
    document.body.insertAdjacentHTML('beforeend', '<div class="detect-breakpoint d-none d-xl-block"></div>');

    breakpoint = isElementVisible('.detect-breakpoint.d-sm-block') ? 'sm' : breakpoint;
    breakpoint = isElementVisible('.detect-breakpoint.d-md-block') ? 'md' : breakpoint;
    breakpoint = isElementVisible('.detect-breakpoint.d-lg-block') ? 'lg' : breakpoint;
    breakpoint = isElementVisible('.detect-breakpoint.d-xl-block') ? 'xl' : breakpoint;

    let breakPointDetectors = document.querySelectorAll('.detect-breakpoint');
    breakPointDetectors.forEach((breakPointDetector) => {
        breakPointDetector.remove();
    });

    return breakpoint;
}

document.addEventListener('DOMContentLoaded', () => {
    // Tell iframe parent that the page is fully loaded.
    let iframeReadyEvent = new CustomEvent('iframeReady');
    window.parent.document.dispatchEvent(iframeReadyEvent);

    // Trigger afterResize event after resizing the window.
    let resizing: NodeJS.Timeout = null;
    window.addEventListener('resize', () => {
        if (resizing) {
            clearTimeout(resizing);
        }
        resizing = setTimeout(function() {
            let afterResizeEvent = new CustomEvent('afterResize');
            document.dispatchEvent(afterResizeEvent);
        }, 500);
    });

    // Get current breakpoint.
    (window as any).bootstrapBreakpoint = detectBreakpoint();

    // Get new breakpoint after window resize.
    window.addEventListener('afterResize', () => {
        let newBreakPoint = detectBreakpoint();
        if (newBreakPoint !== (window as any).bootstrapBreakpoint) {
            (window as any).bootstrapBreakpoint = newBreakPoint;
            let afterChangedBreakpointEvent = new CustomEvent('afterChangedBreakpoint');
            document.dispatchEvent(afterChangedBreakpointEvent);
        }
    });

    // Bind all elements.
    let elements = document.querySelectorAll('[data-uuid]');
    elements.forEach((element: HTMLElement) => {
        bindElement(element, false);
    });

    // Bind create section button.
    let createSectionButtons = document.querySelectorAll('.btn-create-section');
    createSectionButtons.forEach((createSectionButton: HTMLElement) => {
        createSectionButton.addEventListener('click', (event) => {
            event.preventDefault();
            // Trigger create section event on parent window.
            let createSectionEvent = new CustomEvent('createSection', {
                detail: {
                    section: createSectionButton.dataset.section
                }
            });
            window.parent.document.dispatchEvent(createSectionEvent);
        });
    });

    // Refresh element (called from iframe parent).
    document.addEventListener('refreshElement', ((event: CustomEvent) => {
        let elementUuid = event.detail.elementUuid;
        let elementSelector = `[data-uuid="${elementUuid}"]`;

        // Trigger jQuery event for backwards compatibility.
        triggerJqueryEvent('refreshElement', elementUuid, 'body');

        refreshElement(elementSelector);
    }) as EventListener);

});

/*
// Dispatch custom "refreshElement" event for pages that do not use jQuery in the frontend.
let event = new CustomEvent('refreshElement', {
    detail: {
        elementUuid: elementUuid
    }
});
document.dispatchEvent(event);
 */
