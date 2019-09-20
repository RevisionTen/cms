Before: 

```TWIG
{{ render(controller('RevisionTen\\CMS\\Controller\\MenuController::renderMenu', {name: 'Main Menu', alias: alias, language: page.language, website: page.website})) }}
```

After: 

```TWIG
{{ cms_menu({name: 'Main Menu', alias: alias, language: page.language, website: page.website}) }}
```
