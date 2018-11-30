<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use RevisionTen\CMS\Services\FileService;
use RevisionTen\CQRS\Services\AggregateFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use RevisionTen\CMS\Model\File;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class FileController.
 *
 * @Route("/admin")
 */
class FileController extends AbstractController
{
    /**
     * @param AggregateFactory $aggregateFactory
     *
     * @return Response
     */
    public function files(AggregateFactory $aggregateFactory): Response
    {
        /** @var File[] $files */
        $files = $aggregateFactory->findAggregates(File::class);
        // Sort by created date.
        usort($files, 'self::sortByCreated');
        $files = array_reverse($files);

        return $this->render('@cms/File/list.html.twig', [
            'files' => $files,
        ]);
    }

    private static function sortByCreated($a, $b)
    {
        $a = (string) $a->created->getTimestamp();
        $b = (string) $b->created->getTimestamp();

        return strcmp($a, $b);
    }

    /**
     * @Route("/file/list", name="cms_file_list")
     *
     * @param AggregateFactory $aggregateFactory
     *
     * @return Response
     */
    public function fileList(AggregateFactory $aggregateFactory): Response
    {
        /** @var File[] $files */
        $files = $aggregateFactory->findAggregates(File::class);
        // Sort by created date.
        usort($files, 'self::sortByCreated');
        $files = array_reverse($files);

        return $this->render('@cms/Admin/File/list.html.twig', [
            'files' => $files,
        ]);
    }

    /**
     * @Route("/file/create", name="cms_file_create")
     *
     * @param Request     $request
     * @param FileService $fileService
     *
     * @return Response
     */
    public function fileCreate(Request $request, FileService $fileService): Response
    {
        $uploadDir = '/uploads/managed-files/';

        $builder = $this->createFormBuilder();

        $builder->add('title', TextType::class, [
            'label' => 'Title',
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'Title',
            ],
        ]);

        $builder->add('file', FileType::class, [
            'label' => 'Please select the file you want to upload.',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Upload file',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $data['file'] = $fileService->createFile(null, $data['file'], $data['title'], $uploadDir);

            return $this->render('@cms/Admin/File/create-success.html.twig', $data);
        }

        return $this->render('@cms/Form/file-form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
