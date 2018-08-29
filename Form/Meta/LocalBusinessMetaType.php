<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class LocalBusinessMetaType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'label' => 'Business name',
            'required' => true,
        ]);

        $builder->add('telephone', TextType::class, [
            'label' => 'Phone',
            'required' => true,
            'attr' => [
                'placeholder' => 'A business phone number meant to be the primary contact method for customers. Be sure to include the country code and area code in the phone number.',
            ],
        ]);

        $builder->add('address', AddressType::class, [
            'label' => 'Address',
            'required' => true,
            'attr' => [
                'class' => 'well',
            ],
        ]);

        $builder->add('geo', GeoType::class, [
            'label' => 'Geo',
            'required' => false,
            'attr' => [
                'class' => 'well',
            ],
        ]);

        $builder->add('openingHoursSpecification', CollectionType::class, [
            'label' => 'Opening hours',
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
