<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_merge;

class CKEditorType extends TextareaType
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config['ckeditor_config'] ?? [];
    }

    public function getBlockPrefix(): string
    {
        return 'cms_ckeditor';
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['config'] = array_merge($this->config, $options['config']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'config' => $this->config,
        ]);
    }
}
