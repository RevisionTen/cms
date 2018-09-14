<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CQRS\Services\AggregateFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use RevisionTen\CMS\Model\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FileController
 */
class FileController extends Controller
{

    /**
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function fileList(AggregateFactory $aggregateFactory): Response
    {
        /** @var File[] $files */
        $files = $aggregateFactory->findAggregates(File::class);

        return $this->render('@cms/File/list.html.twig', [
            'files' => $files,
        ]);
    }
}
