const axios = require('axios').default;

function clearValidTrees()
{
    let tree = document.querySelector('#page-tree .cms_tree');
    if (null !== tree) {
        tree.classList.remove('valid-target-tree');
    }
    let treeNodeItems = document.querySelectorAll('#page-tree .cms_tree-node-item');
    treeNodeItems.forEach((treeNodeItem) => {
        treeNodeItem.classList.remove('valid-target');
    });
}

function bindTree()
{
    let treeSaveButton = document.querySelector('.btn-tree-save');

    // @ts-ignore
    let pageTree = $('#page-tree > .cms_tree').sortable({
        group: 'serialization',
        containerSelector: '.cms_tree',
        nested: true,
        itemSelector: '.cms_tree-node',
        placeholder: '<div class="placeholder"><i class="fas fa-arrow-right"></i></div>',
        isValidTarget: function ($item: any, container: any) {
            let containerElement = <HTMLElement>container.el[0];
            return containerElement.classList.contains('valid-target-tree');
        },
        onCancel: ($item: any, container: any, _super: any) => {
            // Clear valid trees.
            clearValidTrees();
        },
        onDrop: ($item: any, container: any, _super: any) => {
            // Clear valid trees.
            clearValidTrees();

            // Show tree save button.
            if (null !== treeSaveButton) {
                treeSaveButton.parentElement.classList.remove('d-none');
            }
        },
        onDragStart: ($item: any, container: any, _super: any) => {
            let element = <HTMLElement>$item[0];
            let elementName = element.dataset.elementName;

            // Sections are not draggable.
            if ('Section' === elementName) {
                return false;
            }

            // Show tree save button.
            if (null !== treeSaveButton) {
                treeSaveButton.parentElement.classList.remove('d-none');
            }

            // Look at every tree and see If this item is allowed.
            let trees = document.querySelectorAll('#page-tree .cms_tree');
            trees.forEach((tree: HTMLElement) => {
                let isChild = $.contains(element, tree); // True if this is a child of the items being dragged.
                let acceptedTypes = 'children' in tree.dataset ? tree.dataset.children : '';

                if (isChild === false && ('all' === acceptedTypes || acceptedTypes.split(',').indexOf(elementName) !== -1)) {
                    tree.classList.add('valid-target-tree');
                    let dropTargets = tree.parentElement.querySelectorAll('.cms_tree-node-item');
                    dropTargets.forEach((dropTarget) => {
                        dropTarget.classList.add('valid-target');
                    });
                } else {
                    tree.classList.remove('valid-target-tree');
                    let dropTargets = tree.parentElement.querySelectorAll('.cms_tree-node-item');
                    dropTargets.forEach((dropTarget) => {
                        dropTarget.classList.remove('valid-target');
                    });
                }
            });
        },
        afterMove: () => {
            // Show tree save button.
            if (null !== treeSaveButton) {
                treeSaveButton.parentElement.classList.remove('d-none');
            }
        }
    });

    // Save page tree on button click.
    if (null !== treeSaveButton) {
        treeSaveButton.addEventListener('click', (event) => {
            event.preventDefault();

            let pageUuidInput = <HTMLInputElement>document.getElementById('tree-pageUuid');
            let pageUuid = pageUuidInput.value;
            let onVersionInput = <HTMLInputElement>document.getElementById('tree-onVersion');
            let onVersion = onVersionInput.value;
            let data = pageTree.sortable('serialize').get();

            // Submit page tree sort data.
            let pageTreeSaveUrl = `/admin/page/save-order/${pageUuid}/${onVersion}?ajax=1`;
            axios.post(pageTreeSaveUrl, data)
                .then(function (response: any) {
                    // handle success.
                })
                .catch(function (error: any) {
                    // handle error
                })
                .finally(function () {
                    // always executed
                    window.location.reload();
                });
        });
    }

    // Highlight elements on page when hovering them in page tree.
    let dropTargets = document.querySelectorAll('.cms_tree-node-item');
    dropTargets.forEach((dropTarget: HTMLElement) => {
        let uuid = dropTarget.parentElement.dataset.uuid;
        let elementSelector = `[data-uuid="${uuid}"]`;
        let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
        let hoveredElement = pageFrame.contentDocument.querySelector(elementSelector);

        dropTarget.addEventListener('mouseenter', () => {
            // Highlight element on page.
            hoveredElement.classList.add('editor-highlight');
        });
        dropTarget.addEventListener('mouseleave', () => {
            // Un-highlight element on page.
            hoveredElement.classList.remove('editor-highlight');
        });
    });
}

let updateTree = function(pageUuid: string, userId: any)
{
    let pageTreeUrl = '/admin/api/page-tree/' + pageUuid + '/' + userId;
    axios.get(pageTreeUrl)
        .then(function (response: any) {
            // handle success
            // Replace pageTree content.
            let pageTree = document.getElementById('page-tree');
            if (null !== pageTree) {
                pageTree.innerHTML = response.data;
            }
            bindTree();
        })
        .catch(function (error: any) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
};

export default updateTree;
