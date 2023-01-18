
interface Padding {
    breakpoint: string|null,
    top: string|null,
    bottom: string|null,
    left: string|null,
    right: string|null
}

function updatePadding(uuid: string, side: string, action: string): void
{
    let element = document.querySelector('[data-uuid="'+uuid+'"]') as HTMLElement|null;
    if (null === element) {
        return;
    }

    let padding = JSON.parse(element.dataset.padding) as Padding;

    // Get existing css classes for this breakpoint.
    let existingClasses = paddingToClasses(padding);

    // Update padding.
    if (padding.hasOwnProperty(side)) {
        let value = (padding as any)[side] as string|null;

        if ('increase' === action) {
            if (null === value) {
                value = '3';
            } else {
                value = (parseInt(value) + 1).toFixed(0);
            }
            // Cap at 6.
            if (parseInt(value) > 6) {
                value = '6';
            }
        } else if ('decrease' === action) {
            if ('0' === value || null === value) {
                // Remove when lower than zero.
                value = null;
            } else {
                value = (parseInt(value) - 1).toFixed(0);
            }
        }

        (padding as any)[side] = value;
    }

    // Get new classes.
    let newClasses = paddingToClasses(padding);

    // Remove old classes.
    let removedClasses = existingClasses.filter(item => newClasses.indexOf(item) < 0);
    for (let cssClass of removedClasses) {
        if (element.classList.contains(cssClass)) {
            element.classList.remove(cssClass);
        }
    }

    // Add new classes.
    for (let cssClass of newClasses) {
        if (!element.classList.contains(cssClass)) {
            element.classList.add(cssClass);
        }
    }

    // Update padding data.
    element.dataset.padding = JSON.stringify(padding);

    // Update button size.
    let cssProperty = 'top' === side || 'bottom' === side ? 'height' : 'width';
    let computedPadding = window.getComputedStyle(element, null).getPropertyValue('padding-'+side);
    let button = element.querySelector(`.editor-padding-${side}`) as HTMLElement|null;
    if (button) {
        (button.style as any)[cssProperty] = computedPadding;
    }

    // Show save button.
    addSaveButton(element);
}

function addSaveButton(element: HTMLElement)
{
    let saveButton = element.querySelector('.editor-padding-save') as HTMLElement|null;
    if (null === saveButton || saveButton.parentElement !== element) {
        let saveButton = document.createElement('div') as HTMLElement;
        saveButton.className = 'editor-padding-save';
        let saveButtonBtn = document.createElement('span');
        saveButtonBtn.innerText = (window as any).translations.savePadding;
        saveButton.insertAdjacentElement('beforeend', saveButtonBtn);
        element.insertAdjacentElement('afterbegin', saveButton);

        saveButton.addEventListener('click', () => {
            let detail = {
                uuid: element.dataset.uuid,
                padding: element.dataset.padding
            };
            // Trigger event on parent frame.
            let changePaddingEvent = new CustomEvent('changePadding', {
                detail: detail
            });
            window.parent.document.dispatchEvent(changePaddingEvent);

            saveButton.style.opacity = '0.5';
            saveButton.style.pointerEvents = 'none';
        });
    }
}

function paddingToClasses(padding: Padding): string[]
{
    let breakpoint = (padding.hasOwnProperty('breakpoint') ? padding.breakpoint : null) as string|null;

    let classes = [];
    let prefix = '';
    if (breakpoint) {
        prefix = breakpoint+'-';
    }

    if (padding.top) {
        classes.push('pt-'+prefix+padding.top)
    }
    if (padding.bottom) {
        classes.push('pb-'+prefix+padding.bottom)
    }
    if (padding.left) {
        // Bootstrap < 5:
        classes.push('pl-'+prefix+padding.left)
        // Boostrap 5:
        classes.push('ps-'+prefix+padding.left)
    }
    if (padding.right) {
        // Bootstrap < 5:
        classes.push('pr-'+prefix+padding.right)
        // Boostrap 5:
        classes.push('pe-'+prefix+padding.right)
    }

    return classes;
}

export { updatePadding };
