function bindForm(formSelector: string, formReloadCallback: any = false) {
    let form = <HTMLFormElement>document.querySelector(formSelector);
    if (null !== form) {
        let conditionalChangeElements = form.querySelectorAll('[data-condition]');
        let conditionalClickElements = form.querySelectorAll('button[data-condition]');

        conditionalChangeElements.forEach((conditionalChangeElement: HTMLElement) => {
            conditionalChangeElement.addEventListener('change', () => {
                reloadForm(formSelector, formReloadCallback, form);
            });
        });
        conditionalClickElements.forEach((conditionalClickElement: HTMLElement) => {
            conditionalClickElement.addEventListener('click', () => {
                reloadForm(formSelector, formReloadCallback, form);
            });
        });

        bindWidgets(form);
    }
}

function reloadForm(formSelector: string, formReloadCallback: any, form: HTMLFormElement)
{
    updateCKEditorInstances();

    // Make form half transparent and un-clickable.
    form.classList.add('cms-form-loading');

    let formData = new FormData(form);
    formData.set('ignore_validation', '1');

    let url = form.action;
    axios.post(url, formData)
        .then(function (response: any) {
            // handle success
            let html = response.data;
            // Get element from response.
            let parser = new DOMParser();
            let htmlDoc = <HTMLDocument>parser.parseFromString(html, 'text/html');
            let newForm = <HTMLFormElement>htmlDoc.querySelector(formSelector);
            if (null !== newForm) {
                form.parentNode.replaceChild(newForm, form);
                if (formReloadCallback) {
                    formReloadCallback();
                } else {
                    bindForm(formSelector);
                }
            }
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
 * Submit a form via ajax.
 *
 * @param form
 * @param {function} preSubmitCallback
 * @param {function} preSubmitCallback
 * @param {function} postSubmitCallback
 */
function onSubmit(form: HTMLFormElement, preSubmitCallback: any, postSubmitCallback: any)
{
    form.addEventListener('submit', (event) => {
        event.preventDefault();

        preSubmitCallback();

        let formData = new FormData(form);

        let url = form.action;
        axios.post(url, formData)
            .then(function (response: any) {
                // handle success
                postSubmitCallback(response.data, true);
            })
            .catch(function (response: any) {
                // handle error
                postSubmitCallback(response.data, false);
            })
            .finally(function () {
                // always executed
            });
    });
}
