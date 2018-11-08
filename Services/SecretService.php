<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Sonata\GoogleAuthenticator\GoogleQrUrl;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class SecretService.
 */
class SecretService
{
    /**
     * @var \Swift_Mailer
     */
    private $swift_Mailer;

    /**
     * @var array
     */
    private $config;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * SecretService constructor.
     *
     * @param \Swift_Mailer       $swift_Mailer
     * @param array               $config
     * @param TranslatorInterface $translator
     */
    public function __construct(\Swift_Mailer $swift_Mailer, array $config, TranslatorInterface $translator, RequestStack $requestStack, RouterInterface $router)
    {
        $this->swift_Mailer = $swift_Mailer;
        $this->config = $config;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    public function sendLoginInfo(string $username, string $password, string $mail): void
    {
        $issuer = $this->config['site_name'] ? $this->config['site_name'] : 'revisionTen';

        $subject = $this->translator->trans('Login data for %username%', [
            '%username%' => $username,
        ]);

        $messageText = $this->translator->trans('Your login data for %site_name%', [
            '%site_name%' => $issuer,
        ]);

        $messageBody = <<<EOT
$messageText:<br/><br/>
User: $username<br/>
Password: $password
EOT;

        $this->sendMail($subject, $messageBody, $mail);
    }

    public function sendSecret(string $secret, string $username, string $mail): void
    {
        $issuer = $this->config['site_name'] ? $this->config['site_name'] : 'revisionTen';

        $qrCode = GoogleQrUrl::generate(rawurlencode($username), $secret, rawurlencode($issuer), 200);

        $subject = $this->translator->trans('Google Authenticator Code for %username%', [
            '%username%' => $username,
        ]);

        $messageText = $this->translator->trans('Please keep this Code.');

        $messageBody = <<<EOT
$messageText<br/><br/>
User: $username<br/>
Secret: $secret<br/><br/>
<img src="$qrCode"><br/><br/>
<a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=de">Google Authenticator (Android)</a><br/>
<a href="https://itunes.apple.com/de/app/google-authenticator/id388497605?mt=8">Google Authenticator (iOS)</a>
EOT;

        $this->sendMail($subject, $messageBody, $mail);
    }

    public function sendPasswordResetMail(string $username, string $token, string $mail): void
    {
        $issuer = $this->config['site_name'] ? $this->config['site_name'] : 'revisionTen';

        $subject = $this->translator->trans('Password reset requested for %username%', [
            '%username%' => $username,
        ]);

        $messageText = $this->translator->trans('Click here to reset your password.');

        // Generate reset url.
        $context = new RequestContext();
        $context->fromRequest($this->requestStack->getMasterRequest());
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

    private function sendMail(string $subject, string $messageBody, string $mail): void
    {
        $senderConfigExists = isset($this->config['mailer_from']) && $this->config['mailer_from'] && isset($this->config['mailer_sender']) && $this->config['mailer_sender'] && isset($this->config['mailer_return_path']) && $this->config['mailer_return_path'];

        if ($senderConfigExists) {
            $message = (new \Swift_Message($subject))
                ->setFrom($this->config['mailer_from'])
                ->setSender($this->config['mailer_sender'])
                ->setReturnPath($this->config['mailer_return_path'])
                ->setTo($mail)
                ->setBody($messageBody, 'text/html')
            ;
        } else {
            // Attempt to send without explicitly setting the sender.
            $message = (new \Swift_Message($subject))
                ->setTo($mail)
                ->setBody($messageBody, 'text/html')
            ;
        }

        $this->swift_Mailer->send($message);
    }
}
