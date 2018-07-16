<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

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
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($public_dir) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['delete']) && $data['delete']) {
                $event->setData(null);
            } elseif (isset($data['file']) && null !== $data['file']) {
                if (is_object($data['file'])) {
                    // Save the image and set the field to the upload path.
                    $upload_dir = '/uploads/files/';

                    /** @var File $image */
                    $image = $data['file'];

                    // Move the file to the uploads directory.
                    $newFileName = $image->getFilename().'.'.$image->guessExtension();
                    /** @var File $storedFiled */
                    $storedFiled = $image->move($public_dir.$upload_dir, $newFileName);

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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($public_dir) {
            $data = $event->getData();
            $form = $event->getForm();
            if (null !== $data) {
                $form->remove('file');
                $form->add('file', HiddenType::class, []);
                $data = [
                    'file' => $data,
                ];
                $form->add('delete', CheckboxType::class, [
                    'label' => 'delete the existing file',
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
        ]);
    }
}
