<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Settings\SpacingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class ElementSettings extends AbstractType
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('paddings', CollectionType::class, [
            'required' => false,
            'label' => 'element.label.paddings',
            'translation_domain' => 'cms',
            'entry_type' => SpacingType::class,
            'entry_options' => [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'well',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'attr' => [
                'class' => 'well',
            ],
            'help' => 'element.help.spacing',
            'help_html' => true,
            'help_attr' => [
                'class' => 'w-100',
            ],
        ]);

        $builder->add('margins', CollectionType::class, [
            'required' => false,
            'label' => 'element.label.margins',
            'translation_domain' => 'cms',
            'entry_type' => SpacingType::class,
            'entry_options' => [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'well',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'attr' => [
                'class' => 'well',
            ],
            'help' => 'element.help.spacing',
            'help_html' => true,
            'help_attr' => [
                'class' => 'w-100',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_element_settings';
    }
}
