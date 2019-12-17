# Creating page templates

Replace the default page templates defined in [`cms.page_templates`][config] with your own page templates.

Each page template has a corresponding twig template defined in the `template` option:
```YAML
page_templates:
    Simple Page:
        template: '@cms/simple-page.html.twig'
```

You can add predefined fields to your page template by overriding the default meta data form type defined in the `metatype` option:
```YAML
page_templates:
    Simple Page:
        ...
        metatype: App\Form\SimpleMetaType
```

You can also indexing the new fields your meta data form type provides by overriding the solr page serializer defined in the 'solr_serializer' option:
```YAML
page_templates:
    Simple Page:
        ...
        solr_serializer: App\Solr\SimplePageSerializer # Must implement RevisionTen\CMS\Interface\SolrSerializerInterface
```

If you want your page templates to suggest custom URLs use the `alias_prefix` option:
```YAML
page_templates:
    Simple Page:
        ...
        alias_prefix:
            en: '/simple-page/' # Will suggest /simple-page/{title}
            de: '/einfache-seite/' # Will suggest /einfache-seite/{title}
```


## Restricting access to certain page templates

You can optionally configure permissions for each page template:

```YAML
cms:
    # Create the new permissions.
    permissions:
        Simple Pages:
            list_simple_page:
                label: 'List simple pages'
            search_simple_page:
                label: 'Search simple pages'
            create_simple_page:
                label: 'Create simple pages'
            edit_simple_page:
                label: 'Edit simple pages'
            delete_simple_page:
                label: 'Delete simple pages'

    # Add the permissions to the page template config.
    page_templates:
        Simple Page:
            template: 'Pages/simple.html.twig'
            permissions:
                list: 'list_simple_page'
                search: 'search_simple_page'
                new: 'create_simple_page'
                edit: 'edit_simple_page'
                delete: 'delete_simple_page'
```
Users must then be granted these permissions in addition to the general page permissions (similar to the ["unanimous" access decision strategy][access-decision-strategy]).

[access-decision-strategy]: https://symfony.com/doc/current/security/voters.html#changing-the-access-decision-strategy
[config]: https://github.com/RevisionTen/cms/blob/master/Resources/config/cms.yaml
