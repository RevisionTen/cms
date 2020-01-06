function nodeScriptIs(node: HTMLElement) {
    return node.tagName === 'SCRIPT';
}

function nodeScriptClone(node: HTMLElement){
    let script  = document.createElement('script');
    script.text = node.innerHTML;
    for( let i = node.attributes.length-1; i >= 0; i-- ) {
        script.setAttribute( node.attributes[i].name, node.attributes[i].value );
    }

    return script;
}

let scriptReplace = function(node: HTMLElement) {
    if (nodeScriptIs(node) === true && node.parentNode) {
        node.parentNode.replaceChild( nodeScriptClone(node) , node );
    } else {
        let i = 0;
        let children = node.childNodes;
        while (i < children.length) {
            /** @type {HTMLElement} */
            let child = <HTMLElement>children[i++];
            scriptReplace(child);
        }
    }

    return node;
};

export default scriptReplace;
