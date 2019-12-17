# Installation

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


[packagist]: https://packagist.org/packages/revision-ten/cms
[composer]: http://getcomposer.org/
[use-gmail]: https://symfony.com/doc/current/email.html#using-gmail-to-send-emails
[config]: https://github.com/RevisionTen/cms/blob/master/Resources/config/cms.yaml
