<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

class ImageSettings extends ElementSettings
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('showOriginal', CheckboxType::class, [
            'label' => 'Show original',
            'required' => false,
        ]);

        $builder->add('width', NumberType::class, [
            'label' => 'Width',
            'required' => false,
        ]);

        $builder->add('height', NumberType::class, [
            'label' => 'Height',
            'required' => false,
        ]);

        $builder->add('scaling', ChoiceType::class, [
            'label' => 'Scaling',
            'placeholder' => 'Scaling',
            'required' => false,
            'choices' => [
                'cropResize' => 'cropResize',
                'resize' => 'resize',
                'scaleResize' => 'scaleResize',
                'forceResize' => 'forceResize',
                'zoomCrop' => 'zoomCrop',
            ],
            'help' => '
            <ul>
                <li>cropResize: resizes the image preserving scale (just like "resize") and croping the whitespaces</li>
                <li>resize: resizes the image, will preserve scale and never enlarge it</li>
                <li>scaleResize: resizes the image, will preserve scale, can enlarge it</li>
                <li>forceResize: resizes the image forcing it to be exactly width by height</li>
                <li>zoomCrop: resize and crop the image to fit to given dimensions</li>
                <li><a href="https://github.com/Gregwar/Image#basic-handling" target="_blank">More here</a></li>
            </ul>',
            'help_html' => true,
            'help_attr' => [
                'class' => 'w-100',
            ],
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('grayscale', CheckboxType::class, [
            'label' => 'Grayscale',
            'required' => false,
        ]);

        $builder->add('fixOrientation', CheckboxType::class, [
            'label' => 'Fix orientation',
            'required' => false,
        ]);

        parent::buildForm($builder, $options);
    }
}
