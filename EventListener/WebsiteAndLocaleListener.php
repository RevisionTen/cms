<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Domain;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class WebsiteAndLocaleListener
{
    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $request = $event->getRequest();
        } else {
            $request = $this->requestStack->getMainRequest();
        }

        if ($request && null === $request->get('websiteId')) {
            // Get the website.
            $host = $request->getHost();

            /**
             * @var Domain $domain
             */
            $domain = $this->entityManager->getRepository(Domain::class)->findOneBy([
                'domain' => $host,
            ]);

            // Domain was found.
            if (null !== $domain) {
                $website = $domain->getWebsite();
                if (null !== $website) {
                    // Deprecated.
                    if (null === $request->get('website')) {
                        $request->request->set('website', $website->getId());
                    }

                    // Set website id in request.
                    $request->request->set('websiteId', $website->getId());
                    // Set default locale for this website.
                    $request->setLocale($website->getDefaultLanguage());
                }
            }
        }
    }
}
