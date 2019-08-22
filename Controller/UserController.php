<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Command\UserEditCommand;
use RevisionTen\CMS\Model\RoleRead;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class UserController.
 *
 * @Route("/admin")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/user/edit/{id}", name="cms_user_edit")
     *
     * @param Request                $request
     * @param EntityManagerInterface $entityManager
     * @param CommandBus             $commandBus
     * @param MessageBus             $messageBus
     * @param AggregateFactory       $aggregateFactory
     * @param TranslatorInterface    $translator
     * @param int                    $id
     *
     * @return Response|RedirectResponse|JsonResponse
     * @throws \Exception
     */
    public function edit(Request $request, EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, int $id)
    {
        $this->denyAccessUnlessGranted('user_edit');

        /** @var UserRead $user */
        $user = $this->getUser();

        $userRead = $entityManager->getRepository(UserRead::class)->find($id);
        if (null === $userRead) {
            return $this->redirectToUsers();
        }
        if (empty($userRead->getUuid())) {
            $this->addFlash(
                'danger',
                $translator->trans('User needs to be migrated')
            );
            return $this->redirectToUsers();
        }

        /** @var UserAggregate $userAggregate */
        $userAggregate = $aggregateFactory->build($userRead->getUuid(), UserAggregate::class);

        // Get all websites.
        /** @var Website[] $websiteEntities */
        $websiteEntities = $entityManager->getRepository(Website::class)->findAll();
        $websites = [];
        foreach ($websiteEntities as $websiteEntity) {
            $websites[$websiteEntity->getTitle()] = $websiteEntity->getId();
        }

        // Get all roles.
        /** @var RoleRead[] $roleEntities */
        $roleEntities = $entityManager->getRepository(RoleRead::class)->findAll();
        $roles = [];
        foreach ($roleEntities as $roleEntity) {
            $roles[$roleEntity->getTitle()] = $roleEntity->getUuid();
        }

        $formBuilder = $this->createFormBuilder([
            'color' => $userAggregate->color ?? $userRead->getColor(),
            'websites' => $userAggregate->websites,
            'roles' => $userAggregate->roles,
            'avatarUrl' => $userAggregate->avatarUrl,
        ]);

        $formBuilder->add('avatarUrl', TextType::class, [
            'label' => 'Avatar Url',
            'required' => false,
        ]);

        $formBuilder->add('color', ColorType::class, [
            'label' => 'Color',
            'required' => false,
        ]);

        $formBuilder->add('websites', ChoiceType::class, [
            'label' => 'Website',
            'choices' => $websites,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ]);

        $formBuilder->add('roles', ChoiceType::class, [
            'label' => 'Roles',
            'choices' => $roles,
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ]);

        $formBuilder->add('submit', SubmitType::class, [
            'label' => 'Save user',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Update the aggregate.
            $success = $commandBus->execute(UserEditCommand::class, $userAggregate->getUuid(), [
                'color' => $data['color'],
                'avatarUrl' => $data['avatarUrl'],
                'websites' => array_values($data['websites']),
                'roles' => array_values($data['roles']),
            ], $user->getId());

            if (!$success) {
                return new JsonResponse($messageBus->getMessagesJson());
            }

            $this->addFlash(
                'success',
                $translator->trans('User edited')
            );
        }

        return $this->render('@cms/Form/form.html.twig', [
            'title' => 'Edit user',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @return RedirectResponse
     */
    private function redirectToUsers(): RedirectResponse
    {
        return $this->redirect('/admin/?entity=UserRead&action=list');
    }
}
