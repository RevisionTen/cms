# revision-ten/cms

## Installation


Add the bundle and its repositories to your composer.json
```JSON
"prefer-stable": true,
"minimum-stability": "dev",
"require": {
    "revision-ten/cms": "@dev",
},
```


Add the routes to your /config/routes.yaml:
```YAML
cmsbundle_backend:
    resource: "@CmsBundle/Resources/config/backend_routes.yaml"
    prefix:   /
    
cmsbundle_frontend: # Include the frontend routes last (catch-all).
    resource: "@CmsBundle/Resources/config/frontend_routes.yaml"
    prefix:   /

```

Symfony should add the new bundles automatically to your config/bundles.php.
If not add them manually:
```PHP
RevisionTen\CQRS\CqrsBundle::class => ['all' => true],
RevisionTen\CMS\CmsBundle::class => ['all' => true],
RevisionTen\Forms\FormsBundle::class => ['all' => true],
EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
Gregwar\ImageBundle\GregwarImageBundle::class => ['all' => true],
```

Run `bin/console doctrine:schema:update --force` to update your database schema.

## Setup

Make sure your website is able to send emails first. [Use gmail If you can't send emails locally](https://symfony.com/doc/current/email.html#using-gmail-to-send-emails).

Create an admin user with the interactive command: `bin/console cms:user:create`

You will be mailed a QR-code that you need for logging in.

If you lost your QR-code you can use this command to generate a new one: `bin/console cms:user:generate_secret`

Start your web-server and login at `/admin`.

## Configuration

You can find the full configuration in /vendor/revision-ten/cms/Resources/config/cms.yaml.
