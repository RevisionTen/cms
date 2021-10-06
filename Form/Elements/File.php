<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\CMS\Form\Types\ManagedUploadType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class File extends Element
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('title', TextType::class, [
            'label' => 'element.label.title',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->add('managedFile', ManagedUploadType::class, [
            'required' => false,
            'label' => false,
            'show_file_picker' => true,
            'file_with_meta_data' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_file';
    }
}
