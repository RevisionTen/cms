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

    // Toggle tree.
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
                if (pageTree.classList.contains('d-none')) {
                    pageTree.classList.remove('d-none');
                } else {
                    pageTree.classList.add('d-none');
                }
            }
        });
    }

    // Toggle editor.
    let viewModeToggleButton = document.querySelectorAll('.toggle-view-mode') as NodeListOf<HTMLLinkElement>;
    if (null !== viewModeToggleButton) {
        viewModeToggleButton.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();

                let mode = btn.dataset.mode ?? 'editor';
                let isActive = btn.classList.contains('active');
                if (isActive) {
                    return;
                }

                // Deactivate other buttons.
                viewModeToggleButton.forEach((toggleBtn) => {
                    toggleBtn.classList.remove('active');
                });

                btn.classList.add('active');

                let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
                if (null !== pageFrame) {
                    let pageFrameFrameBody = pageFrame.contentDocument.body;

                    if ('editor' === mode) {
                        pageFrameFrameBody.classList.remove('hide-editor');
                        pageFrameFrameBody.classList.remove('show-spacing-tool');
                    } else if ('spacing' === mode) {
                        pageFrameFrameBody.classList.add('hide-editor');
                        pageFrameFrameBody.classList.add('show-spacing-tool');
                    } else if ('preview' === mode) {
                        pageFrameFrameBody.classList.remove('show-spacing-tool');
                        pageFrameFrameBody.classList.add('hide-editor');
                    }
                }
            });
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
    let sidebar = document.querySelector('.sidebar') as HTMLElement|null;
    let maximizeButton = document.querySelector('.btn-maximize-editor') as HTMLSpanElement|null;
    let minimizeButton = document.querySelector('.btn-minimize-editor') as HTMLSpanElement|null;
    if (null !== sidebar && null !== maximizeButton && null !== minimizeButton) {
        maximizeButton.addEventListener('click', (event) => {
            event.preventDefault();
            maximizeButton.classList.add('d-none');
            minimizeButton.classList.remove('d-none');
            sidebar.classList.add('d-none');
        });
        minimizeButton.addEventListener('click', (event) => {
            event.preventDefault();
            minimizeButton.classList.add('d-none');
            maximizeButton.classList.remove('d-none');
            sidebar.classList.remove('d-none');
        });
    }

    // Bind save buttons.
    let saveBtn = document.querySelector('.btn-save-event') as HTMLElement|null;
    if (saveBtn) {
        window.addEventListener('keydown', function(event: KeyboardEvent) {
            // @ts-ignore
            if((event.ctrlKey || event.metaKey) && event.which == 83) {
                event.preventDefault();
                if (saveBtn.offsetParent !== null) {
                    saveBtn.click();
                }
                return false;
            }
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
