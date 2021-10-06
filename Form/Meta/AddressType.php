<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('addressCountry', TextType::class, [
            'label' => 'address.label.country',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'address.placeholder.country',
            ],
        ]);

        $builder->add('addressLocality', TextType::class, [
            'label' => 'address.label.locality',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'address.placeholder.locality',
            ],
        ]);

        $builder->add('addressRegion', TextType::class, [
            'label' => 'address.label.region',
            'translation_domain' => 'cms',
            'required' => false,
            'attr' => [
                'placeholder' => 'address.placeholder.region',
            ],
        ]);

        $builder->add('postalCode', TextType::class, [
            'label' => 'address.label.postalCode',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'address.placeholder.postalCode',
            ],
        ]);

        $builder->add('streetAddress', TextType::class, [
            'label' => 'address.label.streetAddress',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'address.placeholder.streetAddress',
            ],
        ]);
    }
}
