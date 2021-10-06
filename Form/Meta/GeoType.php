<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class GeoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('latitude', TextType::class, [
            'label' => 'geo.label.latitude',
            'translation_domain' => 'cms',
            'required' => false,
            'attr' => [
                'placeholder' => 'geo.placeholder.latitude',
            ],
        ]);

        $builder->add('longitude', TextType::class, [
            'label' => 'geo.label.longitude',
            'translation_domain' => 'cms',
            'required' => false,
            'attr' => [
                'placeholder' => 'geo.placeholder.longitude',
            ],
        ]);
    }
}
