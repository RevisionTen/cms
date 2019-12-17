<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Filter;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\FilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;
use function array_combine;
use function array_keys;

class TemplateFilterType extends FilterType
{
    /**
     * @var Security
     */
    protected $security;

    /**
     * @var array
     */
    protected $templates;

    /**
     * TemplateFilterType constructor.
     *
     * @param Security     $security
     * @param RequestStack $requestStack
     * @param array        $config
     */
    public function __construct(Security $security, RequestStack $requestStack, array $config)
    {
        $this->security = $security;

        // Get current website.
        $request = $requestStack->getMasterRequest();
        $currentWebsite = $request ? (int) $request->get('currentWebsite') : 1;

        // Get available templates.
        $pageTemplates = $config['page_templates'] ?? null;
        $templates = [];
        foreach ($pageTemplates as $template => $templateConfig) {
            $permission = $templateConfig['permissions']['list'] ?? null;
            // Check if the website matches.
            if (!empty($templateConfig['websites']) && !in_array($currentWebsite, $templateConfig['websites'], true)) {
                // Current website is not is defined websites.
                continue;
            }
            // Check if permission is not explicitly set or user is granted the permission.
            if (null === $permission || $this->security->isGranted($permission)) {
                $templates[$template] = $template;
            }
        }

        $this->templates = $templates;
    }

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->templates,
            'choice_translation_domain' => 'messages',
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     * @param QueryBuilder  $queryBuilder
     * @param FormInterface $form
     * @param array         $metadata
     *
     * @return false|void
     */
    public function filter(QueryBuilder $queryBuilder, FormInterface $form, array $metadata)
    {
        $data = $form->getData();

        if (!empty($data)) {
            $queryBuilder
                ->andWhere('entity.template = :template')
                ->setParameter('template', $data);
        }
    }
}
