<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;

class Timing extends Element
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('startDate', DateTimeType::class, [
            'label' => 'element.label.startDate',
            'translation_domain' => 'cms',
            'input' => 'timestamp',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'html5' => true,
            'required' => false,
        ]);

        $builder->add('endDate', DateTimeType::class, [
            'label' => 'element.label.endDate',
            'translation_domain' => 'cms',
            'input' => 'timestamp',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'html5' => true,
            'required' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_timing';
    }
}
