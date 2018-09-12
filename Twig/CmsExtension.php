<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Twig;

use Symfony\Component\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CmsExtension extends AbstractExtension
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * CmsExtension constructor.
     *
     * @param array $config
     */
    public function __construct(array $config, TranslatorInterface $translator)
    {
        $this->config = $config;
        $this->translator = $translator;
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('isElementVisible', [$this, 'isElementVisible']),
            new TwigFunction('editorAttr', [$this, 'editorAttr'], [
                'is_safe' => ['html'],
            ]),
        );
    }

    public function isElementVisible(array $element): bool
    {
        $laterThanStartDate = isset($element['data']['startDate']) ? (time() >= $element['data']['startDate']) : true;
        $earlierThanEndDate = isset($element['data']['endDate']) ? (time() <= $element['data']['endDate']) : true;

        return $laterThanStartDate && $earlierThanEndDate;
    }

    public function editorAttr(array $element, bool $edit, string $bg = null, bool $visible = null): string
    {
        if (!$edit) {
            return '';
        }

        $attributes = [
            'data-uuid' => $element['uuid'],
            'data-label' => $this->translator->trans($element['elementName']),
            'data-enabled' => ($element['enabled'] ?? true) ? '1' : '0',
        ];

        if (isset($this->config['page_elements'][$element['elementName']]['children'])) {
            $attributes['data-children'] = implode(',', $this->config['page_elements'][$element['elementName']]['children']);
        }

        if ('Section' === $element['elementName']) {
            $attributes['data-disabled-actions'] = 'shift';
        }

        if (null === $bg) {
            $attributes['data-bg'] = 'bg-primary';
        } else {
            $attributes['data-bg'] = $bg;
        }

        if (null !== $visible) {
            $attributes['data-visible'] = $visible ? '1' : '0';
        }

        array_walk($attributes, function (&$value, $key) {
            $value = $key.'="'.$value.'"';
        });

        return implode(' ', $attributes);
    }
}
