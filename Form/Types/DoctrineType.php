<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Entity\Website;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_values;
use function explode;
use function implode;
use function is_array;

class DoctrineType extends AbstractType
{
    private ?Website $website = null;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;

        $request = $requestStack->getMainRequest();
        $website = $request ? $request->get('currentWebsite') : null;

        if (null !== $website) {
            $this->website = $this->entityManager->getRepository(Website::class)->find($website);
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $choices = [];

        // Filter list of entities by website entity or id.
        if ($options['filterByWebsite']) {
            $options['findBy']['website'] = $this->website;
        } elseif ($options['filterByWebsiteId']) {
            $options['findBy']['website'] = $this->website->getId();
        }

        /**
         * @var array $entities
         */
        $entities = $this->entityManager->getRepository($options['entityClass'])->findBy($options['findBy'], $options['orderBy']);

        if ($entities) {
            $choiceLabel = $options['choice_label'];
            foreach ($entities as $entity) {
                $label = (string) $entity;
                if ($choiceLabel) {
                    if (is_callable($choiceLabel)) {
                        $label = $choiceLabel($entity);
                    } elseif (is_string($choiceLabel)) {
                        $getter = 'get'.ucfirst($choiceLabel);
                        $isser = 'is'.ucfirst($choiceLabel);
                        if (method_exists($entity, $getter)) {
                            $label = $entity->{$getter}();
                        } elseif (method_exists($entity, $isser)) {
                            $label = $entity->{$isser}();
                        }
                    }
                }
                $choices[$label] = $entity->getId();
            }
        }

        $choiceOptions = [
            'required' => $options['required'],
            'expanded' => $options['expanded'],
            'multiple' => $options['multiple'],
            'choice_attr' => $options['choice_attr'],
            'label' => false,
            'choices' => $choices,
            'attr' => [
                'class' => $options['multiple'] ? '' : 'custom-select',
            ],
        ];

        if ($options['placeholder']) {
            $choiceOptions['placeholder'] = $options['placeholder'];
        }
        if ($options['attr']) {
            $choiceOptions['attr'] = $options['attr'];
        }
        if ($options['constraints']) {
            $choiceOptions['constraints'] = $options['constraints'];
        }

        $builder->add('entityId', ChoiceType::class, $choiceOptions);
        $builder->add('entityClass', HiddenType::class, [
            'data' => $options['entityClass'],
            'empty_data' => $options['entityClass'],
        ]);

        // Write a compound attribute for easy hydration.
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) {
            $data = $event->getData();

            if (!empty($data['entityId']) && !empty($data['entityClass'])) {
                if (is_array($data['entityId'])) {
                    // Implode multiple.
                    $data['doctrineEntity'] = $data['entityClass'].':'. implode(',', $data['entityId']);
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
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use($options) {
            $data = $event->getData();
            if (null !== $data) {

                if (isset($data['doctrineEntity'])) {
                    // Reference is set, parse the reference.
                    // Get the entity id or ids.
                    $entityIds = explode(':', $data['doctrineEntity'])[1];
                    // Always convert the id(s) to an array of ids.
                    $entityIds = explode(',', $entityIds);

                    // Set the data on the form.
                    $data['entityId'] = $entityIds;
                    // Unset the reference.
                    unset($data['doctrineEntity']);
                }

                // Make sure the entityId field data has the correct format.
                if (!empty($data['entityId'])) {
                    if ($options['multiple'] && !is_array($data['entityId'])) {
                        // Form is multiple, but data is not an array, convert.
                        $data['entityId'] = [$data['entityId']];
                    } elseif (!$options['multiple'] && is_array($data['entityId'])) {
                        // Form in single, but data is an array, get first choice.
                        $data['entityId'] = array_values($data['entityId'])[0];
                    }
                } else {
                    // Data is empty.
                    $data['entityId'] = $options['multiple'] ? [] : null;
                }

                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
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
            'choice_label' => null,
            'choice_attr' => null,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'cms_doctrine';
    }
}
