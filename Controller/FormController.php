<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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
     * @Route("/create-form", name="forms_create_form")
     */
    public function create(Request $request)
    {
        $this->denyAccessUnlessGranted('form_create');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::createFormAggregate', [], $request->query->all());
    }

    /**
     * @Route("/delete-aggregate", name="forms_delete_aggregate")
     */
    public function delete(Request $request)
    {
        $this->denyAccessUnlessGranted('form_delete');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::deleteAggregateAction', [], $request->query->all());
    }

    /**
     * @Route("/edit-aggregate", name="forms_edit_aggregate")
     */
    public function edit(Request $request)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::editAggregateAction', [], $request->query->all());
    }

    /**
     * @Route("/add-item/{formUuid}/{onVersion}/{itemName}/{parent}", name="forms_add_item")
     */
    public function addItem(Request $request, $formUuid, $onVersion, $itemName, $parent = null)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::formAddItem', [
            'formUuid' => $formUuid,
            'onVersion' => $onVersion,
            'itemName' => $itemName,
            'parent' => $parent,
        ], $request->query->all());
    }

    /**
     * @Route("/edit-item/{formUuid}/{onVersion}/{itemUuid}", name="forms_edit_item")
     */
    public function editItem(Request $request, $formUuid, $onVersion, $itemUuid)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::formEditItem', [
            'formUuid' => $formUuid,
            'onVersion' => $onVersion,
            'itemUuid' => $itemUuid,
        ], $request->query->all());
    }

    /**
     * @Route("/remove-item/{formUuid}/{onVersion}/{itemUuid}", name="forms_remove_item")
     */
    public function removeItem(Request $request, $formUuid, $onVersion, $itemUuid)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::formRemoveItem', [
            'formUuid' => $formUuid,
            'onVersion' => $onVersion,
            'itemUuid' => $itemUuid,
        ], $request->query->all());
    }

    /**
     * @Route("/shift-item/{formUuid}/{onVersion}/{itemUuid}/{direction}", name="forms_shift_item")
     */
    public function shiftItem(Request $request, $formUuid, $onVersion, $itemUuid, $direction)
    {
        $this->denyAccessUnlessGranted('form_edit');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::formShiftItem', [
            'formUuid' => $formUuid,
            'onVersion' => $onVersion,
            'itemUuid' => $itemUuid,
            'direction' => $direction,
        ], $request->query->all());
    }

    /**
     * @Route("/clone-aggregate", name="forms_clone_aggregate")
     */
    public function clone(Request $request)
    {
        $this->denyAccessUnlessGranted('form_clone');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::cloneAggregateAction', [], $request->query->all());
    }

    /**
     * @Route("/submissions", name="forms_submissions")
     */
    public function submissions(Request $request)
    {
        $this->denyAccessUnlessGranted('form_submissions');

        return $this->forward('\RevisionTen\Forms\Controller\FormController::submissions', [], $request->query->all());
    }
}
