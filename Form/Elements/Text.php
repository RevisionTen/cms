<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Types\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;

class Text extends Element
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

        $builder->add('text', CKEditorType::class, [
            'label' => 'element.label.text',
            'translation_domain' => 'cms',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_text';
    }
}
