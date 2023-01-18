const axios = require('axios').default;

let bindMenu = function()
{
    // Menu sorting and saving.
    let menus = document.querySelectorAll('.cms-admin-menu-root');

    menus.forEach((menu: HTMLElement) => {
        let menuUuid = menu.dataset.uuid;
        let onVersion = menu.dataset.version;

        // Make menu sortable.
        // @ts-ignore
        $(menu).sortable({
            group: 'serialization',
            containerSelector: '.cms-admin-menu',
            handle: '.cms-admin-menu-item-move'
        });

        // Make menu savable.
        let menuSaveButton = document.querySelector('.btn-save-order[data-uuid="'+menuUuid+'"]');

        if (null !== menuSaveButton) {
            menuSaveButton.addEventListener('click', (event) => {
                event.preventDefault();

                // @ts-ignore
                let data = $(menu).sortable('serialize').get();
                let jsonString = JSON.stringify(data, null, ' ');

                let url = `/admin/menu/save-order/${menuUuid}/${onVersion}?ajax=1`;
                axios.post(url, jsonString)
                    .then(function () {
                        // handle success
                    })
                    .catch(function (error: any) {
                        // handle error
                        console.log(error);
                    })
                    .finally(function () {
                        // always executed
                        window.location.reload();
                    });
            });
        }
    });
};

export default bindMenu;
