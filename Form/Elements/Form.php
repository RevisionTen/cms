<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use RevisionTen\Forms\Model\FormRead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class Form extends Element
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * Form constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $choices = [];

        /**
         * @var FormRead[] $forms
         */
        $forms = $this->entityManager->getRepository(FormRead::class)->findBy(['deleted' => false]);
        if ($forms) {
            foreach ($forms as $form) {
                $choices[$form->getTitle()] = $form->getUuid();
            }
        }

        $builder->add('formUuid', ChoiceType::class, [
            'label' => 'element.label.formUuid',
            'translation_domain' => 'cms',
            'choices' => $choices,
            'choice_translation_domain' => 'messages',
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_form';
    }
}
