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
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('title', TextType::class, [
            'label' => 'Title',
        ]);

        $choices = [];

        /** @var Alias[] $aliases */
        $aliases = $this->entityManager->getRepository(Alias::class)->findAll();
        if ($aliases) {
            foreach ($aliases as $alias) {
                if ($alias->getPageStreamRead()) {
                    $choices[$alias->getPageStreamRead()->getTitle().'  - '.$alias->getPath()] = $alias->getId();
                }
            }
        }

        $builder->add('alias', ChoiceType::class, [
            'label' => 'Alias',
            'choices' => $choices,
        ]);

        $builder->add('targetBlank', CheckboxType::class, [
            'label' => 'Open link in new window',
            'required' => false,
        ]);
    }
}
