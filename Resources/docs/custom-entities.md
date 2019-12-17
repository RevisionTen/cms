# Adding custom entities

You can add custom entities to the `easy_admin` configuration.
See [EasyAdmin: Your First Backend][your-first-backend] for more info.

## Restricting access to custom entities

You can add your own permissions to restrict access to your custom entities.
See [Adding permissions to custom entities][custom-entity-permissions] for more info.

## Restricting entities to certain websites

You can add the LanguageAndWebsiteTrait to your custom easyadmin entities to show only entities that match the current website.
When you create a new entity in easyadmin the website property will be set to the users current website.


[your-first-backend]: https://symfony.com/doc/current/bundles/EasyAdminBundle/book/your-first-backend.html
[custom-entity-permissions]: https://github.com/RevisionTen/cms/blob/master/Resources/docs/permissions.md#adding-permissions-to-custom-entities
