<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;

class OpeningHoursSpecificationType extends AbstractType
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('opens', TimeType::class, [
            'label' => 'openingHoursSpecification.label.opens',
            'translation_domain' => 'cms',
            'required' => false,
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'string',
            'with_seconds' => false,
            'attr' => [
                'placeholder' => 'openingHoursSpecification.placeholder.opens',
            ],
        ]);

        $builder->add('closes', TimeType::class, [
            'label' => 'openingHoursSpecification.label.closes',
            'translation_domain' => 'cms',
            'required' => false,
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'string',
            'with_seconds' => false,
            'attr' => [
                'placeholder' => 'openingHoursSpecification.placeholder.closes',
            ],
        ]);

        $builder->add('dayOfWeek', ChoiceType::class, [
            'label' => 'openingHoursSpecification.label.dayOfWeek',
            'translation_domain' => 'cms',
            'required' => false,
            'expanded' => true,
            'multiple' => true,
            'choices' => [
                'openingHoursSpecification.choices.monday' => 'Monday',
                'openingHoursSpecification.choices.tuesday' => 'Tuesday',
                'openingHoursSpecification.choices.wednesday' => 'Wednesday',
                'openingHoursSpecification.choices.thursday' => 'Thursday',
                'openingHoursSpecification.choices.friday' => 'Friday',
                'openingHoursSpecification.choices.saturday' => 'Saturday',
                'openingHoursSpecification.choices.sunday' => 'Sunday',
            ],
        ]);

        $builder->add('validFrom', DateType::class, [
            'label' => 'openingHoursSpecification.label.validFrom',
            'translation_domain' => 'cms',
            'required' => false,
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'string',
            'format' => DateType::HTML5_FORMAT,
        ]);

        $builder->add('validThrough', DateType::class, [
            'label' => 'openingHoursSpecification.label.validThrough',
            'translation_domain' => 'cms',
            'required' => false,
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'string',
            'format' => DateType::HTML5_FORMAT,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_opening_hours_specification';
    }
}
