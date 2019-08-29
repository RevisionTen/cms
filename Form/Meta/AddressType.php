<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('addressCountry', TextType::class, [
            'label' => 'Country',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'The 2-letter ISO 3166-1 alpha-2 country code',
            ],
        ]);

        $builder->add('addressLocality', TextType::class, [
            'label' => 'Locality',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'Locality',
            ],
        ]);

        $builder->add('addressRegion', TextType::class, [
            'label' => 'Region',
            'required' => false,
            'attr' => [
                'placeholder' => 'Region',
            ],
        ]);

        $builder->add('postalCode', TextType::class, [
            'label' => 'Zipcode',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'Zipcode',
            ],
        ]);

        $builder->add('streetAddress', TextType::class, [
            'label' => 'Street, Nr.',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'Street number, street name, and unit number (if applicable).',
            ],
        ]);
    }
}
