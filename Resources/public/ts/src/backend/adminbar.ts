const axios = require('axios').default;

function bindLink(buttonSelector: string, eventName: string)
{
    let buttons = document.querySelectorAll(buttonSelector);
    buttons.forEach((button: HTMLElement) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            // Trigger event.
            let customEvent = new CustomEvent(eventName, {
                detail: {
                    url: button.getAttribute('href')
                }
            });
            document.dispatchEvent(customEvent);
        });
    });
}

function bindLinks()
{
    bindLink('[data-target=modal]', 'openModal');
    bindLink('[data-target=parent]', 'openModal');
    bindLink('[data-target=ajax]', 'openAjax');
    bindLink('[data-target=tab]', 'openTab');

    // Toggle view modes.
    let treeToggleButton = document.querySelector('.toggle-tree');
    if (null !== treeToggleButton) {
        treeToggleButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (treeToggleButton.classList.contains('active')) {
                treeToggleButton.classList.remove('active');
            } else {
                treeToggleButton.classList.add('active');
            }

            let pageTree = document.getElementById('page-tree');
            if (null !== pageTree) {
                if (pageTree.classList.contains('hidden')) {
                    pageTree.classList.remove('hidden');
                } else {
                    pageTree.classList.add('hidden');
                }
            }
        });
    }

    // Toggle editor.
    let editorToggleButton = document.querySelector('.toggle-editor');
    if (null !== editorToggleButton) {
        editorToggleButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (editorToggleButton.classList.contains('active')) {
                editorToggleButton.classList.remove('active');
            } else {
                editorToggleButton.classList.add('active');
            }

            let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
            if (null !== pageFrame) {
                let pageFrameFrameBody = pageFrame.contentDocument.body;

                if (pageFrameFrameBody.classList.contains('hide-editor')) {
                    pageFrameFrameBody.classList.remove('hide-editor');
                } else {
                    pageFrameFrameBody.classList.add('hide-editor');
                }
            }
        });
    }

    // Toggle editor contrast.
    let contrastToggleButton = document.querySelector('.toggle-contrast');
    if (null !== contrastToggleButton) {
        contrastToggleButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (contrastToggleButton.classList.contains('active')) {
                contrastToggleButton.classList.remove('active');
            } else {
                contrastToggleButton.classList.add('active');
            }

            let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
            if (null !== pageFrame) {
                let pageFrameFrameBody = pageFrame.contentDocument.body;

                if (pageFrameFrameBody.classList.contains('editor-dark')) {
                    pageFrameFrameBody.classList.remove('editor-dark');
                } else {
                    pageFrameFrameBody.classList.add('editor-dark');
                }
            }
        });
    }

    // Toggle editor size.
    let mainSidebar = document.querySelector('.main-sidebar') as HTMLElement|null;
    let contentWrapper = document.querySelector('.edit-page > .wrapper > .content-wrapper') as HTMLElement|null;
    let maximizeButton = document.querySelector('.btn-maximize-editor') as HTMLSpanElement|null;
    let minimizeButton = document.querySelector('.btn-minimize-editor') as HTMLSpanElement|null;
    if (null !== contentWrapper && null !== mainSidebar && null !== maximizeButton && null !== minimizeButton) {
        maximizeButton.addEventListener('click', (event) => {
            event.preventDefault();
            maximizeButton.classList.add('d-none');
            minimizeButton.classList.remove('d-none');
            mainSidebar.classList.add('d-none');
            contentWrapper.classList.add('p-0');
        });
        minimizeButton.addEventListener('click', (event) => {
            event.preventDefault();
            minimizeButton.classList.add('d-none');
            maximizeButton.classList.remove('d-none');
            mainSidebar.classList.remove('d-none');
            contentWrapper.classList.remove('p-0');
        });
    }
}

let updateAdminBar = function(pageUuid: string, userId: any)
{
    let pageInfoUrl = '/admin/api/page-info/' + pageUuid + '/' + userId;
    axios.get(pageInfoUrl)
        .then(function (response: any) {
            // handle success
            (window as any).pageData = response.data;
            // Replace adminBar content.
            let adminBar = document.getElementById('admin-bar');
            if (null !== adminBar) {
                adminBar.innerHTML = response.data.html;
            }
            bindLinks();
        })
        .catch(function (error: any) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
};

export default updateAdminBar;
