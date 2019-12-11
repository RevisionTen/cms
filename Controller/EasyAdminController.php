<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController as BaseController;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EasyAdminFormType;
use RevisionTen\CMS\Model\Website;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;
use function mb_strtolower;
use function method_exists;

/**
 * Class EasyAdminController
 *
 * Filter entity listings by currentWebsite.
 */
class EasyAdminController extends BaseController
{
    /**
     * @var array
     */
    private $cmsConfig;

    /**
     * EasyAdminController constructor.
     *
     * @param array $cmsConfig
     */
    public function __construct(array $cmsConfig)
    {
        $this->cmsConfig = $cmsConfig;
    }

    /**
     * {@inheritdoc}
     *
     * @return Response
     */
    protected function listAction(): Response
    {
        $permission = $this->entity['permissions']['list'] ?? 'list_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        // Always filter entities that have a website relation.
        $currentWebsite = $this->request->get('currentWebsite');
        if ($currentWebsite && method_exists($this->entity['class'], 'getWebsite')) {
            if (!empty($this->entity['list']['dql_filter'])) {
                $this->entity['list']['dql_filter'] .= ' AND entity.website = '.$currentWebsite;
            } else {
                $this->entity['list']['dql_filter'] = 'entity.website = '.$currentWebsite;
            }
        }

        return parent::listAction();
    }

    /**
     * {@inheritdoc}
     *
     * @return Response
     */
    protected function searchAction(): Response
    {
        $permission = $this->entity['permissions']['search'] ?? 'search_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        // Always filter entities that have a website relation.
        $currentWebsite = $this->request->get('currentWebsite');
        if ($currentWebsite && method_exists($this->entity['class'], 'getWebsite')) {
            if (!empty($this->entity['search']['dql_filter'])) {
                $this->entity['search']['dql_filter'] .= ' AND entity.website = '.$currentWebsite;
            } else {
                $this->entity['search']['dql_filter'] = 'entity.website = '.$currentWebsite;
            }
        }

        return parent::searchAction();
    }

    /**
     * {@inheritdoc}
     *
     * @return Response
     */
    protected function editAction(): Response
    {
        $permission = $this->entity['permissions']['edit'] ?? 'edit_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::editAction();
    }

    /**
     * {@inheritdoc}
     *
     * @return Response
     */
    protected function showAction(): Response
    {
        $permission = $this->entity['permissions']['show'] ?? 'show_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::showAction();
    }

    /**
     * {@inheritdoc}
     *
     * @return Response
     */
    protected function newAction(): Response
    {
        $permission = $this->entity['permissions']['new'] ?? 'create_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::newAction();
    }

    /**
     * {@inheritdoc}
     *
     * @return Response
     */
    protected function deleteAction(): Response
    {
        $permission = $this->entity['permissions']['delete'] ?? 'delete_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::deleteAction();
    }

    /**
     * {@inheritdoc}
     *
     * @param object $entity
     * @param string $view
     *
     * @return FormBuilder|FormBuilderInterface
     */
    protected function createEntityFormBuilder($entity, $view)
    {
        $currentWebsite = $this->request->get('currentWebsite');
        /**
         * @var Website $website
         */
        $website = $this->em->getRepository(Website::class)->find($currentWebsite);
        $overrideWebsite = $website && method_exists($this->entity['class'], 'getWebsite') && method_exists($entity, 'setWebsite');
        $overrideLanguage = $website && method_exists($this->entity['class'], 'getWebsite') && method_exists($entity, 'setLanguage');

        // Set default website.
        if ($overrideWebsite && 'new' === $view && null === $entity->getWebsite()) {
            $entity->setWebsite($website);
        }
        // Set default language.
        if ($overrideLanguage && 'new' === $view && null === $entity->getLanguage()) {
            $defaultLanguage = $website->getDefaultLanguage() ?? $this->request->getLocale();
            $entity->setLanguage($defaultLanguage);
        }

        $formOptions = $this->executeDynamicMethod('get<EntityName>EntityFormOptions', [$entity, $view]);
        $formBuilder = $this->get('form.factory')->createNamedBuilder(mb_strtolower($this->entity['name']), EasyAdminFormType::class, $entity, $formOptions);

        // Don't allow the website field to appear in the form.
        if ($formBuilder->has('website')) {
            $formBuilder->remove('website');
        }

        return $formBuilder;
    }

    /**
     * @param string|null $dqlFilter
     *
     * @return Response
     */
    protected function filteredListAction(string $dqlFilter = null): Response
    {
        $this->entity['list']['dql_filter'] = $dqlFilter;

        return $this->listAction();
    }

    /**
     * @param string|null $dqlFilter
     *
     * @return Response
     */
    protected function filteredSearchAction(string $dqlFilter = null): Response
    {
        $this->entity['search']['dql_filter'] = $dqlFilter;

        return $this->searchAction();
    }

    /**
     * @return Response
     */
    protected function listPageStreamReadAction(): Response
    {
        $dqlFilter = 'entity.deleted IS NULL OR entity.deleted = 0';
        // Filter out page_templates that the user has no permission for.
        $dqlFilter .= $this->getPermittedTemplatesDqlFilter('list');

        return $this->filteredListAction($dqlFilter);
    }

    /**
     * @return Response
     */
    protected function searchPageStreamReadAction(): Response
    {
        $dqlFilter = 'entity.deleted IS NULL OR entity.deleted = 0';
        // Filter out page_templates that the user has no permission for.
        $dqlFilter .= $this->getPermittedTemplatesDqlFilter('search');

        return $this->filteredSearchAction($dqlFilter);
    }

    /**
     * @return Response
     */
    protected function listPageStreamReadArchiveAction(): Response
    {
        $dqlFilter = 'entity.deleted = 1';
        // Filter out page_templates that the user has no permission for.
        $dqlFilter .= $this->getPermittedTemplatesDqlFilter('list');

        return $this->filteredListAction($dqlFilter);
    }

    /**
     * @return Response
     */
    protected function searchPageStreamReadArchiveAction(): Response
    {
        $dqlFilter = 'entity.deleted = 1';
        // Filter out page_templates that the user has no permission for.
        $dqlFilter .= $this->getPermittedTemplatesDqlFilter('search');

        return $this->filteredSearchAction($dqlFilter);
    }

    /**
     * @return Response
     */
    protected function listAliasAction(): Response
    {
        return $this->filteredListAction('entity.controller IS NULL');
    }

    /**
     * @return Response
     */
    protected function searchAliasAction(): Response
    {
        return $this->filteredSearchAction('entity.controller IS NULL');
    }

    /**
     * @param string $permissionName
     *
     * @return string
     */
    private function getPermittedTemplatesDqlFilter(string $permissionName): string
    {
        $pageTemplates = $this->cmsConfig['page_templates'] ?? null;
        $templates = [];
        foreach ($pageTemplates as $template => $templateConfig) {
            $permission = $templateConfig['permissions'][$permissionName] ?? null;
            // Check if permission is not explicitly set or user is granted the permission.
            if (null === $permission || $this->isGranted($permission)) {
                $templates[] = $template;
                continue;
            }
        }

        // Build filter query.
        $filter = ' AND ';
        if (empty($templates)) {
            $filter .= "entity.template = 'CANTVIEWANY'";
        } else {
            $templates = array_map(static function($template) {
                return "entity.template = '$template'";
            }, $templates);

            $filter .= '('.implode(' OR ', $templates).')';
        }

        return $filter;
    }
}
