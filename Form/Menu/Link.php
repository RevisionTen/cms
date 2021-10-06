<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Menu;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class Link extends Item
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('title', TextType::class, [
            'label' => 'menu.label.title',
            'translation_domain' => 'cms',
        ]);

        $builder->add('url', TextType::class, [
            'label' => 'menu.label.url',
            'translation_domain' => 'cms',
        ]);

        $builder->add('targetBlank', CheckboxType::class, [
            'label' => 'menu.label.targetBlank',
            'translation_domain' => 'cms',
            'required' => false,
        ]);
    }
}
