<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

class Row extends Element
{
    public function getBlockPrefix(): string
    {
        return 'cms_row';
    }
}
