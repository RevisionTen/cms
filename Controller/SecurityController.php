<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
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
class SecurityController extends Controller
{
    /**
     * @param Request $request
     *
     * @return FormInterface
     */
    private function buildCodeForm(Request $request): FormInterface
    {
        /** @var FormFactory $formFactory */
        $formFactory = $this->get('form.factory');
        $formBuilder = $formFactory->createNamedBuilder(null);

        $formBuilder->setMethod('POST');
        $formBuilder->setAction($request->get('redirectTo') ?? '/admin/');

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
     * @param Request $request
     *
     * @return FormInterface
     */
    private function buildLoginForm(Request $request): FormInterface
    {
        /** @var FormFactory $formFactory */
        $formFactory = $this->get('form.factory');
        $formBuilder = $formFactory->createNamedBuilder(null);

        $formBuilder->setMethod('POST');
        $formBuilder->setAction($request->get('redirectTo') ?? '/admin/');

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
     * @param Request $request
     *
     * @return Response
     */
    public function login(Request $request): Response
    {
        $form = $this->buildLoginForm($request);
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
     * @param Request $request
     *
     * @return Response
     */
    public function code(Request $request): Response
    {
        // Remove username and password field from request.
        $request->request->remove('username');
        $request->request->remove('password');

        $form = $this->buildCodeForm($request);

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
     * @param Request $request
     *
     * @return Response
     */
    public function loginForm(Request $request): Response
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getMasterRequest() ?? $request;

        $form = $this->buildLoginForm($request);
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
