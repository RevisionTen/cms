<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\Forms\Model\FormRead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class Form extends Element
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

        $choices = [];

        /** @var FormRead[] $forms */
        $forms = $this->entityManager->getRepository(FormRead::class)->findBy(['deleted' => false]);
        if ($forms) {
            foreach ($forms as $form) {
                $choices[$form->getTitle()] = $form->getUuid();
            }
        }

        $builder->add('formUuid', ChoiceType::class, [
            'label' => 'Please choose the Form you want to show.',
            'choices' => $choices,
        ]);
    }
}
