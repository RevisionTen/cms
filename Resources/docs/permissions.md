# Adding and using permissions

You can add your own permissions to the config:

```YAML
cms:
    permissions:
        # The permission group name can be anything, but see the permission.yaml 
        # for existing group names if you don't want to accidentally override them.
        My custom permission group:
            access_thing: # The permission name can be anything.
                label: 'Access a thing'
```

and use them in your controller for example:

```PHP
public function accessThing(): Response
{
    // Check access.
    $this->denyAccessUnlessGranted('access_thing');
    
    // Code here is only executed if the user has the access_thing permission.
}
```

or your template:

```TWIG
{% if is_granted('access_thing') %}
    <p>Hey, you can access a thing!</p>
{% endif %}
```

## Adding permissions to custom entities

You can also add permissions to your EasyAdmin entity configuration:

```YAML
easy_admin:
    entities:
        Thing:
            class: App\Entity\Thing
            permissions:
                list: 'access_thing' # Defaults to 'list_generic'
                show: 'view_thing' # Defaults to 'show_generic'
                search: 'find_thing' # Defaults to 'search_generic'
                new: 'create_thing' # Defaults to 'create_generic'
                edit: 'edit_thing' # Defaults to 'edit_generic'
                delete: 'delete_thing' # Defaults to 'delete_generic'
            list:
                # You can also add them to your custom actions to avoid displaying 
                # action links the user has no access to.
                actions:
                    - { name: 'custom_thing_route', type: 'route', label: 'Check thing', permission: 'check_thing' }
```

You can also configure permissions for each page template (see [Creating page templates][creating-page-templates]).

[creating-page-templates]: https://github.com/RevisionTen/cms/blob/master/Resources/docs/creating-page-templates.md

## Access to pages

Page access is determined by the alias that is visited, not by the properties of the page.
The language and website of the alias must match the locale and host of the request.
