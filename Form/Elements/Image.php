<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Types\UploadType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class Image extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('settings', ImageSettings::class, [
            'label' => false,
            'required' => false,
        ]);

        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'constraints' => new NotBlank(),
            ])
            ->add('image', UploadType::class, [
                'label' => 'Please select the image file you want to upload.',
                'required' => false,
                'show_file_picker' => true,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'cms_image';
    }
}
