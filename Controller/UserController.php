<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_values;

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
     * @throws Exception
     */
    public function edit(Request $request, EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, TranslatorInterface $translator, int $id)
    {
        /**
         * @var UserRead $user
         */
        $user = $this->getUser();

        /**
         * @var UserRead $userRead
         */
        $userRead = $entityManager->getRepository(UserRead::class)->find($id);
        if (null === $userRead) {
            return $this->redirectToUsers();
        }
        if (empty($userRead->getUuid())) {
            $this->addFlash(
                'danger',
                $translator->trans('admin.help.userNeedsMigration', [], 'cms')
            );

            return $this->redirectToUsers();
        }

        /**
         * @var UserAggregate $userAggregate
         */
        $userAggregate = $aggregateFactory->build($userRead->getUuid(), UserAggregate::class);

        $userEditsSelf = $user->getUuid() === $userAggregate->getUuid();
        $canEditOthers = $this->isGranted('user_edit');

        if (!$userEditsSelf) {
            $this->denyAccessUnlessGranted('user_edit');
        }

        // Get all websites.
        /**
         * @var Website[] $websiteEntities
         */
        $websiteEntities = $entityManager->getRepository(Website::class)->findAll();
        $websites = [];
        foreach ($websiteEntities as $websiteEntity) {
            $websites[$websiteEntity->getTitle()] = $websiteEntity->getId();
        }

        // Get all roles.
        /**
         * @var RoleRead[] $roleEntities
         */
        $roleEntities = $entityManager->getRepository(RoleRead::class)->findAll();
        $roles = [];
        foreach ($roleEntities as $roleEntity) {
            $roles[$roleEntity->getTitle()] = $roleEntity->getUuid();
        }

        $formBuilder = $this->createFormBuilder([
            'color' => $userAggregate->color ?? $userRead->getColor(),
            'theme' => $userAggregate->theme ?? $userRead->getTheme(),
            'username' => $userAggregate->username,
            'email' => $userAggregate->email,
            'websites' => $userAggregate->websites,
            'roles' => $userAggregate->roles,
            'avatarUrl' => $userAggregate->avatarUrl,
        ]);

        $usernameHelp = $userEditsSelf ? 'admin.help.username' : null;
        $formBuilder->add('username', TextType::class, [
            'label' => 'admin.label.username',
            'help' => $usernameHelp,
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('email', EmailType::class, [
            'label' => 'admin.label.email',
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('avatarUrl', UrlType::class, [
            'label' => 'admin.label.avatarUrl',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $formBuilder->add('color', ColorType::class, [
            'label' => 'admin.label.color',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $formBuilder->add('theme', ChoiceType::class, [
            'label' => 'admin.label.theme',
            'translation_domain' => 'cms',
            'required' => false,
            'choices' => [
                'Sandstone' => 'sandstone',
                'Cosmo' => 'cosmo',
                'Simplex' => 'simplex',
                'Litera' => 'litera',
                'Lux' => 'lux',
                'Dark' => 'dark',
                'Greendrops' => 'greendrops',
            ],
        ]);

        if ($canEditOthers) {
            $formBuilder->add('websites', ChoiceType::class, [
                'label' => 'admin.label.websites',
                'translation_domain' => 'cms',
                'choices' => $websites,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);

            $formBuilder->add('roles', ChoiceType::class, [
                'label' => 'admin.label.roles',
                'translation_domain' => 'cms',
                'choices' => $roles,
                'choice_translation_domain' => 'messages',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
        }

        $formBuilder->add('save', SubmitType::class, [
            'label' => 'admin.btn.save',
            'translation_domain' => 'cms',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $usernameHasChanged = $userEditsSelf && !empty($data['username']) && $data['username'] !== $userAggregate->username;

            // Update the aggregate.
            $success = $commandBus->execute(UserEditCommand::class, $userAggregate->getUuid(), [
                'username' => $data['username'],
                'email' => $data['email'],
                'color' => $data['color'],
                'theme' => $data['theme'],
                'avatarUrl' => $data['avatarUrl'],
                'websites' => array_values($data['websites']),
                'roles' => array_values($data['roles']),
            ], $user->getId());

            if (!$success) {
                return new JsonResponse($messageBus->getMessagesJson());
            }

            if ($usernameHasChanged) {
                $session = $request->getSession();
                if ($session) {
                    $session->clear();
                }

                return $this->redirectToRoute('cms_login');
            }

            $this->addFlash(
                'success',
                $translator->trans('admin.label.userEditSuccess', [], 'cms')
            );

            return $this->redirectToRoute('cms_list_entity', [
                'entity' => 'UserRead',
                'sortBy' => $request->query->get('sortBy'),
                'sortOrder' => $request->query->get('sortOrder'),
            ]);
        }

        return $this->render('@CMS/Backend/Form/form.html.twig', [
            'title' => $translator->trans('admin.label.editUser', [], 'cms'),
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
