<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class Row extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('styles', ChoiceType::class, [
            'label' => 'Choose how the columns in this row are displayed.',
            'choices' => $options['elementConfig']['styles'],
            'multiple' => true,
            'expanded' => true,
        ]);
    }
}
