<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Serializer;

use Doctrine\Common\Collections\Collection;
use RevisionTen\CMS\Interfaces\SolrSerializerInterface;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Services\IndexService;
use Solarium\QueryType\Update\Query\Query;
use function array_values;
use function is_array;

class PageSerializer implements SolrSerializerInterface
{
    public function serialize(Query $update, PageStreamRead $pageStreamRead, array $payload = null): array
    {
        $docs = [];
        $helper = $update->getHelper();

        $id = $pageStreamRead->getUuid();
        // Don't index if no page read exists (page is likely deleted or unpublished).
        if (null === $payload) {
            // Delete page from index.
            $update->addDeleteById($id);
            return [];
        }

        $docs[$id] = $update->createDocument();
        $docs[$id]->id = $id;
        $docs[$id]->ispage_b = true;
        $docs[$id]->title_s = $helper->filterControlCharacters($pageStreamRead->getTitle() ?? '');
        $docs[$id]->website_i = $pageStreamRead->getWebsite();
        $docs[$id]->language_s = $pageStreamRead->getLanguage();
        $docs[$id]->template_s = $helper->filterControlCharacters($pageStreamRead->getTemplate() ?? '');
        $docs[$id]->created_dt = $helper->formatDate($pageStreamRead->getCreated());
        $docs[$id]->modified_dt = $helper->formatDate($pageStreamRead->getModified());

        // Get path of first alias attached to the pageStreamRead entity.
        $path = null;
        /**
         * @var \RevisionTen\CMS\Model\Alias[] $aliases
         */
        $aliases = $pageStreamRead->getAliases();
        if (null !== $aliases) {
            if ($aliases instanceof Collection) {
                $aliases = $aliases->toArray();
            }
            if (!empty($aliases) && is_array($aliases) && null !== array_values($aliases)[0]) {
                $path = array_values($aliases)[0]->getPath();
            }
        }
        $docs[$id]->url_s = $path;

        $docs[$id]->fulltext = IndexService::reducePayload($payload, $helper);

        return $docs;
    }
}
