<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use RevisionTen\CMS\Form\Types\UploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class DepartmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('type', ChoiceType::class, [
            'label' => 'department.label.type',
            'translation_domain' => 'cms',
            'required' => false,
            'choices' => [
                'department.choices.autoDealer' => 'AutoDealer',
                'department.choices.autoPartsStore' => 'AutoPartsStore',
                'department.choices.autoRepair' => 'AutoRepair',
                'department.choices.autoBodyShop' => 'AutoBodyShop',
                'department.choices.autoRental' => 'AutoRental',
                'department.choices.gasStation' => 'GasStation',
                'department.choices.autoWash' => 'AutoWash',
                'department.choices.motorcycleDealer' => 'MotorcycleDealer',
                'department.choices.motorcycleRepair' => 'MotorcycleRepair',
            ],
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('name', TextType::class, [
            'label' => 'department.label.name',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'department.label.image',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('telephone', TextType::class, [
            'label' => 'department.label.telephone',
            'translation_domain' => 'cms',
            'required' => false,
            'attr' => [
                'placeholder' => 'department.placeholder.telephone',
            ],
        ]);

        $builder->add('openingHoursSpecification', CollectionType::class, [
            'required' => false,
            'label' => 'department.label.openingHoursSpecification',
            'translation_domain' => 'cms',
            'entry_type' => OpeningHoursSpecificationType::class,
            'entry_options' => [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'well',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'attr' => [
                'class' => 'well',
            ],
        ]);
    }
}
