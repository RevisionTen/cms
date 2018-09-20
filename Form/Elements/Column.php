<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class Column extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $this->widthForm($builder, 'xs', 'xs', 'widthXS', '12');
        $this->widthForm($builder, 'sm', 'like xs', 'widthSM');
        $this->widthForm($builder, 'md', 'like sm', 'widthMD');
        $this->widthForm($builder, 'lg', 'like md', 'widthLG');
        $this->widthForm($builder, 'xl', 'like lg', 'widthXL');
    }

    private function widthForm(FormBuilderInterface $builder, string $label, $placeholder, string $key, string $empty_data = null)
    {
        $builder->add($key, ChoiceType::class, [
            'label' => $label,
            'required' => false,
            'choices' => [
                '1 column wide' => '1',
                '2 columns wide' => '2',
                '3 columns wide' => '3',
                '4 columns wide' => '4',
                '5 columns wide' => '5',
                '6 columns wide' => '6',
                '7 columns wide' => '7',
                '8 columns wide' => '8',
                '9 columns wide' => '9',
                '10 columns wide' => '10',
                '11 columns wide' => '11',
                '12 columns wide' => '12',
            ],
            'placeholder' => $placeholder,
            'empty_data' => $empty_data,
        ]);
    }
}
