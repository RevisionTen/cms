<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Admin;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use RevisionTen\CMS\Entity\PageStreamRead;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PageStreamReadType extends EntityType
{
    protected TranslatorInterface $translator;

    private ?int $website;

    public function __construct(ManagerRegistry $registry, TranslatorInterface $translator, RequestStack $requestStack)
    {
        parent::__construct($registry);

        $this->translator = $translator;
        $this->website = $requestStack->getMainRequest() ? (int) $requestStack->getMainRequest()->get('currentWebsite') : null;
    }

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('class', PageStreamRead::class);
        $resolver->setDefault('attr', [
            'data-widget' => 'select2',
        ]);

        $resolver->setDefault('choice_label', function ($pageStreamRead) {
            /**
             * @var PageStreamRead $pageStreamRead
             */
            $label = $pageStreamRead->getTitle();
            if ($pageStreamRead->getDeleted()) {
                $label .= ' ' . $this->translator->trans('admin.label.pageIsDeleted', [], 'cms');
            }

            return $label;
        });

        $resolver->setDefault('query_builder', function (EntityRepository $entityRepository) {
            $qb = $entityRepository->createQueryBuilder('p');

            return $qb->where($qb->expr()->orX(
                    $qb->expr()->eq('p.website', ':website'),
                    $qb->expr()->isNull('p.website')
                ))
                ->setParameter('website', $this->website)
                ->orderBy('p.title', 'ASC');
        });
    }
}
