<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadType extends AbstractType
{
    /**
     * @var string
     */
    private $project_dir;

    /**
     * UploadType constructor.
     *
     * @param string $project_dir
     */
    public function __construct(string $project_dir)
    {
        $this->project_dir = $project_dir;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('file', FileType::class, [
            'required' => $options['required'],
            'label' => false,
            'attr' => $options['attr'],
        ]);

        // Store the uploaded file on submit and save the filename in the data.
        $public_dir = $this->project_dir.'/public';
        $upload_dir = $options['upload_dir'];
        $keep_deleted_file = $options['keep_deleted_file'];
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($public_dir, $upload_dir, $keep_deleted_file) {
            $data = $event->getData();

            if (isset($data['delete']) && $data['delete']) {
                // Set the file name property to null.
                $event->setData(null);
                if (!$keep_deleted_file) {
                    // Delete the actual file.
                    $filePath = $public_dir.$data['file'];
                    $filesystem = new Filesystem();
                    if ($filesystem->exists($filePath)) {
                        $filesystem->remove($filePath);
                    }
                }
            } elseif (isset($data['file']) && null !== $data['file']) {
                if (\is_object($data['file'])) {
                    // Save the image and set the field to the upload path.
                    /** @var File $image */
                    $image = $data['file'];

                    // Move the file to the uploads directory.
                    $newFileName = $image->getFilename().'.'.$image->guessExtension();
                    $image->move($public_dir.$upload_dir, $newFileName);

                    // Overwrite uploaded File with file path string.
                    $data = $upload_dir.$newFileName;
                    $event->setData($data);
                } else {
                    $event->setData($data['file']);
                }
            } else {
                // Set to null when no image was set and is set.
                $event->setData(null);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            if (null !== $data) {
                $form->remove('file');
                $form->add('file', HiddenType::class, []);
                $data = [
                    'file' => $data,
                ];
                $form->add('delete', CheckboxType::class, [
                    'label' => 'delete the existing image',
                    'required' => false,
                ]);
            }
            $event->setData($data);
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
            'upload_dir' => '/uploads/files/',
            'keep_deleted_file' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_upload';
    }
}
