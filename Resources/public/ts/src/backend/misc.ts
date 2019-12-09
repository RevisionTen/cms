function updateCKEditorInstances() {
    // Update CKEditor Textarea Element.
    for(let i in (window as any).CKEDITOR.instances) {
        (window as any).CKEDITOR.instances[i].updateElement();
    }
}

// Allow relative urls in trix editor link dialog.
addEventListener("trix-initialize", event => {
    let toolbarElement = <HTMLElement>event.target;
    let inputElement = <HTMLInputElement>toolbarElement.querySelector("input[name=href]");
    inputElement.type = "text";
    inputElement.pattern = "(https?://|/).+";
});
