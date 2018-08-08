<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoctrineType extends AbstractType
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

        if ($options['findBy']) {
            /** @var array $entities */
            $entities = $this->entityManager->getRepository($options['entityClass'])->findBy($options['findBy']);
        } else {
            /** @var array $entities */
            $entities = $this->entityManager->getRepository($options['entityClass'])->findAll();
        }

        if ($entities) {
            foreach ($entities as $entity) {
                $choices[(string) $entity] = $entity->getId();
            }
        }

        $builder->add('entityId', ChoiceType::class, [
            'required' => $options['required'],
            'label' => false,
            'choices' => $choices,
        ]);
        $builder->add('entityClass', HiddenType::class, [
            'data' => $options['entityClass'],
            'empty_data' => $options['entityClass'],
        ]);

        // Write a compound attribute for easy hydration.
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['entityId']) && isset($data['entityClass'])) {
                $data['doctrineEntity'] = $data['entityClass'].':'.$data['entityId'];
                // Remove unneeded data.
                unset($data['entityId']);
                unset($data['entityClass']);
            } else {
                $data['doctrineEntity'] = null;
            }

            $event->setData($data);
        });

        // Parse hydrationId.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            if (null !== $data) {
                if (isset($data['doctrineEntity'])) {
                    $data['entityId'] = explode(':', $data['doctrineEntity'])[1];
                }
                $event->setData($data);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'required' => true,
            'entityClass' => null,
            'findBy' => false,
        ]);
    }
}
