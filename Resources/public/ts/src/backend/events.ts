let fireCustomEvent = function(eventName: string, detail: any, target: any)
{
    let openModalEvent = new CustomEvent(eventName, {
        detail: detail
    });
    target.dispatchEvent(openModalEvent);
};

export default fireCustomEvent;
