<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Domain;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class WebsiteAndLocaleListener
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if ($event->isMasterRequest()) {
            /** @var \Symfony\Component\HttpFoundation\Request $request */
            $request = $event->getRequest();

            // Get the website.
            $host = $request->getHost();

            /** @var Domain $domain */
            $domain = $this->entityManager->getRepository(Domain::class)->findOneBy([
                'domain' => $host,
            ]);

            // Domain was found.
            if (null !== $domain) {
                $website = $domain->getWebsite();
                if (null !== $website) {
                    // Set website id in request.
                    if (null === $request->get('website')) {
                        $request->request->set('website', $website->getId());
                    }

                    // Set default locale for this website.
                    $request->setLocale($website->getDefaultLanguage());
                }
            }
        }
    }
}
