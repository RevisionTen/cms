<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventListener;

use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;
use function array_map;
use function in_array;
use function strpos;

class CurrentWebsiteListener
{
    /** @var SessionInterface  */
    private $session;

    /** @var Security  */
    private $security;

    public function __construct(SessionInterface $session, Security $security)
    {
        $this->session = $session;
        $this->security = $security;
    }

    /**
     * This method get the users chosen website and sets it on the request as "currentWebsite".
     *
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMasterRequest()) {
            $request = $event->getRequest();

            // Path must begin with /admin, otherwise stop execution of this method.
            if (strpos($request->getRequestUri(), '/admin') !== 0) {
                return;
            }

            /** @var UserRead $user */
            $user = $this->security->getUser();

            if (null !== $user && null !== $request) {
                $websites = $user->getWebsites();

                if (null === $websites) {
                    throw new AccessDeniedHttpException('User does not belong to any website');
                }

                if ($websites->count() === 1) {
                    /** @var Website $currentWebsite */
                    $currentWebsite = $websites->first();
                    $currentWebsite = $currentWebsite->getId();
                } else {
                    $websiteIds = array_map(static function ($website) {
                        /** @var Website $website */
                        return $website->getId();
                    }, $websites->toArray());

                    $currentWebsite = $request->cookies->get('cms_current_website') ?? $this->session->get('currentWebsite');

                    if (null === $currentWebsite || !in_array($currentWebsite, $websiteIds, false)) {
                        // Current Website is null or does not exist in the users websites, set first website as current
                        // or If the user has no assigned website set it to website with id 1 if the user is an admin.
                        /** @var Website $currentWebsite */
                        $currentWebsite = $websites->first();
                        $fallbackWebsite = in_array('ROLE_ADMINISTRATOR', $user->getRoles(), true) ? 1 : 0;
                        $currentWebsite = $currentWebsite instanceOf Website ? $currentWebsite->getId() : $fallbackWebsite;
                    }
                }

                $request->request->set('currentWebsite', (int) $currentWebsite);
            }
        }
    }
}
