<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class Controller extends Element
{
    /** @var array */
    private $controller;

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

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('controller', ChoiceType::class, [
            'label' => 'Choose the controller action to render.',
            'required' => false,
            'choices' => $this->controller,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_controller';
    }
}
