<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PageMetaType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('keywords', TextType::class, [
            'label' => 'Keywords',
            'required' => false,
            'attr' => [
                'placeholder' => 'keyword, keyword, ...',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_meta';
    }
}
