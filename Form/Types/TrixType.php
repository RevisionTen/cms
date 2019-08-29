<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class TrixType extends HiddenType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'cms_trix';
    }
}
