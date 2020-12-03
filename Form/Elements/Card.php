<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Types\CKEditorType;
use RevisionTen\CMS\Form\Types\DoctrineType;
use RevisionTen\CMS\Form\Types\UploadType;
use RevisionTen\CMS\Model\Alias;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class Card extends Element
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('imagePosition', ChoiceType::class, [
            'label' => 'element.label.imagePosition',
            'translation_domain' => 'cms',
            'constraints' => new NotBlank(),
            'choices' => [
                'element.choices.top' => 'top',
                'element.choices.bottom' => 'bottom',
                'element.choices.left' => 'left',
                'element.choices.right' => 'right',
                'element.choices.background' => 'background',
                'element.choices.backgroundFitted' => 'background-fitted',
            ],
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('title', TextType::class, [
            'label' => 'element.label.title',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'element.label.image',
            'translation_domain' => 'cms',
            'required' => false,
            'show_file_picker' => true,
        ]);

        $builder->add('text', CKEditorType::class, [
            'required' => false,
            'label' => 'element.label.text',
            'translation_domain' => 'cms',
        ]);

        $builder->add('buttonText', TextType::class, [
            'label' => 'element.label.buttonText',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('page', DoctrineType::class, [
            'required' => false,
            'label' => 'element.label.page',
            'translation_domain' => 'cms',
            'entityClass' => Alias::class,
            'findBy' => [
                'controller' => null,
            ],
            'filterByWebsite' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_card';
    }
}
