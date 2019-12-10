import getPageInfo from './pageinfo';
import fireCustomEvent from './events';

let updateElement = function(data: any)
{
    getPageInfo();

    if (typeof data.refresh !== 'undefined' && data.refresh) {
        // Trigger a refresh event on the page.
        let pageFrame = <HTMLIFrameElement>document.getElementById('page-frame');
        if (null !== pageFrame) {
            let elementUuid = data.refresh;
            let refreshElementEvent = new CustomEvent('refreshElement', {
                detail: {
                    elementUuid: elementUuid
                }
            });
            pageFrame.contentDocument.dispatchEvent(refreshElementEvent);
        }
    } else if (typeof data.refresh !== 'undefined' && data.refresh === null) {
        // Reload the full page if refresh isset and is null.
        window.location.reload();
    } else if (typeof data.modal !== 'undefined' && data.modal) {
        // Open a modal.
        let detail = {
            url: data.modal
        };
        fireCustomEvent('openModal', detail, document);
    }
};

export default updateElement;
