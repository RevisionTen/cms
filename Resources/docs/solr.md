# Solr

## Configuration

Configure your Solr host, collection and port with `cms.solr_host`, `cms.solr_collection` and `cms.solr_port`.

## Indexing pages in solr

Use `bin/console cms:solr:index` to manually index your pages.

Pages are automatically updated when they are published or unpublished.

If you want to add more data to solr implement the `SolrSerializerInterface` and reference the class in the `solr_serializer` option under the corresponding page template configuration.
