<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Element extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('settings', ElementSettings::class, [
            'label' => false,
            'required' => false,
        ]);

        if (isset($options['elementConfig']['styles']) && !empty($options['elementConfig']['styles'])) {
            $builder->add('styles', ChoiceType::class, [
                'label' => 'element.label.styles',
                'translation_domain' => 'cms',
                'choices' => $options['elementConfig']['styles'],
                'choice_translation_domain' => 'messages',
                'multiple' => true,
                'expanded' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'elementConfig' => [],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_element';
    }
}
