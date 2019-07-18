<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Filter;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\FilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\QueryBuilder;

class TemplateFilterType extends FilterType
{
    /** @var array */
    protected $templates;

    /**
     * TemplateFilterType constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $pageTemplates = $config['page_templates'] ?? [];

        if (!empty($pageTemplates)) {
            $pageTemplates = array_keys($pageTemplates);
            $pageTemplates = array_combine($pageTemplates, $pageTemplates);
        }

        $this->templates = $pageTemplates;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => $this->templates,
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function filter(QueryBuilder $queryBuilder, FormInterface $form, array $metadata)
    {
        $data = $form->getData();

        if (!empty($data)) {
            $queryBuilder->andWhere('entity.template = :template')
                ->setParameter('template', $data);
        }
    }
}
