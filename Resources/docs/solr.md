# Solr

## Configuration

Configure your Solr host, collection and port with `cms.solr_host`, `cms.solr_collection` and `cms.solr_port`.

## Indexing pages in solr

Use `bin/console cms:solr:index` to manually index your pages.

Pages are automatically updated when they are published or unpublished.

## Indexing custom page properties

You can use a custom serializer class to add as many properties to your indexed pages as you want.
Just implement the `SolrSerializerInterface` and reference the class in the `solr_serializer` option under the corresponding page template configuration.

A good starting point is to just extend the default `RevisionTen\CMS\Serializer\PageSerializer` class.

Let's say your page has a custom email address field stored in its meta field.

```php
class CustomMetaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class);
    }
}
```

This new field is not automatically added to solr document when the page is indexed.

A typical solr query result for the page might look like this:

```json
{
    "docs":[
        {
            "id":"b615f658-15e8-11ea-bc5c-f01898ec9598",
            "title_s":"Homepage",
            "website_i":1,
            "language_s":"en",
            "template_s":"Simple Page",
            "created_dt":"2019-12-03T16:19:41Z",
            "modified_dt":"2020-01-08T16:24:17Z",
            "url_s":"/"
        }
    ]
}
```

For the email to appear in the indexed data we must add it ourselves:

```php
class CustomPageSerializer extends PageSerializer implements SolrSerializerInterface
{
    public function serialize(Query $update, PageStreamRead $pageStreamRead, array $payload = null): array
    {
        // Call the parent function that indexes all the default fields like the title, website, etc.
        $docs = parent::serialize($update, $pageStreamRead, $payload);
    
        // The $docs array should contain our page, grab it from the array so we can add more fields to it.
        $id = $pageStreamRead->getUuid();   
        $doc = $docs[$id] ?? null;

        if (null === $doc) {
            // The page was not indexed.
            return $docs;
        }

        // $doc is an object of type stdClass.
        // We can declare additional fields dynamically.
        // Let's add our email field from the page meta data. 
        $doc->email_s = $payload['meta']['email'] ?? null;

        // A safer way to add the field is to filter its contents first with the help of the solarium helper.
        $helper = $update->getHelper();
        $doc->email_s = !empty($payload['meta']['email']) ? $helper->filterControlCharacters($payload['meta']['email']) : null;

        return $docs;
    }
}
```

After reindexing the page document should now look like this:

```json
{
    "docs":[
        {
            "id":"b615f658-15e8-11ea-bc5c-f01898ec9598",
            "title_s":"Homepage",
            "website_i":1,
            "language_s":"en",
            "template_s":"Simple Page",
            "created_dt":"2019-12-03T16:19:41Z",
            "modified_dt":"2020-01-08T16:24:17Z",
            "url_s":"/",
            "email_s":"some.email@domain.tld"
        }
    ]
}
```

You can also use the serializer to create more than one solr document from a page by adding as many documents to the
`$docs` array as you want.
A simple use case would be to index special page elements separately in solr (an "event" element inside of a "event group" page for example).
