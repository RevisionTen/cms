<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Sonata\GoogleAuthenticator\GoogleQrUrl;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function rawurlencode;

class SecretService
{
    private MailerInterface $mailer;

    protected array $config;

    protected TranslatorInterface $translator;

    protected RequestStack $requestStack;

    protected RouterInterface $router;

    public function __construct(MailerInterface $mailer, array $config, TranslatorInterface $translator, RequestStack $requestStack, RouterInterface $router)
    {
        $this->mailer = $mailer;
        $this->config = $config;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendLoginInfo(string $username, string $password, string $mail): void
    {
        $issuer = $this->config['site_name'] ?? 'revisionTen';

        // Generate login url.
        $context = new RequestContext();
        $context->fromRequest($this->requestStack->getMainRequest());
        $this->router->setContext($context);
        $loginUrl = $this->router->generate('cms_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $subject = $this->translator->trans('admin.label.loginDataFor', [
            '%username%' => $username,
        ], 'cms');

        $messageText = $this->translator->trans('admin.label.yourLoginFor', [
            '%site_name%' => $issuer,
        ], 'cms');

        $messageBody = <<<EOT
$messageText:<br/><br/>
User: $username<br/>
Password: $password<br/>
<a href="$loginUrl">Login Link</a>
EOT;

        $this->sendMail($subject, $messageBody, $mail);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendSecret(string $secret, string $username, string $mail): void
    {
        $issuer = $this->config['site_name'] ?? 'revisionTen';

        $qrCode = GoogleQrUrl::generate(rawurlencode($username), $secret, rawurlencode($issuer));

        $subject = $this->translator->trans('admin.label.authenticatorCodeFor', [
            '%username%' => $username,
        ], 'cms');

        $messageText = $this->translator->trans('admin.label.authenticatorCodeHint', [], 'cms');

        $messageBody = <<<EOT
$messageText<br/><br/>
User: $username<br/>
Secret: $secret<br/><br/>
<img alt="QR Code" src="$qrCode"><br/><br/>
<a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=de">Google Authenticator (Android)</a><br/>
<a href="https://itunes.apple.com/de/app/google-authenticator/id388497605?mt=8">Google Authenticator (iOS)</a>
EOT;

        $this->sendMail($subject, $messageBody, $mail);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendPasswordResetMail(string $username, string $token, string $mail): void
    {
        $subject = $this->translator->trans('admin.label.passwordResetFor', [
            '%username%' => $username,
        ], 'cms');

        $messageText = $this->translator->trans('admin.label.passwordResetLink', [], 'cms');

        // Generate reset url.
        $context = new RequestContext();
        $context->fromRequest($this->requestStack->getMainRequest());
        $this->router->setContext($context);
        $resetUrl = $this->router->generate('cms_reset_password_form', [
            'resetToken' => $token,
            'username' => $username,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $messageBody = <<<EOT
<a href="$resetUrl">$messageText</a>
EOT;

        $this->sendMail($subject, $messageBody, $mail);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendMail(string $subject, string $messageBody, string $mail): void
    {
        $senderConfigExists = isset($this->config['mailer_from'], $this->config['mailer_sender'], $this->config['mailer_return_path']) && $this->config['mailer_from'] && $this->config['mailer_sender'] && $this->config['mailer_return_path'];

        if ($senderConfigExists) {
            $message = (new Email())
                ->from($this->config['mailer_from'])
                ->sender($this->config['mailer_sender'])
                ->returnPath($this->config['mailer_return_path'])
                ->to($mail)
                ->subject($subject)
                ->html($messageBody, 'text/html')
            ;
        } else {
            // Attempt to send without explicitly setting the sender.
            $message = (new Email())
                ->to($mail)
                ->subject($subject)
                ->html($messageBody, 'text/html')
            ;
        }

        $this->mailer->send($message);
    }
}
