<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class GeoType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('latitude', TextType::class, [
            'label' => 'Latitude',
            'required' => false,
            'attr' => [
                'placeholder' => 'The latitude of the business location. The precision should be at least 5 decimal places.',
            ],
        ]);

        $builder->add('longitude', TextType::class, [
            'label' => 'Longitude',
            'required' => false,
            'attr' => [
                'placeholder' => 'The longitude of the business location. The precision should be at least 5 decimal places.',
            ],
        ]);
    }
}
