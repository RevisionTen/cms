<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

class ImageSettings extends ElementSettings
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('showOriginal', CheckboxType::class, [
            'label' => 'element.label.showOriginal',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('width', NumberType::class, [
            'label' => 'element.label.width',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('height', NumberType::class, [
            'label' => 'element.label.height',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('scaling', ChoiceType::class, [
            'label' => 'element.label.scaling',
            'translation_domain' => 'cms',
            'placeholder' => 'element.placeholder.scaling',
            'required' => false,
            'choices' => [
                'element.choices.cropResize' => 'cropResize',
                'element.choices.resize' => 'resize',
                'element.choices.scaleResize' => 'scaleResize',
                'element.choices.forceResize' => 'forceResize',
                'element.choices.zoomCrop' => 'zoomCrop',
            ],
            'help' => 'element.help.scaling',
            'help_html' => true,
            'help_attr' => [
                'class' => 'w-100',
            ],
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('grayscale', CheckboxType::class, [
            'label' => 'element.label.grayscale',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('fixOrientation', CheckboxType::class, [
            'label' => 'element.label.fixOrientation',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        parent::buildForm($builder, $options);
    }
}
