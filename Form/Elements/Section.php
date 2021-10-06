<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class Section extends Element
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('section', TextType::class, [
            'label' => 'element.label.section',
            'translation_domain' => 'cms',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_section';
    }
}
