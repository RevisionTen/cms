<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use RevisionTen\CMS\Form\Types\UploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class LocalBusinessMetaType extends AbstractType
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'localBusinessMeta.label.name',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'localBusinessMeta.label.image',
            'translation_domain' => 'cms',
            'required' => true,
        ]);

        $builder->add('telephone', TextType::class, [
            'label' => 'localBusinessMeta.label.telephone',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'localBusinessMeta.placeholder.telephone',
            ],
        ]);

        $builder->add('address', AddressType::class, [
            'label' => 'localBusinessMeta.label.address',
            'translation_domain' => 'cms',
            'required' => true,
            'attr' => [
                'class' => 'well',
            ],
        ]);

        $builder->add('geo', GeoType::class, [
            'label' => 'localBusinessMeta.label.geo',
            'translation_domain' => 'cms',
            'required' => false,
            'attr' => [
                'class' => 'well',
            ],
        ]);

        $builder->add('openingHoursSpecification', CollectionType::class, [
            'required' => false,
            'label' => 'localBusinessMeta.label.openingHoursSpecification',
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

        $builder->add('department', CollectionType::class, [
            'required' => false,
            'label' => 'localBusinessMeta.label.department',
            'translation_domain' => 'cms',
            'entry_type' => DepartmentType::class,
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
