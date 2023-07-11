<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class CodeAuthenticatorEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        if ($request->getSession()->has('username')) {
            return new RedirectResponse('/code');
        }

        return new RedirectResponse('/login');
    }
}
