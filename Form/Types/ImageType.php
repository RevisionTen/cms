<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImageType extends AbstractType
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'image.label.title',
            'translation_domain' => 'cms',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'image.label.image',
            'translation_domain' => 'cms',
            'required' => false,
            'show_file_picker' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_image';
    }
}
