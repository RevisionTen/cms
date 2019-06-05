<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController as BaseController;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EasyAdminFormType;
use RevisionTen\CMS\Model\Website;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EasyAdminController
 *
 * Filter entity listings by currentWebsite.
 *
 * @package RevisionTen\CMS\Controller
 */
class EasyAdminController extends BaseController
{
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

    protected function editAction(): Response
    {
        $permission = $this->entity['permissions']['edit'] ?? 'edit_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::editAction();
    }

    protected function showAction(): Response
    {
        $permission = $this->entity['permissions']['show'] ?? 'show_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::showAction();
    }

    protected function newAction(): Response
    {
        $permission = $this->entity['permissions']['new'] ?? 'create_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::newAction();
    }

    protected function deleteAction(): Response
    {
        $permission = $this->entity['permissions']['delete'] ?? 'delete_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

        return parent::deleteAction();
    }

    protected function createEntityFormBuilder($entity, $view)
    {
        $currentWebsite = $this->request->get('currentWebsite');
        /** @var Website $website */
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
        $formBuilder = $this->get('form.factory')->createNamedBuilder(\mb_strtolower($this->entity['name']), EasyAdminFormType::class, $entity, $formOptions);

        // Don't allow the website field to appear in the form.
        if ($formBuilder->has('website')) {
            $formBuilder->remove('website');
        }

        return $formBuilder;
    }

    protected function filteredListAction(string $dqlFilter = null): Response
    {
        $this->entity['list']['dql_filter'] = $dqlFilter;

        return $this->listAction();
    }

    protected function filteredSearchAction(string $dqlFilter = null): Response
    {
        $this->entity['search']['dql_filter'] = $dqlFilter;

        return $this->searchAction();
    }

    protected function listPageStreamReadAction(): Response
    {
        return $this->filteredListAction('entity.deleted IS NULL OR entity.deleted = 0');
    }

    protected function searchPageStreamReadAction(): Response
    {
        return $this->filteredSearchAction('entity.deleted IS NULL OR entity.deleted = 0');
    }

    protected function listPageStreamReadArchiveAction(): Response
    {
        return $this->filteredListAction('entity.deleted = 1');
    }

    protected function searchPageStreamReadArchiveAction(): Response
    {
        return $this->filteredSearchAction('entity.deleted = 1');
    }

    protected function listAliasAction(): Response
    {
        return $this->filteredListAction('entity.controller IS NULL');
    }

    protected function searchAliasAction(): Response
    {
        return $this->filteredSearchAction('entity.controller IS NULL');
    }
}
