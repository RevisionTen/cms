<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class SpacingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('breakpoint', ChoiceType::class, [
            'label' => 'spacing.label.breakpoint',
            'translation_domain' => 'cms',
            'required' => true,
            'choices' => [
                'spacing.choices.xs' => 'xs',
                'spacing.choices.sm' => 'sm',
                'spacing.choices.md' => 'md',
                'spacing.choices.lg' => 'lg',
                'spacing.choices.xl' => 'xl',
            ],
            'placeholder' => 'spacing.placeholder.breakpoint',
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $this->spacingForm($builder, 'top', 'spacing.label.top');
        $this->spacingForm($builder, 'right', 'spacing.label.right');
        $this->spacingForm($builder, 'bottom', 'spacing.label.bottom');
        $this->spacingForm($builder, 'left', 'spacing.label.left');
    }

    public function getBlockPrefix(): string
    {
        return 'cms_spacing';
    }

    private function spacingForm(FormBuilderInterface $builder, string $key, string $label): void
    {
        $builder->add($key, ChoiceType::class, [
            'label' => $label,
            'translation_domain' => 'cms',
            'required' => false,
            'choices' => [
                'spacing.choices.none' => '0',
                'spacing.choices.auto' => 'auto',
                'spacing.choices.extraSmall' => '1',
                'spacing.choices.small' => '2',
                'spacing.choices.medium' => '3',
                'spacing.choices.big' => '4',
                'spacing.choices.extraBig' => '5',
                'spacing.choices.huge' => '6',
            ],
            'placeholder' => $label,
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);
    }
}
