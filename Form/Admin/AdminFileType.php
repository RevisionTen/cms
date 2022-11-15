<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'admin.label.title',
            'translation_domain' => 'cms',
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'admin.label.title',
            ],
        ]);

        $builder->add('language', ChoiceType::class, [
            'label' => 'admin.label.language',
            'translation_domain' => 'cms',
            'choice_translation_domain' => 'messages',
            'choices' => $options['page_languages'],
            'placeholder' => 'admin.label.language',
            'constraints' => new NotBlank(),
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('file', FileType::class, [
            'label' => 'admin.label.fileChoose',
            'translation_domain' => 'cms',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('keepOriginalFileName', CheckboxType::class, [
            'label' => 'admin.label.keepOriginalFileName',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('save', SubmitType::class, [
            'label' => 'admin.btn.saveFile',
            'translation_domain' => 'cms',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('page_languages', [
            'English' => 'en',
            'German' => 'de',
        ]);
    }
}
