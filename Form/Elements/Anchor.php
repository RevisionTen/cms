<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class Anchor extends Element
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('anchor', TextType::class, [
            'label' => 'element.label.anchor',
            'translation_domain' => 'cms',
            'required' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_anchor';
    }
}
