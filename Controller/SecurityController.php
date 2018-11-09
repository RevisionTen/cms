<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Command\UserChangePasswordCommand;
use RevisionTen\CMS\Command\UserResetPasswordCommand;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Utilities\RandomHelpers;
use RevisionTen\CQRS\Services\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class SecurityController.
 */
class SecurityController extends AbstractController
{
    /**
     * @param Request              $request
     * @param FormFactoryInterface $formFactory
     *
     * @return FormInterface
     */
    private function buildCodeForm(Request $request, FormFactoryInterface $formFactory): FormInterface
    {
        $formBuilder = $formFactory->createNamedBuilder(null);

        $formBuilder->setMethod('POST');
        $formBuilder->setAction('/admin/dashboard');

        $session = $request->getSession();

        if (!$session->has('username')) {
            $formBuilder->add('username', TextType::class, [
                'label' => 'Username',
                'required' => true,
                'constraints' => new NotBlank(),
            ]);
        }

        $formBuilder->add('code', TextType::class, [
            'label' => 'Code',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'Send',
        ]);

        $form = $formBuilder->getForm();

        return $form;
    }

    /**
     * @param FormFactoryInterface $formFactory
     *
     * @return FormInterface
     */
    private function buildLoginForm(FormFactoryInterface $formFactory): FormInterface
    {
        $formBuilder = $formFactory->createNamedBuilder(null);

        $formBuilder->setMethod('POST');
        $formBuilder->setAction('/code');

        $formBuilder->add('username', TextType::class, [
            'label' => 'Username',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('password', PasswordType::class, [
            'label' => 'Password',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'autocomplete' => 'off',
            ],
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'Send',
        ]);

        $form = $formBuilder->getForm();

        return $form;
    }

    /**
     * Displays the login page.
     *
     * @Route("/login", name="cms_login")
     *
     * @param Request              $request
     * @param FormFactoryInterface $formFactory
     *
     * @return Response
     */
    public function login(Request $request, FormFactoryInterface $formFactory): Response
    {
        $form = $this->buildLoginForm($formFactory);
        $form->handleRequest($request);

        return $this->render('@cms/Security/login.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays the login page.
     *
     * @Route("/code", name="cms_code")
     *
     * @param Request              $request
     * @param FormFactoryInterface $formFactory
     *
     * @return Response
     */
    public function code(Request $request, FormFactoryInterface $formFactory): Response
    {
        // Remove username and password field from request.
        $request->request->remove('username');
        $request->request->remove('password');

        $form = $this->buildCodeForm($request, $formFactory);

        // Only handle the request if a code was submitted.
        if ($request->get('code')) {
            $form->handleRequest($request);
        }

        return $this->render('@cms/Security/login.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays the login form.
     *
     * @param RequestStack         $requestStack
     * @param FormFactoryInterface $formFactory
     *
     * @return Response
     */
    public function loginForm(RequestStack $requestStack, FormFactoryInterface $formFactory): Response
    {
        $request = $requestStack->getMasterRequest();

        $form = $this->buildLoginForm($formFactory);
        $form->handleRequest($request);

        return $this->render('@cms/Security/login-form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Request a password reset mail.
     *
     * @Route("/reset-password", name="cms_reset_password")
     *
     * @param Request                $request
     * @param FormFactoryInterface   $formFactory
     * @param EntityManagerInterface $entityManager
     * @param CommandBus             $commandBus
     *
     * @return Response
     */
    public function resetPassword(Request $request, FormFactoryInterface $formFactory, EntityManagerInterface $entityManager, CommandBus $commandBus): Response
    {
        $formBuilder = $formFactory->createNamedBuilder(null);

        $formBuilder->setMethod('POST');

        $formBuilder->add('username_email', TextType::class, [
            'label' => 'Username or email',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'Request new Password',
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $success = false;
        if ($form->isSubmitted() && $form->isValid()) {
            $success = true;

            $this->addFlash('success', 'A password reset email was requested. Please check your inbox.');

            $data = $form->getData();
            $usernameOrEmail = $data['username_email'];

            /** @var UserRead $user */
            $user = $entityManager->getRepository(UserRead::class)->findOneByUsername($usernameOrEmail);
            if (null === $user) {
                $user = $entityManager->getRepository(UserRead::class)->findOneByEmail($usernameOrEmail);
            }

            if (null !== $user) {
                $userId = $user->getId();
                $userUuid = $user->getUuid();

                // Check if user has an aggregate.
                if (null !== $userUuid) {
                    $onVersion = $user->getVersion();

                    $token = RandomHelpers::randomString();

                    // Dispatch password reset event.
                    $userResetPasswordCommand = new UserResetPasswordCommand($userId, null, $userUuid, $onVersion, [
                        'token' => $token,
                    ]);
                    $commandBus->dispatch($userResetPasswordCommand);
                }
            }
        }

        return $this->render('@cms/Security/reset-password-form.html.twig', [
            'form' => $form->createView(),
            'success' => $success,
        ]);
    }

    /**
     * Password reset form.
     *
     * @Route("/reset-password-form/{resetToken}/{username}", name="cms_reset_password_form")
     *
     * @param string                       $resetToken
     * @param string                       $username
     * @param Request                      $request
     * @param FormFactoryInterface         $formFactory
     * @param EntityManagerInterface       $entityManager
     * @param CommandBus                   $commandBus
     * @param UserPasswordEncoderInterface $encoder
     *
     * @return Response
     */
    public function resetPasswordForm(string $resetToken, string $username, Request $request, FormFactoryInterface $formFactory, EntityManagerInterface $entityManager, CommandBus $commandBus, UserPasswordEncoderInterface $encoder): Response
    {
        $formBuilder = $formFactory->createNamedBuilder(null, FormType::class, [
            'username' => $username,
            'resetToken' => $resetToken,
        ]);

        $formBuilder->setMethod('POST');

        $formBuilder->add('username', HiddenType::class, [
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('resetToken', HiddenType::class, [
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('password', PasswordType::class, [
            'label' => 'New Password',
            'required' => true,
            'constraints' => new NotBlank(),
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'Set new password',
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $success = false;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $password = $data['password'];
            $username = $data['username'];
            $resetToken = $data['resetToken'];

            /** @var UserRead $user */
            $user = $entityManager->getRepository(UserRead::class)->findOneByUsername($username);

            if (null !== $user) {
                $userId = $user->getId();
                $userUuid = $user->getUuid();
                $userToken = $user->getResetToken();
                $encodedPassword = $encoder->encodePassword($user, $password);

                // Check if token matches and user has an aggregate.
                if ($userToken === $resetToken && null !== $userUuid) {
                    $onVersion = $user->getVersion();

                    // Dispatch password reset event.
                    $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };
                    $userChangePasswordCommand = new UserChangePasswordCommand($userId, null, $userUuid, $onVersion, [
                        'password' => $encodedPassword,
                    ], $successCallback);
                    $commandBus->dispatch($userChangePasswordCommand);
                }
            }
        }

        if ($success) {
            $this->addFlash('success', 'Password was changed!');
        }

        return $this->render('@cms/Security/reset-password-form.html.twig', [
            'form' => $form->createView(),
            'success' => $success,
        ]);
    }

    /**
     * Log a user out by clearing the session.
     *
     * @Route("/logout", name="cms_logout")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        // Destroy session.
        $session = $request->getSession();
        if ($session) {
            $session->clear();
        }

        return $this->redirect('/');
    }
}
