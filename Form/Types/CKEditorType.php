<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CKEditorType extends TextareaType
{
    private static $config = [
        'uiColor' => '#ffffff',
        'allowedContent' => true,
        'extraAllowedContent' => 'span(*);i(*)',
        'stylesSet' => 'bootstrap4styles:/bundles/cms/js/ckeditor-styles.js', // To use ckeditor defaults use: default:/bundles/cms/libs/dist/ckeditor/styles.js
        'contentsCss' => [
            'https://use.fontawesome.com/releases/v5.5.0/css/all.css',
            '/bundles/cms/example-template-files/bootstrap.min.css',
            '/bundles/cms/libs/dist/ckeditor/contents.css',
        ],
        'toolbar' => [
            [
                'name' => 'basicstyles',
                'items' => [ 'Source', 'PasteFromWord', 'RemoveFormat', '-', 'Undo', 'Redo', 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript' ],
            ],
            [
                'name' => 'paragraph',
                'items' => [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'Link', 'Unlink', 'Anchor', 'Table', 'HorizontalRule', 'Iframe' ],
            ],
            [
                'name' => 'basicstyles2',
                'items' => [ 'Styles', 'Format' ],
            ],
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_ckeditor';
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['config'] = array_merge(self::$config, $options['config']);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'config' => self::$config,
        ]);
    }
}
