<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ElementType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (isset($options['elementConfig']['class'])) {
            $builder->add('data', $options['elementConfig']['class'], [
                'label' => false,
                'elementConfig' => $options['elementConfig'],
            ]);
        }

        $builder->add('save', SubmitType::class);
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
