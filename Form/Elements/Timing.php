<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;

class Timing extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('startDate', DateTimeType::class, [
            'label' => 'Start date',
            'input' => 'timestamp',
            'date_widget' => 'single_text',
            'html5' => true,
            'required' => false,
        ]);

        $builder->add('endDate', DateTimeType::class, [
            'label' => 'End date',
            'input' => 'timestamp',
            'date_widget' => 'single_text',
            'html5' => true,
            'required' => false,
        ]);
    }
}
