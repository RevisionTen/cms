<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Website;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoctrineType extends AbstractType
{
    /** @var Website|null */
    private $website;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;

        $request = $requestStack->getMasterRequest();
        $website = $request ? $request->get('currentWebsite') : null;

        if (null !== $website) {
            $this->website = $this->entityManager->getRepository(Website::class)->find($website);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $choices = [];

        // Filter list of entities by website entity or id.
        if ($options['filterByWebsite']) {
            $options['findBy']['website'] = $this->website;
        } elseif ($options['filterByWebsiteId']) {
            $options['findBy']['website'] = $this->website->getId();
        }

        /** @var array $entities */
        $entities = $this->entityManager->getRepository($options['entityClass'])->findBy($options['findBy'], $options['orderBy']);

        if ($entities) {
            foreach ($entities as $entity) {
                $choices[(string) $entity] = $entity->getId();
            }
        }

        $choiceOptions = [
            'required' => $options['required'],
            'expanded' => $options['expanded'],
            'multiple' => $options['multiple'],
            'label' => false,
            'choices' => $choices,
        ];

        if ($options['placeholder']) {
            $choiceOptions['placeholder'] = $options['placeholder'];
        }

        $builder->add('entityId', ChoiceType::class, $choiceOptions);
        $builder->add('entityClass', HiddenType::class, [
            'data' => $options['entityClass'],
            'empty_data' => $options['entityClass'],
        ]);

        // Write a compound attribute for easy hydration.
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();

            if (isset($data['entityId'], $data['entityClass'])) {
                if (is_array($data['entityId'])) {
                    // Implode multiple.
                    $data['doctrineEntity'] = $data['entityClass'].':'.implode(',', $data['entityId']);
                } else {
                    // Single.
                    $data['doctrineEntity'] = $data['entityClass'].':'.$data['entityId'];
                }
                // Remove unneeded data.
                unset($data['entityId'], $data['entityClass']);
            } else {
                unset($data['entityId'], $data['entityClass'], $data['doctrineEntity']);
            }

            $event->setData($data);
        });

        // Parse hydrationId.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use($options) {
            $data = $event->getData();
            if (null !== $data) {
                if (isset($data['doctrineEntity'])) {
                    $data['entityId'] = explode(':', $data['doctrineEntity'])[1];
                    // Always convert to multiple array.
                    $data['entityId'] = explode(',', $data['entityId']);
                    // Convert back to single if single choice.
                    if (!$options['multiple']) {
                        $data['entityId'] = $data['entityId'][0];
                    }
                    unset($data['doctrineEntity']);
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
            'expanded' => false,
            'multiple' => false,
            'placeholder' => false,
            'entityClass' => null,
            'findBy' => [],
            'orderBy' => [],
            'filterByWebsite' => false,
            'filterByWebsiteId' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cms_doctrine';
    }
}
