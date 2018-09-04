<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Sonata\GoogleAuthenticator\GoogleQrUrl;
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
     * SecretService constructor.
     *
     * @param \Swift_Mailer $swift_Mailer
     */
    public function __construct(\Swift_Mailer $swift_Mailer, array $config, TranslatorInterface $translator)
    {
        $this->swift_Mailer = $swift_Mailer;
        $this->config = $config;
        $this->translator = $translator;
    }

    public function sendSecret(string $secret, string $username, string $mail): void
    {
        $issuer = $this->config['site_name'] ? $this->config['site_name'] : 'revisionTen';

        $qrCode = GoogleQrUrl::generate(rawurlencode($username), $secret, rawurlencode($issuer), 200);

        $subject = $this->translator->trans('Google Authenticator Code for %username%', [
            '%username%' => $username,
        ]);

        $messageText = $this->translator->trans('Please keep this Code.');

        $senderConfigExists = isset($this->config['mailer_from']) && $this->config['mailer_from'] && isset($this->config['mailer_sender']) && $this->config['mailer_sender'] && isset($this->config['mailer_return_path']) && $this->config['mailer_return_path'];

        if ($senderConfigExists) {
            $message = (new \Swift_Message($subject))
                ->setFrom($this->config['mailer_from'])
                ->setSender($this->config['mailer_sender'])
                ->setReturnPath($this->config['mailer_return_path'])
                ->setTo($mail)
                ->setBody(
                    $messageText.'<br/><br/>User: '.$username.'<br/>Secret: '.$secret.'<br/><br/><img src="'.$qrCode.'"><br/><br/><a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=de">Google Authenticator (Android)</a><br/><a href="https://itunes.apple.com/de/app/google-authenticator/id388497605?mt=8">Google Authenticator (iOS)</a>',
                    'text/html'
                )
            ;
        } else {
            // Attempt to send without explicitly setting the sender.
            $message = (new \Swift_Message($subject))
                ->setTo($mail)
                ->setBody(
                    $messageText.'<br/><br/>User: '.$username.'<br/>Secret: '.$secret.'<br/><br/><img src="'.$qrCode.'"><br/><br/><a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=de">Google Authenticator (Android)</a><br/><a href="https://itunes.apple.com/de/app/google-authenticator/id388497605?mt=8">Google Authenticator (iOS)</a>',
                    'text/html'
                )
            ;
        }

        $this->swift_Mailer->send($message);
    }
}
