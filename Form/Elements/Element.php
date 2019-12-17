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
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'elementConfig' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_element';
    }
}
