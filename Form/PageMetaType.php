<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PageMetaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('keywords', TextType::class, [
            'label' => 'pageMeta.label.keywords',
            'translation_domain' => 'cms',
            'required' => false,
            'attr' => [
                'placeholder' => 'pageMeta.placeholder.keywords',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_meta';
    }
}
