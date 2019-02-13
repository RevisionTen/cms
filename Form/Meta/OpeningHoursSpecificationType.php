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
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('opens', TimeType::class, [
            'label' => 'Opens',
            'required' => false,
            'html5' => true,
            'input' => 'string',
            'attr' => [
                'placeholder' => 'The time the business location opens, in hh:mm:ss format.',
            ],
        ]);

        $builder->add('closes', TimeType::class, [
            'label' => 'Closes',
            'required' => false,
            'html5' => true,
            'input' => 'string',
            'attr' => [
                'placeholder' => 'The time the business location closes, in hh:mm:ss format.',
            ],
        ]);

        $builder->add('dayOfWeek', ChoiceType::class, [
            'label' => 'Day of Week',
            'required' => false,
            'expanded' => true,
            'multiple' => true,
            'choices' => [
                'Monday' => 'Monday',
                'Tuesday' => 'Tuesday',
                'Wednesday' => 'Wednesday',
                'Thursday' => 'Thursday',
                'Friday' => 'Friday',
                'Saturday' => 'Saturday',
                'Sunday' => 'Sunday',
            ],
        ]);

        $builder->add('validFrom', DateType::class, [
            'label' => 'Valid from',
            'required' => false,
            'html5' => true,
            'input' => 'string',
        ]);

        $builder->add('validThrough', DateType::class, [
            'label' => 'Valid through',
            'required' => false,
            'html5' => true,
            'input' => 'string',
        ]);
    }
}
