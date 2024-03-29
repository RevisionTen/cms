<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Menu;

use RevisionTen\CMS\Model\Alias;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class Page extends Item
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('title', TextType::class, [
            'label' => 'menu.label.title',
            'translation_domain' => 'cms',
        ]);

        $choices = [];

        /**
         * @var Alias[] $aliases
         */
        $aliases = $this->entityManager->getRepository(Alias::class)->findAll();
        if ($aliases) {
            foreach ($aliases as $alias) {
                if ($alias->getPageStreamRead()) {
                    $choices[$alias->getPageStreamRead()->getTitle().'  - '.$alias->getPath()] = $alias->getId();
                }
            }
        }

        $builder->add('alias', ChoiceType::class, [
            'label' => 'menu.label.alias',
            'translation_domain' => 'cms',
            'choices' => $choices,
            'attr' => [
                'data-widget' => 'select2',
            ],
        ]);

        $builder->add('targetBlank', CheckboxType::class, [
            'label' => 'menu.label.targetBlank',
            'translation_domain' => 'cms',
            'required' => false,
        ]);
    }
}
