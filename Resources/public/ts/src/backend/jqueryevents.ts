/**
 * Trigger jQuery events for backwards compatibility.
 *
 * @param eventName
 * @param detail
 * @param target
 */
let triggerJqueryEvent = function(eventName: string, detail: any, target: any)
{
    $(target).trigger(eventName, detail);
};

export default triggerJqueryEvent;
