<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Menu;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class Link extends Item
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('title', TextType::class, [
            'label' => 'Title',
        ]);

        $builder->add('url', TextType::class, [
            'label' => 'URL',
        ]);

        $builder->add('targetBlank', CheckboxType::class, [
            'label' => 'Open link in new window',
            'required' => false,
        ]);
    }
}
