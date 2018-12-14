<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use RevisionTen\CMS\Services\FileService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ManagedUploadType extends AbstractType
{
    /**
     * @var string
     */
    private $fileService;

    /**
     * UploadType constructor.
     *
     * @param FileService $fileService
     */
    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', TextType::class, [
            'label' => 'Title',
            'constraints' => new NotBlank(),
            'required' => true,
            'attr' => [
                'class' => 'file-title',
            ],
        ]);

        $builder->add('file', FileType::class, [
            'required' => false,
            'label' => 'Please select the file you want to upload.',
            'attr' => $options['attr'],
        ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData();

            if (isset($data['delete']) && $data['delete']) {
                // Request to delete, set the file property to null.
                $data['file'] = $this->fileService->deleteFile($data['file']);
                $data['delete'] = null;
            } elseif (isset($data['existingFileUuid'], $data['existingFileVersion']) && null !== $data['existingFileUuid'] && null !== $data['existingFileVersion']) {
                $existingFileUuid = $data['existingFileUuid'];
                $existingFileVersion = (int) $data['existingFileVersion'];

                $data['file'] = $this->fileService->getFile($existingFileUuid, $existingFileVersion);
                $data['existingFileUuid'] = null;
                $data['existingFileVersion'] = null;
            } elseif (isset($data['replaceFile']) && null !== $data['replaceFile']) {
                if (\is_object($data['replaceFile']) && isset($data['file']['uuid'])) {
                    // Store the uploaded file on submit and save the filename in the data.
                    $data['file'] = $this->fileService->replaceFile($data['file'], $data['replaceFile'], $data['title'], $options['upload_dir']);
                    $data['replaceFile'] = null;
                }
            } elseif (isset($data['file']) && null !== $data['file']) {
                if (\is_object($data['file'])) {
                    // Store the uploaded file on submit and save the filename in the data.
                    $data['file'] = $this->fileService->createFile(null, $data['file'], $data['title'], $options['upload_dir']);
                } elseif (\is_array($data['file'])) {
                    // File is already stored.
                    $data['file'] = $this->fileService->updateFile($data['file'], $data['title']);
                }
            } else {
                // Set to null when no image was set and is set.
                $data['file'] = null;
            }

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $data = $event->getData();
            $form = $event->getForm();

            if (null === $data['file']) {
                $form->add('existingFileUuid', HiddenType::class, [
                    'required' => false,
                    'attr' => [
                        'class' => 'existing-file-uuid',
                    ],
                ]);
                $form->add('existingFileVersion', HiddenType::class, [
                    'required' => false,
                    'attr' => [
                        'class' => 'existing-file-version',
                    ],
                ]);
            } else {
                // File exists, display delete and replace form.
                $form->remove('file');

                $form->add('replaceFile', FileType::class, [
                    'required' => false,
                    'label' => 'replace file',
                    'attr' => $options['attr'],
                ]);

                $form->add('delete', CheckboxType::class, [
                    'label' => 'delete the existing file',
                    'required' => false,
                ]);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => true,
            'required' => false,
            'label' => false,
            'attr' => [],
            'upload_dir' => '/uploads/managed-files/',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_managed_upload';
    }
}
