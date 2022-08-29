<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;
use RevisionTen\CMS\Entity\Website;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function in_array;

/**
 * @Route("/admin/entity")
 */
class EntityController extends AbstractController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/create", name="cms_create_entity")
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     *
     * @return Response
     * @throws Exception
     */
    public function create(Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $entity = $request->query->get('entity') ??  'doesnotexist';

        $entityConfig = $this->config['entities'][$entity] ?? null;
        if (empty($entityConfig)) {
            throw new NotFoundHttpException();
        }

        $permissionCreate = $entityConfig['permissions']['create'] ?? ($entityConfig['permissions']['new'] ?? 'create_generic');
        $this->denyAccessUnlessGranted($permissionCreate);

        $entityClass = $entityConfig['class'] ?? null;
        if (empty($entityClass)) {
            throw new Exception('Class not set for entities entry '.$entity);
        }

        $entityObject = new $entityClass();

        $websiteId = (int) $request->get('currentWebsite');
        $website = $websiteId ? $em->getRepository(Website::class)->find($websiteId) : null;
        if ($website) {
            if (method_exists($entityObject, 'setWebsite')) {
                $entityObject->setWebsite($website);
            }
            if (method_exists($entityObject, 'setLanguage')) {
                $entityObject->setLanguage($website->getDefaultLanguage());
            }
        }

        $form = $this->getEntityForm($entityConfig, $entityObject);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entityObject);
            $em->flush();

            $this->addFlash(
                'success',
                $translator->trans('admin.label.createEntitySuccess', [
                    '%entity%' => $translator->trans($entity),
                ], 'cms')
            );

            return $this->redirectToRoute('cms_edit_entity', [
                'entity' => $entity,
                'id' => $entityObject->getId(),
            ]);
        }

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.addEntity', [
                '%entity%' => $translator->trans($entity),
            ], 'cms'),
            'form' => $form->createView(),
            'entity' => $entity,
            'entityObject' => $entityObject,
        ]);
    }

    /**
     * @Route("/edit/{id}", name="cms_edit_entity")
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @param int|string $id
     *
     * @return Response
     * @throws Exception
     */
    public function edit(Request $request, EntityManagerInterface $em, TranslatorInterface $translator, $id): Response
    {
        $entity = $request->query->get('entity') ??  'doesnotexist';

        $entityConfig = $this->config['entities'][$entity] ?? null;
        if (empty($entityConfig)) {
            throw new NotFoundHttpException();
        }

        $permissionEdit = $entityConfig['permissions']['edit'] ?? 'edit_generic';
        // Todo: Add delete button:
        $permissionDelete = $entityConfig['permissions']['delete'] ?? 'delete_generic';
        $this->denyAccessUnlessGranted($permissionEdit);

        $entityClass = $entityConfig['class'] ?? null;
        if (empty($entityClass)) {
            throw new Exception('Class not set for entities entry '.$entity);
        }

        $entityObject = $em->getRepository($entityClass)->find($id);
        if (empty($entityObject)) {
            throw new NotFoundHttpException();
        }

        $form = $this->getEntityForm($entityConfig, $entityObject);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entityObject);
            $em->flush();

            $this->addFlash(
                'success',
                $translator->trans('admin.label.editEntitySuccess', [
                    '%entity%' => $translator->trans($entity),
                ], 'cms')
            );

            return $this->redirectToRoute('cms_edit_entity', [
                'entity' => $entity,
                'id' => $entityObject->getId(),
            ]);
        }

        $entityTitle = method_exists($entityObject,'__toString' ) ? '"'.((string) $entityObject).'" ' : '';

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.editEntity', [
                '%entity%' => $translator->trans($entity),
                '%title%' => $entityTitle,
            ], 'cms'),
            'form' => $form->createView(),
            'entity' => $entity,
            'entityObject' => $entityObject,
        ]);
    }

    /**
     * @param array $entityConfig
     * @param mixed|null $data
     *
     * @return FormInterface
     * @throws Exception
     */
    private function getEntityForm(array $entityConfig, $data): FormInterface
    {
        $entityType = $entityConfig['form']['type'] ?? null;
        $fields = $entityConfig['form']['fields'] ?? [];
        if ($entityType) {
            $form = $this->createForm($entityType, $data);
        } elseif (!empty($fields)) {
            // Create form from field config.
            $builder = $this->createFormBuilder($data);

            foreach ($fields as $field) {
                $property = $field['property'] ?? null;
                $label = $field['label'] ?? null;
                $type = $field['type'] ?? null;
                $type_options = $field['type_options'] ?? null;

                switch ($type) {
                    case 'text':
                    case null:
                        $type = TextType::class;
                        break;
                    case 'textarea':
                        $type = TextareaType::class;
                        break;
                    case 'collection':
                        $type = CollectionType::class;
                        break;
                    case 'choice':
                        $type = ChoiceType::class;
                        break;
                }

                if ($property && $type) {
                    $options = [
                        'label' => $label,
                        'translation_domain' => 'cms',
                    ];
                    if (!empty($type_options)) {
                        $options += $type_options;
                    }
                    $builder->add($property, $type, $options);
                }
            }

            $builder->add('save', SubmitType::class, [
                'label' => 'admin.btn.save',
                'translation_domain' => 'cms',
            ]);

            $form = $builder->getForm();
        } else {
            throw new Exception('Missing form type or form config');
        }

        return $form;
    }

    /**
     * @Route("/delete/{id}", name="cms_delete_entity")
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @param int|string $id
     *
     * @return RedirectResponse
     * @throws Exception
     */
    public function delete(Request $request, EntityManagerInterface $em, TranslatorInterface $translator, $id): RedirectResponse
    {
        $entity = $request->query->get('entity') ??  'doesnotexist';

        $entityConfig = $this->config['entities'][$entity] ?? null;
        if (empty($entityConfig)) {
            throw new NotFoundHttpException();
        }

        $permissionDelete = $entityConfig['permissions']['delete'] ?? 'delete_generic';
        $this->denyAccessUnlessGranted($permissionDelete);

        $entityClass = $entityConfig['class'] ?? null;
        if (empty($entityClass)) {
            throw new Exception('Class not set for entities entry '.$entity);
        }

        $entityObject = $em->getRepository($entityClass)->find($id);
        if (empty($entityObject)) {
            throw new NotFoundHttpException();
        }

        $em->remove($entityObject);
        $em->flush();
        $em->clear();

        $query = $request->query->all();
        unset($query['id']);

        return $this->redirectToRoute('cms_list_entity', $query);
    }

    /**
     * @Route("/list", name="cms_list_entity")
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     *
     * @return Response
     * @throws Exception
     */
    public function list(Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $entity = $request->query->get('entity') ??  'doesnotexist';

        $entityConfig = $this->config['entities'][$entity] ?? null;

        if (empty($entityConfig)) {
            throw new NotFoundHttpException();
        }

        $permissionList = $entityConfig['permissions']['list'] ?? 'list_generic';
        $permissionSearch = $entityConfig['permissions']['search'] ?? 'search_generic';
        $permissionEdit = $entityConfig['permissions']['edit'] ?? 'edit_generic';
        $permissionShow = $entityConfig['permissions']['show'] ?? 'show_generic';
        $permissionCreate = $entityConfig['permissions']['create'] ?? ($entityConfig['permissions']['new'] ?? 'create_generic');
        $permissionDelete = $entityConfig['permissions']['delete'] ?? 'delete_generic';

        $this->denyAccessUnlessGranted($permissionList);

        $defaultResultsPerPage = $entityConfig['list']['resultsPerPage'] ?? 20;
        $defaultSortBy = $entityConfig['list']['sort'][0] ?? 'id';
        $defaultSortOrder = strtolower($entityConfig['list']['sort'][1] ?? 'desc');

        $q = (string) ($request->get('q') ?? '');
        $sortBy = (string) ($request->get('sortBy') ?? $defaultSortBy);
        $sortOrder = (string) ($request->get('sortOrder') ?? $defaultSortOrder);
        $page = (int) $request->get('page');
        $limit = (int) ($request->get('resultsPerPage') ?? $defaultResultsPerPage);
        $offset = (int) ($page * $limit);

        $listFields = $entityConfig['list']['fields'] ?? [];

        $fields = [];
        foreach ($listFields as $key => $field) {
            $property = $field['property'] ?? null;
            $template = $field['template'] ?? null;
            $sortable = !isset($field['sortable']) || $field['sortable'] === true;
            $type = $field['type'] ?? null;

            $label = $field['label'] ?? null;
            if ($label) {
                $label = $translator->trans($label, [], 'cms');
            }

            $name = $property ?? $key;
            $fields[$name] = [
                'property' => $property,
                'sortable' => $sortable,
                'label' => $label,
                'template' => $template,
                'type' => $type,
            ];
        }

        $fields['actions'] = [
            'label' => null,
            'template' => '@CMS/Backend/Entity/List/field_actions.html.twig',
        ];

        // Check if sortBy is an entity property that can be sorted.
        $sortProperties = array_map(static function($field) {
            $property = $field['property'] ?? null;
            $sortable = !isset($field['sortable']) || $field['sortable'] === true;

            return $property && $sortable ? $property : null;
        }, $fields);
        $sortBy = in_array($sortBy, $sortProperties, true) ? $sortBy : $defaultSortBy;

        // Check if sortOrder is valid.
        $sortOrder = in_array($sortOrder, ['desc', 'asc'], true) ? $sortOrder : $defaultSortOrder;

        $entityClass = $entityConfig['class'] ?? null;
        if (empty($entityClass)) {
            throw new Exception('Class not set for entities entry '.$entity);
        }

        $qb = $em->createQueryBuilder();
        $qb
            ->select('entity')
            ->from($entityClass, 'entity')
            ->where($qb->expr()->isNotNull('entity.id'))
            ->addOrderBy('entity.'.$sortBy, $sortOrder)
        ;

        // Add current website filter.
        $websiteId = $request->get('currentWebsite');
        if ($websiteId && property_exists($entityClass, 'website')) {
            $qb->andWhere($qb->expr()->eq('entity.website', ':websiteId'))->setParameter('websiteId', $websiteId);
        }

        // Add dql filter.
        $dqlFilter = $entityConfig['list']['dql_filter'] ?? null;
        if ($dqlFilter) {
            $subQuery = $em->createQuery($dqlFilter);
            $qb->andWhere($subQuery->getDQL());
        }

        // Add search term.
        $searchFields = $entityConfig['list']['search_fields'] ?? [];
        if (!empty($q) && !empty($searchFields)) {
            $qb = self::addTermQuery($qb, $searchFields, $q);
        }

        // Get count.
        $count = count($qb->getQuery()->getScalarResult());
        $numPages = ceil($count / $limit);
        if ($page+1 > $numPages) {
            $page = $numPages - 1;
            $offset = (int) ($page * $limit);
        }

        $items = [];
        if ($numPages > 0) {
            // Get results.
            $query = $qb->getQuery();
            if (null !== $limit) {
                $query->setMaxResults($limit);
            }
            if (null !== $offset) {
                $query->setFirstResult($offset);
            }
            $paginator = new Paginator($query);
            $items = $paginator->getIterator();
        }

        // Default actions.
        $actions = [
            'list',
            'search',
            'create',
            'edit',
            'delete',
        ];

        // Check if show view is configured.
        $showConfig = $entityConfig['show']['fields'] ?? [];
        if (!empty($showConfig)) {
            $actions[] = 'show';
        }

        $actionsConfig = $entityConfig['list']['actions'] ?? [];
        $actions = array_combine($actions, $actions);
        foreach ($actionsConfig as $action) {
            if (is_string($action) && substr($action, 0, 1) === '-') {
                // Remove action.
                $actionKey = substr($action, 1);
                if (isset($actions[$actionKey])) {
                    unset($actions[$actionKey]);
                }
            } elseif (is_array($action)) {
                // Add route action.
                $label = !empty($action['label']) ? $action['label']: ($action['title'] ?? null);
                // Parse old EasyAdmin style type: route config.
                $fallbackRoute = !empty($action['type']) && $action['type'] === 'route' && !empty($action['name']) ? $action['name'] : null;
                $route = !empty($action['route']) ? $action['route'] : $fallbackRoute;
                if ($route && $label) {
                    $actions[$route] =[
                        'route' => $route,
                        'label' => $label,
                        'icon' => $action['icon'] ?? null,
                        'cssClass' => $action['css_class'] ?? '',
                        'permission' => $action['permission'] ?? null,
                    ];
                }
            }
        }

        $template = $entityConfig['list']['template'] ?? $entityConfig['templates']['list'] ?? '@CMS/Backend/Entity/List/list.html.twig';

        return $this->render($template, [
            'page' => $page,
            'numPages' => $numPages,
            'resultsPerPage' => $limit,
            'items' => $items,
            'fields' => $fields,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,

            'title' => $entityConfig['label'] ?? null,
            'entity' => $entity,
            'actions' => $actions,
            'permissions' => [
                'list' => $permissionList,
                'search' => $permissionSearch,
                'edit' => $permissionEdit,
                'show' => $permissionShow,
                'create' => $permissionCreate,
                'delete' => $permissionDelete,
            ],
        ]);
    }

    public static function addTermQuery(QueryBuilder $qb, $fields, string $term)
    {
        $fieldQueries = [];
        foreach ($fields as $field) {
            if ('payload' === $field) {
                $fieldQueries[] = $qb->expr()->isNotNull("JSON_SEARCH(LOWER(entity.payload), 'all', LOWER(:q))");
            } else {
                $fieldQueries[] = $qb->expr()->like('entity.'.$field, ':q');
            }
        }

        $qb
            ->andWhere($qb->expr()->orX(...$fieldQueries))
            ->setParameter('q', '%'.$term.'%');

        return $qb;
    }

    /**
     * @Route("/show/{id}", name="cms_show_entity")
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @param int|string $id
     *
     * @return Response
     * @throws Exception
     */
    public function show(Request $request, EntityManagerInterface $em, TranslatorInterface $translator, $id): Response
    {
        $entity = $request->query->get('entity') ??  'doesnotexist';

        $entityConfig = $this->config['entities'][$entity] ?? null;
        if (empty($entityConfig)) {
            throw new NotFoundHttpException();
        }

        $permissionShow = $entityConfig['permissions']['show'] ?? 'show_generic';
        $this->denyAccessUnlessGranted($permissionShow);

        $entityClass = $entityConfig['class'] ?? null;
        if (empty($entityClass)) {
            throw new Exception('Class not set for entities entry '.$entity);
        }

        $entityObject = $em->getRepository($entityClass)->find($id);
        if (empty($entityObject)) {
            throw new NotFoundHttpException();
        }

        $entityTitle = method_exists($entityObject,'__toString' ) ? '"'.((string) $entityObject).'" ' : '';

        $fields = $entityConfig['show']['fields'] ?? [];
        foreach ($fields as $key => $field) {
            $property = $field['property'] ?? null;
            if ($property) {
                $get = 'get'.ucfirst($property);
                $has = 'has'.ucfirst($property);
                $is = 'is'.ucfirst($property);
                if (method_exists($entityObject, $get)) {
                    $fields[$key]['value'] = $entityObject->{$get}();
                } elseif (method_exists($entityObject, $has)) {
                    $fields[$key]['value'] = $entityObject->{$has}();
                } elseif (method_exists($entityObject, $is)) {
                    $fields[$key]['value'] = $entityObject->{$is}();
                } else {
                    $rp = new ReflectionProperty($entityObject, $property);
                    if ($rp->isPublic()) {
                        $fields[$key]['value'] = $entityObject->{$property};
                    }
                }
            }
        }

        return $this->render('@CMS/Backend/Entity/Show/show.html.twig', [
            'title' => $translator->trans('admin.label.showEntity', [
                '%entity%' => $translator->trans($entity),
                '%title%' => $entityTitle,
            ], 'cms'),
            'entity' => $entity,
            'entityObject' => $entityObject,
            'fields' => $fields,
        ]);
    }
}
