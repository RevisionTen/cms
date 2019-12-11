<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use \RevisionTen\Forms\Controller\FormController as BaseFormController;

/**
 * Class FormController.
 *
 * This controller wraps the RevisionTen\Forms\Controller\FormController and adds access checks to it.
 *
 * @Route("/admin/forms")
 */
class FormController extends AbstractController
{
    /**
     * @var BaseFormController
     */
    private $formController;

    public function __construct(BaseFormController $formController)
    {
        $this->formController = $formController;
    }

    /**
     * @Route("/create-form", name="forms_create_form")
     *
     * @param Request $request
     *
     * @return JsonResponse|RedirectResponse|Response
     * @throws Exception
     */
    public function create(Request $request)
    {
        $this->denyAccessUnlessGranted('form_create');

        return $this->formController->createFormAggregate($request);
    }

    /**
     * @Route("/delete-aggregate", name="forms_delete_aggregate")
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function delete(Request $request): Response
    {
        $this->denyAccessUnlessGranted('form_delete');

        return $this->formController->deleteAggregateAction($request);
    }

    /**
     * @Route("/edit-aggregate", name="forms_edit_aggregate")
     *
     * @param Request            $request
     * @param ContainerInterface $container
     *
     * @return Response
     * @throws Exception
     */
    public function edit(Request $request, ContainerInterface $container): Response
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->editAggregateAction($request, $container);
    }

    /**
     * @Route("/add-item/{formUuid}/{onVersion}/{itemName}/{parent}", name="forms_add_item")
     *
     * @param Request     $request
     * @param string      $formUuid
     * @param int         $onVersion
     * @param string      $itemName
     * @param string|null $parent
     *
     * @return Response
     * @throws Exception
     */
    public function addItem(Request $request, string $formUuid, int $onVersion, string $itemName, string $parent = null): Response
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formAddItem($request, $formUuid, $onVersion, $parent, $itemName);
    }

    /**
     * @Route("/edit-item/{formUuid}/{onVersion}/{itemUuid}", name="forms_edit_item")
     *
     * @param Request $request
     * @param string  $formUuid
     * @param int     $onVersion
     * @param string  $itemUuid
     *
     * @return Response
     * @throws Exception
     */
    public function editItem(Request $request, string $formUuid, int $onVersion, string $itemUuid): Response
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formEditItem($request, $formUuid, $onVersion, $itemUuid);
    }

    /**
     * @Route("/remove-item/{formUuid}/{onVersion}/{itemUuid}", name="forms_remove_item")
     *
     * @param string $formUuid
     * @param int    $onVersion
     * @param string $itemUuid
     *
     * @return JsonResponse|Response
     * @throws Exception
     */
    public function removeItem(string $formUuid, int $onVersion, string $itemUuid)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formRemoveItem($formUuid, $onVersion, $itemUuid);
    }

    /**
     * @Route("/shift-item/{formUuid}/{onVersion}/{itemUuid}/{direction}", name="forms_shift_item")
     *
     * @param string $formUuid
     * @param int    $onVersion
     * @param string $itemUuid
     * @param string $direction
     *
     * @return JsonResponse|RedirectResponse|Response
     * @throws Exception
     */
    public function shiftItem(string $formUuid, int $onVersion, string $itemUuid, string $direction)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formShiftItem($formUuid, $onVersion, $itemUuid, $direction);
    }

    /**
     * @Route("/clone-aggregate", name="forms_clone_aggregate")
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function clone(Request $request): Response
    {
        $this->denyAccessUnlessGranted('form_clone');

        return $this->formController->cloneAggregateAction($request);
    }

    /**
     * @Route("/submissions-download", name="forms_submissions_download")
     *
     * @param SerializerInterface $serializer
     * @param Request             $request
     *
     * @return Response
     */
    public function submissionsDownload(SerializerInterface $serializer, Request $request): Response
    {
        $this->denyAccessUnlessGranted('form_submissions');

        return $this->formController->submissionsDownload($serializer, $request);
    }

    /**
     * @Route("/submissions", name="forms_submissions")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function submissions(Request $request): Response
    {
        $this->denyAccessUnlessGranted('form_submissions');

        return $this->formController->submissions($request);
    }
}
