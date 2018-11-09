<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Twig;

use Symfony\Component\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
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
     * @param array               $config
     * @param TranslatorInterface $translator
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
            new TwigFunction('elementClasses', [$this, 'elementClasses']),
        );
    }

    public function isElementVisible(array $element): bool
    {
        $laterThanStartDate = isset($element['data']['startDate']) ? (time() >= $element['data']['startDate']) : true;
        $earlierThanEndDate = isset($element['data']['endDate']) ? (time() <= $element['data']['endDate']) : true;

        return $laterThanStartDate && $earlierThanEndDate;
    }

    /**
     * Returns an array of bootstrap spacing css classes.
     *
     * @param array  $spacing     the amount of spacing
     * @param string $propertyAbr the type of spacing ('m' = margin, 'p' = padding)
     *
     * @return array
     */
    private static function getSpacing(array $spacing, string $propertyAbr): array
    {
        $classes = [];

        $breakpoint = $spacing['breakpoint'] ? '-'.$spacing['breakpoint'] : '';
        $top = $spacing['top'] ?? null;
        $right = $spacing['right'] ?? null;
        $bottom = $spacing['bottom'] ?? null;
        $left = $spacing['left'] ?? null;
        if ($top) {
            $key = $propertyAbr.'t'.$breakpoint;
            $classes[$key] = $key.'-'.$top;
        }
        if ($right) {
            $key = $propertyAbr.'r'.$breakpoint;
            $classes[$key] = $key.'-'.$right;
        }
        if ($bottom) {
            $key = $propertyAbr.'b'.$breakpoint;
            $classes[$key] = $key.'-'.$bottom;
        }
        if ($left) {
            $key = $propertyAbr.'l'.$breakpoint;
            $classes[$key] = $key.'-'.$left;
        }

        return $classes;
    }

    public function elementClasses(array $element)
    {
        $classes = [];

        // Add style classes.
        if (isset($element['data']['styles']) && \is_array($element['data']['styles'])) {
            $classes = $element['data']['styles'];
        }

        // Add margin classes.
        if (isset($element['data']['settings']['margins']) && \is_array($element['data']['settings']['margins']) && !empty($element['data']['settings']['margins'])) {
            foreach ($element['data']['settings']['margins'] as $spacing) {
                $classes += self::getSpacing($spacing, 'm');
            }
        }

        // Add padding classes.
        if (isset($element['data']['settings']['paddings']) && \is_array($element['data']['settings']['paddings']) && !empty($element['data']['settings']['paddings'])) {
            foreach ($element['data']['settings']['paddings'] as $spacing) {
                $classes += self::getSpacing($spacing, 'p');
            }
        }

        return implode(' ', $classes);
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
            'data-type' => $this->config['page_elements'][$element['elementName']]['type'] ?? $element['elementName'],
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
