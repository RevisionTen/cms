# Form types

The CMS comes with a number of form types you can use.

#### CKEditorType

This form type provides a CKEditor text field which is fully configurable in the `cms` config.
See `ckeditor_config` in the [default config][config].

#### TrixType

This form type provides the very minimal [Trix][trix] text editor

#### DoctrineType

Use this form type to reference a doctrine entity in your page element. Example:

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

[config]: https://github.com/RevisionTen/cms/blob/master/Resources/config/cms.yaml
[trix]: https://github.com/basecamp/trix
