<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Settings\SpacingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class ElementSettings extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $helpHtml = '<div class="row w-100"><div class="col-xs-4 col-4">Extra Small = 0.25 x text height<br/> Small = 0.5 x text height</div><div class="col-xs-4 col-4">Medium = 1 x text height<br/> Big = 1.5 x text height</div><div class="col-xs-4 col-4">Extra Big = 3 x text height<br/> Huge = 6 x text height</div></div>';

        $builder->add('paddings', CollectionType::class, [
            'required' => false,
            'label' => 'Padding',
            'entry_type' => SpacingType::class,
            'entry_options' => [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'well',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'attr' => [
                'class' => 'well',
            ],
            'help' => $helpHtml,
        ]);

        $builder->add('margins', CollectionType::class, [
            'required' => false,
            'label' => 'Margin',
            'entry_type' => SpacingType::class,
            'entry_options' => [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'well',
                ],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'attr' => [
                'class' => 'well',
            ],
            'help' => $helpHtml,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_element_settings';
    }
}
