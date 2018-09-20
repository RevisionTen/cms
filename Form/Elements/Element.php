<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Element extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('settings', ElementSettings::class, [
            'label' => false, //'Settings',
            'required' => false,
        ]);

        if (isset($options['elementConfig']['styles']) && !empty($options['elementConfig']['styles'])) {
            $builder->add('styles', ChoiceType::class, [
                'label' => 'Choose how this element is displayed.',
                'choices' => $options['elementConfig']['styles'],
                'multiple' => true,
                'expanded' => true,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'elementConfig' => [],
        ]);
    }
}
