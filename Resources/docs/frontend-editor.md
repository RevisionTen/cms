# Frontend editor

## Javascript events

The frontend editor provides to events you can hook into.
This is needed to rebuild a javascript slider after it has been reloaded for example.

All editor events are triggered on the document.
These events are dispatched as native javascript "CustomEvent" events on the document.

| Event | Detail | Description |
|---|---|---|
| `refreshElement` | elementUuid | Occurs before an element is refreshed. |
| `bindElement` | elementUuid | Occurs after an element is refreshed. |

Example for a listener:
```javascript
document.addEventListener('bindElement', (event) => {
    let elementThatWasReloaded = document.querySelector('[data-uuid="'+event.detail.elementUuid+'"]');
   // Do something.
});
```
