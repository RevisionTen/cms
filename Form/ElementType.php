<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ElementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (isset($options['elementConfig']['class'])) {
            $builder->add('data', $options['elementConfig']['class'], [
                'label' => false,
                'elementConfig' => $options['elementConfig'],
            ]);
        }

        $builder->add('save', SubmitType::class, [
            'label' => 'admin.btn.save',
            'translation_domain' => 'cms',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'elementConfig' => [],
        ]);
    }
}
