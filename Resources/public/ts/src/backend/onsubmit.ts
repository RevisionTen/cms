const axios = require('axios').default;

/**
 * Submit a form via ajax.
 *
 * @param form
 * @param {function} preSubmitCallback
 * @param {function} preSubmitCallback
 * @param {function} postSubmitCallback
 */
let onSubmit = function(form: HTMLFormElement, preSubmitCallback: any, postSubmitCallback: any)
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
};

export default onSubmit;
