<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class Column extends Element
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $this->widthForm($builder, 'element.label.xs', 'element.placeholder.xs', 'widthXS', '12');
        $this->widthForm($builder, 'element.label.sm', 'element.placeholder.sm', 'widthSM');
        $this->widthForm($builder, 'element.label.md', 'element.placeholder.md', 'widthMD');
        $this->widthForm($builder, 'element.label.lg', 'element.placeholder.lg', 'widthLG');
        $this->widthForm($builder, 'element.label.xl', 'element.placeholder.xl', 'widthXL');
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_column';
    }

    /**
     * @param FormBuilderInterface $builder
     * @param string               $label
     * @param                      $placeholder
     * @param string               $key
     * @param string|null          $empty_data
     */
    private function widthForm(FormBuilderInterface $builder, string $label, $placeholder, string $key, string $empty_data = null): void
    {
        $builder->add($key, ChoiceType::class, [
            'label' => $label,
            'translation_domain' => 'cms',
            'required' => false,
            'choices' => [
                'element.choices.autoColumnWide' => 'auto',
                'element.choices.defaultColumnWide' => 'default',
                'element.choices.1ColumnWide' => '1',
                'element.choices.2ColumnsWide' => '2',
                'element.choices.3ColumnsWide' => '3',
                'element.choices.4ColumnsWide' => '4',
                'element.choices.5ColumnsWide' => '5',
                'element.choices.6ColumnsWide' => '6',
                'element.choices.7ColumnsWide' => '7',
                'element.choices.8ColumnsWide' => '8',
                'element.choices.9ColumnsWide' => '9',
                'element.choices.10ColumnsWide' => '10',
                'element.choices.11ColumnsWide' => '11',
                'element.choices.12ColumnsWide' => '12',
            ],
            'placeholder' => $placeholder,
            'empty_data' => $empty_data,
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);
    }
}
