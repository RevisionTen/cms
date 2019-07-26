# revision-ten/cms

[![Latest Version][badge-release]][release]
[![Software License][badge-license]][license]
[![Total Downloads][badge-downloads]][downloads]

## Installation

#### Install via composer

The preferred method of installation is via [Packagist][] and [Composer][]. Run the following command to install the package and add it as a requirement to your project's `composer.json`:

```bash
composer req revision-ten/cms
```

#### Add routes

Add the routes to your /config/routes.yaml:
```YAML
cmsbundle_backend:
    resource: "@CmsBundle/Resources/config/backend_routes.yaml"
    prefix:   /
    
cmsbundle_frontend: # Include the frontend routes last (catch-all).
    resource: "@CmsBundle/Resources/config/frontend_routes.yaml"
    prefix:   /

```

#### Add the new bundles to the kernel

Symfony should add the new bundles automatically to your config/bundles.php.
If not add them manually:
```PHP
RevisionTen\CQRS\CqrsBundle::class => ['all' => true],
RevisionTen\CMS\CmsBundle::class => ['all' => true],
RevisionTen\Forms\FormsBundle::class => ['all' => true],
```

#### Update you database schema

Run `bin/console doctrine:schema:update --force` to update your database schema.

#### Choose your security configuration

**Delete your security configuration file** (`config/packages/security.yaml`) to use the default security configuration that comes with this bundle, or copy the contents of `/vendor/revision-ten/cms/Resources/config/security.yaml` to your own security config.

#### Update your assets

Run `bin/console assets:install --symlink` to install the bundle assets.

## Setup

Make sure your website is able to send emails first. [Use gmail If you can't send emails locally][use-gmail].

Install the default roles with the command `bin/console cms:install:roles`.

Create an admin user with the command: `bin/console cms:user:create`.

You will be mailed a QR-code that you need for logging in.

If you lost your QR-code you can use this command to generate a new one: `bin/console cms:user:generate_secret`

Start your web-server and login at `/login`.

## Configuration

You can find the full configuration in [`/vendor/revision-ten/cms/Resources/config/cms.yaml`][config].

## Extend your Bootstrap spacing classes

Add a 6er-spacing Bootstrap utility class to your css.

_variables.scss
```SCSS
$spacer: 1rem !default;
$spacers: () !default;
$spacers: map-merge(
    (
        0: 0,
        1: ($spacer * .25),
        2: ($spacer * .5),
        3: $spacer,
        4: ($spacer * 1.5),
        5: ($spacer * 3),
        6: ($spacer * 6), // Additional spacer definition for extra large spacing
    ),
    $spacers
);
```

## Editor javascript events

All editor events are triggered on the body element of the page (using jQuery`s trigger() method).

| Event | Parameters | Description |
|---|---|---|
| `refreshElement` | event, elementUuid | Occurs before an element is refreshed. |
| `bindElement` | event, elementUuid | Occurs after an element is refreshed. |

These events are also dispatched as native javascript "CustomEvent" events on the document.

Example for a listener:
```javascript
document.addEventListener('bindElement', (event) => {
    let elementThatWasReloaded = document.querySelector('[data-uuid="'+event.detail.elementUuid+'"]');
   // Do something.
});
```


## Form types

#### DoctrineType

Use this form type to reference a doctrine entity in your element. Example:

```PHP
$builder->add('Link', DoctrineType::class, [
    'required' => false,
    'multiple' => false,
    'expanded' => false,
    'label' => 'Link',
    'entityClass' => Alias::class,
]);
```

You can also pass a findBy and orderBy parameter to filter your choice list.

```PHP
$builder->add('Link', DoctrineType::class, [
    'required' => false,
    'multiple' => false,
    'expanded' => false,
    'label' => 'Link',
    'entityClass' => Alias::class,
    'findBy' => [
        'priority' => 0.5,
    ],
    'orderBy' => [
        'path' => 'DESC',
    ],
    'filterByWebsite' => true,
]);
```

You can also limit the choice list to entities that match the users current website by using `filterByWebsite` (if the website property on the entity is a relationship) or 'filterByWebsiteId' (if the website property is an id).

You can then use the entity in your twig template. Dumping it will print something like this:

```
array:1 [▼
  "doctrineEntity" => Alias {#1107 ▼
    -id: 1
    -path: "/"
    -pageStreamRead: PageStreamRead {#1147 ▶}
    -redirect: null
    -priority: 0.6
  }
]
```

#### UploadType

Use this form type to upload files. Example:

```PHP
$builder->add('image', UploadType::class, [
    'label' => 'Please select the image file you want to upload.',
    'required' => false,
    'upload_dir' => '/uploads/files/', // Optional, where the files are stored in the public folder.
    'keep_deleted_file' => true, // Optional, "false" deletes the file.
    'constraints' => [ // Optional.
        new Image([
            'maxSize' => '2M',
        ]),
    ],
]);
```

## LanguageAndWebsiteTrait & EasyAdmin entities

You can add the LanguageAndWebsiteTrait to your custom easyadmin entities to show only entities that match the current website.
When you create a new entity in easyadmin the website property will be set to the users current website.

## Caching

The cms uses a shared memory segment to keep the cache consistent across multiple apcu processes.

You can list the shared memory segments with the command: `ipcs -m`
It will output something like this:
```
------ Shared Memory Segments --------
key        shmid      owner      perms      bytes      nattch     status                         
0x00000001 2752520    automb     666        10485760   0   
```
If for whatever reason the SHM can't be created, the cache will be disabled.

## Access to pages

Page access is determined by the alias that is visited, not by the properties of the page.
The language and website of the alias must match the locale and host of the request.

## Indexing pages in solr

Configure your solr collection and port with `cms.solr_collection` and `cms.solr_port`.
Use `bin/console cms:solr:index` to manually index your pages.
Pages are automatically updated when they are published or unpublished.
If you want to add more data to solr implement the `SolrSerializerInterface` and reference the class in the `solr_serializer` option under the corresponding page template configuration.

## Adding and using permissions

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


[packagist]: https://packagist.org/packages/revision-ten/cms
[composer]: http://getcomposer.org/
[use-gmail]: https://symfony.com/doc/current/email.html#using-gmail-to-send-emails

[badge-release]: https://img.shields.io/packagist/v/revision-ten/cms.svg?style=flat-square
[badge-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[badge-downloads]: https://img.shields.io/packagist/dt/revision-ten/cms.svg?style=flat-square

[release]: https://packagist.org/packages/revision-ten/cms
[license]: https://github.com/RevisionTen/cms/blob/master/LICENSE
[downloads]: https://packagist.org/packages/revision-ten/cms

[config]: https://github.com/RevisionTen/cms/blob/master/Resources/config/cms.yaml
