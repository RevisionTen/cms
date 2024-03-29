<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RevisionTen\CMS\Command\UserChangePasswordCommand;
use RevisionTen\CMS\Command\UserResetPasswordCommand;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Utilities\RandomHelpers;
use RevisionTen\CQRS\Exception\InterfaceException;
use RevisionTen\CQRS\Services\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class SecurityController.
 */
class SecurityController extends AbstractController
{
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

        return $this->render('@CMS/Backend/Security/login.html.twig', [
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
     * @param KernelInterface      $kernel
     *
     * @return Response
     */
    public function code(Request $request, FormFactoryInterface $formFactory, KernelInterface $kernel): Response
    {
        // Remove submitted login form fields from request.
        $request->request->remove('login');

        $code = $kernel->getEnvironment() === 'dev' ? '123456' : null;

        $form = $this->buildCodeForm($formFactory, [
            'code' => $code,
        ]);

        // Only handle the request if a code was submitted.
        $code = $request->get('code')['code'] ?? null;
        if ($code) {
            $form->handleRequest($request);
        }

        return $this->render('@CMS/Backend/Security/code.html.twig', [
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
        $request = $requestStack->getMainRequest();

        $form = $this->buildLoginForm($formFactory);
        $form->handleRequest($request);

        return $this->render('@CMS/Backend/Security/login-form.html.twig', [
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
     * @param TranslatorInterface    $translator
     *
     * @return Response
     * @throws InterfaceException
     */
    public function resetPassword(Request $request, FormFactoryInterface $formFactory, EntityManagerInterface $entityManager, CommandBus $commandBus, TranslatorInterface $translator): Response
    {
        $formBuilder = $formFactory->createBuilder();

        $formBuilder->setMethod('POST');

        $formBuilder->add('username_email', TextType::class, [
            'label' => false,
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'admin.label.userNameOrEmail',
            ],
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'admin.btn.requestNewPassword',
            'translation_domain' => 'cms',
            'attr' => [
                'class' => 'btn-primary d-block w-100',
            ],
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $success = false;
        if ($form->isSubmitted() && $form->isValid()) {
            $success = true;

            $this->addFlash('success', $translator->trans('admin.label.requestNewPasswordSuccess', [], 'cms'));

            $data = $form->getData();
            $usernameOrEmail = $data['username_email'];

            /**
             * @var UserRead $user
             */
            $user = $entityManager->getRepository(UserRead::class)->findOneBy([ 'username' => $usernameOrEmail ]);
            if (null === $user) {
                $user = $entityManager->getRepository(UserRead::class)->findOneBy([ 'email' => $usernameOrEmail ]);
            }

            if (null !== $user) {
                $userId = $user->getId();
                $userUuid = $user->getUuid();

                // Check if user has an aggregate.
                if (null !== $userUuid) {
                    $token = RandomHelpers::randomString();

                    // Dispatch password reset event.
                    $commandBus->execute(UserResetPasswordCommand::class, $userUuid, [
                        'token' => $token,
                    ], $userId);
                }
            }
        }

        return $this->render('@CMS/Backend/Security/reset-password.html.twig', [
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
     * @param UserPasswordHasherInterface  $passwordHasher
     * @param TranslatorInterface          $translator
     *
     * @return Response
     * @throws InterfaceException
     */
    public function resetPasswordForm(string $resetToken, string $username, Request $request, FormFactoryInterface $formFactory, EntityManagerInterface $entityManager, CommandBus $commandBus, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator): Response
    {
        $formBuilder = $formFactory->createBuilder(FormType::class, [
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

        $formBuilder->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'required' => true,
            'constraints' => [
                new NotBlank(),
                new NotCompromisedPassword(),
            ],
            'translation_domain' => 'cms',
            'first_options'  => ['label' => 'admin.label.newPassword'],
            'second_options' => ['label' => 'admin.label.repeatNewPassword'],
            'invalid_message' => $translator->trans('admin.validation.passwordsMustMatch', [], 'cms'),
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'admin.btn.setNewPassword',
            'translation_domain' => 'cms',
        ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $success = false;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $password = $data['password'];
            $username = $data['username'];
            $resetToken = $data['resetToken'];

            /**
             * @var UserRead $user
             */
            $user = $entityManager->getRepository(UserRead::class)->findOneBy([ 'username' => $username ]);

            if (null !== $user) {
                $userId = $user->getId();
                $userUuid = $user->getUuid();
                $userToken = $user->getResetToken();
                $encodedPassword = $passwordHasher->hashPassword($user, $password);

                // Check if token matches and user has an aggregate.
                if ($userToken === $resetToken && null !== $userUuid) {
                    // Dispatch password reset event.
                    $success = $commandBus->execute(UserChangePasswordCommand::class, $userUuid, [
                        'password' => $encodedPassword,
                    ], $userId);
                }
            }
        }

        if ($success) {
            $this->addFlash('success', $translator->trans('admin.label.passwordChangedSuccess', [], 'cms'));
        }

        return $this->render('@CMS/Backend/Security/reset-password.html.twig', [
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

    /**
     * @param FormFactoryInterface $formFactory
     *
     * @return FormInterface
     */
    private function buildCodeForm(FormFactoryInterface $formFactory, array $data): FormInterface
    {
        $formBuilder = $formFactory->createNamedBuilder('code', FormType::class, $data);

        $formBuilder->setMethod('POST');
        $formBuilder->setAction('/admin/dashboard');

        $formBuilder->add('code', TextType::class, [
            'label' => false,
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'admin.label.code',
                'autofocus' => 'autofocus',
            ],
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'admin.btn.login',
            'translation_domain' => 'cms',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);

        return $formBuilder->getForm();
    }

    /**
     * @param FormFactoryInterface $formFactory
     *
     * @return FormInterface
     */
    private function buildLoginForm(FormFactoryInterface $formFactory): FormInterface
    {
        $formBuilder = $formFactory->createNamedBuilder('login');

        $formBuilder->setMethod('POST');
        $formBuilder->setAction('/code');

        $formBuilder->add('username', TextType::class, [
            'label' => false,
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'placeholder' => 'admin.label.username',
                'autofocus' => 'autofocus',
            ],
        ]);

        $formBuilder->add('password', PasswordType::class, [
            'label' => false,
            'translation_domain' => 'cms',
            'required' => true,
            'constraints' => new NotBlank(),
            'attr' => [
                'autocomplete' => 'off',
                'placeholder' => 'admin.label.password',
            ],
        ]);

        $formBuilder->add('send', SubmitType::class, [
            'label' => 'admin.btn.login',
            'translation_domain' => 'cms',
            'attr' => [
                'class' => 'btn-primary',
            ],
        ]);

        return $formBuilder->getForm();
    }
}
