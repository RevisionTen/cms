<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use RevisionTen\CMS\Form\Admin\AdminFileType;
use RevisionTen\CMS\Model\FileRead;
use RevisionTen\CMS\Services\FileService;
use RevisionTen\CQRS\Services\AggregateFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use RevisionTen\CMS\Model\File;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_filter;
use function array_reverse;
use function explode;
use function strcmp;
use function usort;

class FileController extends AbstractController
{
    /**
     * @Route("/admin/file/picker", name="cms_file_picker")
     *
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     * @throws Exception
     */
    public function getFilePicker(RequestStack $requestStack, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('file_list');

        $request = $requestStack->getMainRequest();
        if (null === $request) {
            throw new NotFoundHttpException();
        }

        $page = (int) $request->get('page');
        $sortBy = 'created';
        $sortOrder = 'desc';

        $qb = $entityManager->createQueryBuilder();
        $qb
            ->select('entity')
            ->from(FileRead::class, 'entity')
            ->where($qb->expr()->eq('entity.deleted', 0))
            ->addOrderBy('entity.'.$sortBy, $sortOrder)
        ;

        // Add mimeTypes filter.
        $mimeTypes = $request->get('mimeTypes');
        if ($mimeTypes) {
            $mimeTypesList = explode(',', $mimeTypes);
            $fieldQueries = [];
            foreach ($mimeTypesList as $mimeType) {
                $fieldQueries[] = $qb->expr()->eq('entity.mimeType', $qb->expr()->literal($mimeType));
            }
            $qb->andWhere($qb->expr()->orX(...$fieldQueries));
        }

        // Add search query.
        $q = (string) ($request->get('q') ?? '');
        if (!empty($q)) {
            $searchFields = ['title', 'path'];
            $qb = EntityController::addTermQuery($qb, $searchFields, $q);
        }

        // Add current website filter.
        $websiteId = $request->get('currentWebsite');
        if ($websiteId) {
            $qb->andWhere($qb->expr()->eq('entity.website', ':websiteId'))->setParameter('websiteId', $websiteId);
        }

        $limit = 20;
        $offset = $page * $limit;
        $count = count($qb->getQuery()->getScalarResult());
        $totalPages = ceil($count / $limit);

        // Get results.
        $query = $qb->getQuery();
        $query->setMaxResults($limit);
        if (null !== $offset) {
            $query->setFirstResult($offset);
        }
        $paginator = new Paginator($query);
        $files = $paginator->getIterator();

        return $this->render('@CMS/Backend/File/picker.html.twig', [
            'files' => $files,
            'page' => $page,
            'totalPages' => $totalPages,
            'mimeTypes' => $mimeTypes,
        ]);
    }

    /**
     * @Route("/file/{fileUuid}", name="cms_file_show")
     *
     * @param EntityManagerInterface $entityManager
     * @param string                 $fileUuid
     *
     * @return RedirectResponse
     */
    public function fileShow(EntityManagerInterface $entityManager, string $fileUuid): RedirectResponse
    {
        /**
         * @var FileRead|null $fileRead
         */
        $fileRead = $entityManager->getRepository(FileRead::class)->findOneBy(['uuid' => $fileUuid]);

        if (null === $fileRead) {
            throw new NotFoundHttpException();
        }

        return $this->redirect($fileRead->path);
    }

    /**
     * @param AggregateFactory $aggregateFactory
     * @param RequestStack     $requestStack
     *
     * @return Response
     * @throws Exception
     */
    public function files(AggregateFactory $aggregateFactory, RequestStack $requestStack): Response
    {
        $this->denyAccessUnlessGranted('file_list');

        /**
         * @var File[] $files
         */
        $files = $aggregateFactory->findAggregates(File::class);
        // Sort by created date.
        usort($files, 'self::sortByCreated');
        $files = array_reverse($files);

        // Filter files by current website.
        $request = $requestStack->getMainRequest();
        if ($request && $currentWebsite = $request->get('currentWebsite')) {
            $files = array_filter($files, static function ($file) use ($currentWebsite) {
                /**
                 * @var File $file
                 */
                return $file->website === $currentWebsite;
            });
        }

        // Filter deleted files.
        $files = array_filter($files, static function ($file) {
            return $file->deleted === false;
        });

        return $this->render('@CMS/Backend/File/element-list.html.twig', [
            'files' => $files,
        ]);
    }

