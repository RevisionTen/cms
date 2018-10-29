<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class SecurityController.
 */
class SecurityController extends AbstractController
{
    /**
     * @param Request $request
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
     * @param Request              $request
     * @param FormFactoryInterface $formFactory
     *
     * @return FormInterface
     */
    private function buildLoginForm(Request $request, FormFactoryInterface $formFactory): FormInterface
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
     * @Route("/login", name="login")
     *
     * @param Request              $request
     * @param FormFactoryInterface $formFactory
     *
     * @return Response
     */
    public function login(Request $request, FormFactoryInterface $formFactory): Response
    {
        $form = $this->buildLoginForm($request, $formFactory);
        $form->handleRequest($request);

        return $this->render('@cms/Security/login.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays the login page.
     *
     * @Route("/code", name="code")
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

        $form = $this->buildLoginForm($request, $formFactory);
        $form->handleRequest($request);

        return $this->render('@cms/Security/login-form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Log a user out by clearing the session.
     *
     * @Route("/logout", name="logout")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        $session = $request->getSession();

        if ($session) {
            $session->clear();
        }

        return $this->redirect('/');
    }
}
