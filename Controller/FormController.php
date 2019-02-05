<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class FormController.
 *
 * This controller wraps the RevisionTen\Forms\Controller\FormController and adds access checks to it.
 *
 * @Route("/admin/forms")
 */
class FormController extends AbstractController
{
    /** @var \RevisionTen\Forms\Controller\FormController  */
    private $formController;

    public function __construct(\RevisionTen\Forms\Controller\FormController $formController)
    {
        $this->formController = $formController;
    }

    /**
     * @Route("/create-form", name="forms_create_form")
     */
    public function create(Request $request)
    {
        $this->denyAccessUnlessGranted('form_create');

        return $this->formController->createFormAggregate($request);
    }

    /**
     * @Route("/delete-aggregate", name="forms_delete_aggregate")
     */
    public function delete(Request $request)
    {
        $this->denyAccessUnlessGranted('form_delete');

        return $this->formController->deleteAggregateAction($request);
    }

    /**
     * @Route("/edit-aggregate", name="forms_edit_aggregate")
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->editAggregateAction($request);
    }

    /**
     * @Route("/add-item/{formUuid}/{onVersion}/{itemName}/{parent}", name="forms_add_item")
     */
    public function addItem(Request $request, string $formUuid, int $onVersion, string $itemName, string $parent = null)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formAddItem($request, $formUuid, $onVersion, $parent, $itemName);
    }

    /**
     * @Route("/edit-item/{formUuid}/{onVersion}/{itemUuid}", name="forms_edit_item")
     */
    public function editItem(Request $request, string $formUuid, int $onVersion, string $itemUuid)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formEditItem($request, $formUuid, $onVersion, $itemUuid);
    }

    /**
     * @Route("/remove-item/{formUuid}/{onVersion}/{itemUuid}", name="forms_remove_item")
     */
    public function removeItem(string $formUuid, int $onVersion, string $itemUuid)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formRemoveItem($formUuid, $onVersion, $itemUuid);
    }

    /**
     * @Route("/shift-item/{formUuid}/{onVersion}/{itemUuid}/{direction}", name="forms_shift_item")
     */
    public function shiftItem(string $formUuid, int $onVersion, string $itemUuid, string $direction)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->formController->formShiftItem($formUuid, $onVersion, $itemUuid, $direction);
    }

    /**
     * @Route("/clone-aggregate", name="forms_clone_aggregate")
     */
    public function clone(Request $request)
    {
        $this->denyAccessUnlessGranted('form_clone');

        return $this->formController->cloneAggregateAction($request);
    }

    /**
     * @Route("/submissions", name="forms_submissions")
     */
    public function submissions(SerializerInterface $serializer, Request $request)
    {
        $this->denyAccessUnlessGranted('form_submissions');

        return $this->formController->submissions($serializer, $request);
    }
}
