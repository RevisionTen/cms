<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Controller\FrontendController;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\Website;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use function is_array;

class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var FrontendController
     */
    protected $frontendController;

    /**
     * ExceptionSubscriber constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param FrontendController     $frontendController
     */
    public function __construct(EntityManagerInterface $entityManager, FrontendController $frontendController)
    {
        $this->entityManager = $entityManager;
        $this->frontendController = $frontendController;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['notFoundException', 0],
            ]
        ];
    }

    /**
     * @param ExceptionEvent $event
     */
    public function notFoundException(ExceptionEvent $event): void
    {
        $exception = $event->getException();

        if ($exception instanceof NotFoundHttpException) {

            $request = $event->getRequest();
            $websiteId = $request->get('websiteId') ?? 1;
            $locale = $request->getLocale();

            if (null !== $websiteId && $locale) {
                $website = $this->entityManager->find(Website::class, $websiteId);
                if (null !== $website) {
                    foreach ($website->getErrorPages() as $pageStreamRead) {
                        if ($pageStreamRead->getLanguage() === $locale) {
                            $pageRead = $this->entityManager->getRepository(PageRead::class)->findOneBy(['uuid' => $pageStreamRead->getUuid()]);
                            $pageData = $pageRead ? $pageRead->getPayload() : false;

                            if ($pageData && is_array($pageData)) {
                                $alias = new Alias();
                                $alias->setPath($request->getPathInfo());
                                $alias->setWebsite($website);
                                $alias->setLanguage($locale);
                                $response = $this->frontendController->getPageResponse($pageData, $website, $alias);
                                $response->setStatusCode(Response::HTTP_NOT_FOUND);
                                $event->setResponse($response);
                            }
                        }
                    }
                }
            }
        }
    }
}