    /**
     * @Route("/admin/file/create", name="cms_file_create")
     *
     * @param Request $request
     * @param FileService $fileService
     * @param TranslatorInterface $translator
     *
     * @return Response
     */
    public function fileCreate(Request $request, FileService $fileService, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('file_create');

        $uploadDir = '/uploads/managed-files/';
        $currentWebsite = $request->get('currentWebsite');
        $config = $this->getParameter('cms');

        $form = $this->createForm(AdminFileType::class, null, [
            'page_languages' => $config['page_languages'],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $data['file'] = $fileService->createFile(null, $data['file'], $data['title'], $uploadDir, $currentWebsite, $data['language'], $data['keepOriginalFileName']);

            return $this->render('@CMS/Backend/File/create-success.html.twig', $data);
        }

        return $this->render('@CMS/Backend/File/file-form.html.twig', [
            'form' => $form->createView(),
            'title' => $translator->trans('admin.label.fileUpload', [], 'cms'),
        ]);
    }

    /**
     * @Route("/admin/file/edit", name="cms_file_edit")
     *
     * @param Request $request
     * @param FileService $fileService
     * @param AggregateFactory $aggregateFactory
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     *
     * @return Response
     */
    public function fileEdit(Request $request, FileService $fileService, AggregateFactory $aggregateFactory, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('file_edit');

        $fileRead = $entityManager->getRepository(FileRead::class)->find($request->get('id'));
        if (null === $fileRead) {
            return $this->redirect('/admin');
        }
        $fileUuid = $fileRead->getUuid();

        /**
         * @var File $fileAggregate
         */
        $fileAggregate = $aggregateFactory->build($fileUuid, File::class);
        $uploadDir = '/uploads/managed-files/';
        $config = $this->getParameter('cms');

        $form = $this->createForm(AdminFileType::class, [
            'title' => $fileAggregate->title,
            'language' => $fileAggregate->language,
        ], [
            'page_languages' => $config['page_languages'],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $data['file'] = $fileService->replaceFile([
                'uuid' => $fileUuid,
            ], $data['file'], $data['title'], $uploadDir, $data['language'], null, $data['keepOriginalFileName']);

            return $this->render('@CMS/Backend/File/create-success.html.twig', $data);
        }

        return $this->render('@CMS/Backend/File/file-form.html.twig', [
            'form' => $form->createView(),
            'title' => $translator->trans('admin.label.fileEdit', [], 'cms'),
        ]);
    }

    /**
     * @Route("/admin/file/delete", name="cms_file_delete")
     *
     * @param Request $request
     * @param FileService $fileService
     * @param AggregateFactory $aggregateFactory
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     *
     * @return Response
     * @throws Exception
     */
    public function fileDelete(Request $request, FileService $fileService, AggregateFactory $aggregateFactory, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('file_edit');

        $id = $request->get('id');

        /**
         * @var FileRead $fileRead
         */
        $fileRead = $entityManager->getRepository(FileRead::class)->find($id);
        if (null === $fileRead) {
            return $this->redirect('/admin');
        }
        $fileUuid = $fileRead->getUuid();

        if ($request->get('confirm')) {

            $file = $fileService->deleteFile([
                'uuid' => $fileUuid,
            ]);

            if (null === $file) {
                $this->addFlash(
                    'success',
                    $translator->trans('admin.label.fileDeleteSuccess', [], 'cms')
                );

                return $this->redirectToRoute('cms_list_entity', [
                    'entity' => 'FileRead',
                    'sortBy' => $request->query->get('sortBy'),
                    'sortOrder' => $request->query->get('sortOrder'),
                ]);
            }
        }

        return $this->render('@CMS/Backend/File/delete.html.twig', [
            'id' => $id,
            'file' => $fileRead,
            'request' => $request,
        ]);
    }

    private static function sortByCreated($a, $b): int
    {
        $a = (string) $a->created->getTimestamp();
        $b = (string) $b->created->getTimestamp();

        return strcmp($a, $b);
    }
}
