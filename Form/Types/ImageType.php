<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'constraints' => new NotBlank(),
            ])
            ->add('image', UploadType::class, [
                'label' => 'Please select the image file you want to upload.',
                'required' => false,
            ])
        ;
    }
}
