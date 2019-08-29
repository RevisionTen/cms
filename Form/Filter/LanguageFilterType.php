<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Filter;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\FilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\QueryBuilder;

class LanguageFilterType extends FilterType
{
    /** @var array */
    protected $languages;

    /**
     * LanguageFilterType constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->languages = $config['page_languages'] ?? [];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->languages,
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
            $queryBuilder
                ->andWhere('entity.language = :language')
                ->setParameter('language', $data);
        }
    }
}
