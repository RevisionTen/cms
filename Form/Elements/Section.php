<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class Section extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('section', TextType::class, [
            'label' => 'Choose to which layout section this section belongs.',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'cms_section';
    }
}
