

Before:

```PHP
<?php

declare(strict_types=1);

namespace App\Serializer;

use RevisionTen\CMS\Interfaces\SolrSerializerInterface;
use RevisionTen\CMS\Model\PageStreamRead;
use Solarium\Core\Query\Helper;

class CustomPageSerializer implements SolrSerializerInterface
{
    /**
     * @param PageStreamRead $pageStreamRead
     * @param array          $payload
     * @param Helper         $helper
     *
     * @return array
     * @throws \Exception
     */
    public function serialize(PageStreamRead $pageStreamRead, array $payload, Helper $helper): array
    {
        $doc = [];
        
        $doc['customfield_s'] = $helper->filterControlCharacters('custom field solr content');

        return $doc;
    }
}
```

After:

```PHP
<?php

declare(strict_types=1);

namespace App\Serializer;

use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Serializer\PageSerializer;
use Solarium\QueryType\Update\Query\Query;

class CustomPageSerializer extends PageSerializer
{
    /**
     * @param \Solarium\QueryType\Update\Query\Query $update
     * @param \RevisionTen\CMS\Model\PageStreamRead  $pageStreamRead
     * @param array|NULL                             $payload
     *
     * @return array
     */
    public function serialize(Query $update, PageStreamRead $pageStreamRead, array $payload = null): array
    {
        // Index the page.
        $docs = parent::serialize($update, $pageStreamRead, $payload);
        
        $helper = $update->getHelper();
        $id = $pageStreamRead->getUuid();
        // Get the new document object for this page.
        /** @var \Solarium\Core\Query\DocumentInterface $doc */
        $doc = $docs[$id] ?? null;
        if (null === $doc) {
            return $docs;
        }

        // Add more fields to the solr document...
        $doc->customfield_s = $helper->filterControlCharacters('custom field solr content');

        return $docs;
    }
}
```
