<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ManagedUploadType
 *
 * @deprecated Deprecated since version 2.3.8. Will be removed in version 3.
 */
class ManagedUploadType extends UploadType
{
    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'label' => false,
            'attr' => [],
            'upload_dir' => '/uploads/managed-files/',
            'keep_deleted_file' => true,
            'allow_delete' => true,
            'allow_replace' => true,
            'show_file_picker' => true,
            'file_picker_mime_types' => null,
            'file_with_meta_data' => true,
            // Do not validate this form type with the passed constraints, use them for the file field instead.
            'validation_groups' => false,
            'constraints' => null,
            'allow_extra_fields' => true,
            'keepOriginalFileName' => false,
            'enable_title' => true,
            'enable_chooser' => true,
        ]);

        $resolver->setDeprecated('keep_deleted_file');
        $resolver->setDeprecated('enable_title');
        $resolver->setDeprecated('enable_chooser');
    }
}
