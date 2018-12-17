<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController as BaseController;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EasyAdminFormType;
use RevisionTen\CMS\Model\Website;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;

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

        return parent::listAction();
    }

    protected function searchAction(): Response
    {
        $permission = $this->entity['permissions']['search'] ?? 'search_generic';
        $this->denyAccessUnlessGranted($permission, $this->entity);

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

    protected function createAliasEntityFormBuilder($entity, $view)
    {
        $formOptions = $this->executeDynamicMethod('get<EntityName>EntityFormOptions', [$entity, $view]);

        $formBuilder = $this->get('form.factory')->createNamedBuilder(\mb_strtolower($this->entity['name']), EasyAdminFormType::class, $entity, $formOptions);

        // Override website choice form.
        if ($this->getUser()) {
            /** @var Website[] $websites */
            $websites = $this->getUser()->getWebsites();
            $formBuilder->add('website', EntityType::class, [
                'class' => Website::class,
                'choice_label' => 'title',
                'label' => 'Website',
                'placeholder' => 'Website',
                'choices' => $websites,
                'constraints' => new NotBlank(),
            ]);
        }

        return $formBuilder;
    }

    protected function filteredListAction(string $dqlFilter = null): Response
    {
        $currentWebsite = $this->request->get('currentWebsite');

        if ($currentWebsite) {
            if (null !== $dqlFilter) {
                $this->entity['list']['dql_filter'] = '('.$dqlFilter.') AND entity.website = '.$currentWebsite;
            } else {
                $this->entity['list']['dql_filter'] = 'entity.website = '.$currentWebsite;
            }
        } else {
            $this->entity['list']['dql_filter'] = $dqlFilter;
        }

        return $this->listAction();
    }

    protected function filteredSearchAction(string $dqlFilter = null): Response
    {
        $currentWebsite = $this->request->get('currentWebsite');

        if ($currentWebsite) {
            if (null !== $dqlFilter) {
                $this->entity['search']['dql_filter'] = '('.$dqlFilter.') AND entity.website = '.$currentWebsite;
            } else {
                $this->entity['search']['dql_filter'] = 'entity.website = '.$currentWebsite;
            }
        } else {
            $this->entity['search']['dql_filter'] = $dqlFilter;
        }

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

    protected function listMenuReadAction(): Response
    {
        return $this->filteredListAction();
    }

    protected function searchMenuReadAction(): Response
    {
        return $this->filteredSearchAction();
    }

    protected function listFileReadAction(): Response
    {
        return $this->filteredListAction();
    }

    protected function searchFileReadAction(): Response
    {
        return $this->filteredSearchAction();
    }
}