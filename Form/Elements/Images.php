<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Types\ImageType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class Images extends Element
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('images', CollectionType::class, [
            'label' => 'element.label.images',
            'translation_domain' => 'cms',
            'entry_type' => ImageType::class,
            'entry_options' => [
                'attr' => [
                    'class' => 'well',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_images';
    }
}
