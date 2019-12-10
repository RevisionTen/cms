import updateTree from "./tree";
import updateAdminBar from "./adminbar";

let getPageInfo = function()
{
    let pageUuidInput = <HTMLInputElement>document.getElementById('pageUuid');
    let pageUuid = pageUuidInput ? pageUuidInput.value : null;

    let userIdInput = <HTMLInputElement>document.getElementById('userId');
    let userId = pageUuidInput ? userIdInput.value : null;

    if (pageUuid && userId) {
        // Update admin bar.
        updateAdminBar(pageUuid, userId);

        // Update tree.
        updateTree(pageUuid, userId);
    }
};

export default getPageInfo;
