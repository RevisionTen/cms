let updateCKEditorInstances = function()
{
    // Update CKEditor Textarea Element.
    for(let i in (window as any).CKEDITOR.instances) {
        (window as any).CKEDITOR.instances[i].updateElement();
    }
};

export default updateCKEditorInstances;
