<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use function array_walk;

class Controller extends Element
{
    private array $controller;

    public function __construct(array $config)
    {
        $controller = $config['controller'] ?? [];

        // Reduce controller config array to only label => action.
        if ($controller) {
            array_walk($controller, static function (&$item) {
                $item = $item['action'] ?? null;
            });
        }

        $this->controller = $controller;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('controller', ChoiceType::class, [
            'label' => 'element.label.controller',
            'translation_domain' => 'cms',
            'required' => false,
            'choices' => $this->controller,
            'choice_translation_domain' => 'messages',
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_controller';
    }
}
