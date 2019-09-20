<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return array(
            new TwigFunction('cms_menu', [MenuRuntime::class, 'renderMenu'], [
                'is_safe' => ['html'],
            ]),
        );
    }
}
