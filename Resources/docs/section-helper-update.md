Before: 

```TWIG
{{ section.render('Body', '@cms/Layout/section-empty.html.twig', page, edit, config) }}
```


After: 

```TWIG
{{ section.render('Body', '@cms/Layout/section-empty.html.twig', page, edit, config, website, alias) }}
```
