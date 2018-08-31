<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Meta;

use RevisionTen\CMS\Form\Types\UploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class DepartmentType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'label' => 'Department name',
            'required' => false,
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'Department Image',
            'required' => false,
        ]);

        $builder->add('telephone', TextType::class, [
            'label' => 'Phone',
            'required' => false,
            'attr' => [
                'placeholder' => 'A business phone number meant to be the primary contact method for customers. Be sure to include the country code and area code in the phone number.',
            ],
        ]);

        $builder->add('openingHoursSpecification', CollectionType::class, [
            'required' => false,
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
