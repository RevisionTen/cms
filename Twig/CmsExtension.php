<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function array_push;
use function array_search;
use function array_walk;
use function implode;
use function is_array;
use function json_encode;
use function time;
use function uasort;

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
     * @param TranslatorInterface $translator
     */
    public function __construct(array $config, TranslatorInterface $translator)
    {
        $this->config = $config;
        $this->translator = $translator;
    }

    /**
     * @return array|TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('isElementVisible', [$this, 'isElementVisible']),
            new TwigFunction('editorAttr', [$this, 'editorAttr'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('elementClasses', [$this, 'elementClasses']),
        ];
    }

    /**
     * @return array|TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('sortWeekdays', [$this, 'sortWeekdays']),
        ];
    }

    /**
     * @param array $element
     *
     * @return bool
     */
    public function isElementVisible(array $element): bool
    {
        $laterThanStartDate = isset($element['data']['startDate']) ? (time() >= $element['data']['startDate']) : true;
        $earlierThanEndDate = isset($element['data']['endDate']) ? (time() <= $element['data']['endDate']) : true;

        return $laterThanStartDate && $earlierThanEndDate;
    }

    /**
     * Returns an array of bootstrap spacing css classes.
     *
     * @param array $spacing the amount of spacing
     * @param string $propertyAbr the type of spacing ('m' = margin, 'p' = padding)
     *
     * @return array
     */
    private static function getSpacing(array $spacing, string $propertyAbr): array
    {
        $classes = [];

        // Breakpoint XS is the same as no breakpoint specified in Bootstrap 4 ("mobile first").
        if ('xs' === $spacing['breakpoint']) {
            $spacing['breakpoint'] = null;
        }

        $breakpoint = $spacing['breakpoint'] ? '-'.$spacing['breakpoint'] : '';
        $top = $spacing['top'] ?? null;
        $right = $spacing['right'] ?? null;
        $bottom = $spacing['bottom'] ?? null;
        $left = $spacing['left'] ?? null;
        if (null !== $top) {
            $key = $propertyAbr.'t'.$breakpoint;
            $classes[] = $key.'-'.$top;
        }
        if (null !== $right) {
            $key = $propertyAbr.'r'.$breakpoint;
            $classes[] = $key.'-'.$right;
        }
        if (null !== $bottom) {
            $key = $propertyAbr.'b'.$breakpoint;
            $classes[] = $key.'-'.$bottom;
        }
        if (null !== $left) {
            $key = $propertyAbr.'l'.$breakpoint;
            $classes[] = $key.'-'.$left;
        }

        return $classes;
    }

    /**
     * @param array $element
     *
     * @return string
     */
    public function elementClasses(array $element): string
    {
        $classes = [];

        // Add style classes.
        if (isset($element['data']['styles']) && is_array($element['data']['styles'])) {
            $classes = $element['data']['styles'];
        }

        // Add margin classes.
        if (isset($element['data']['settings']['margins']) && is_array($element['data']['settings']['margins']) && !empty($element['data']['settings']['margins'])) {
            foreach ($element['data']['settings']['margins'] as $spacing) {
                $spacings = self::getSpacing($spacing, 'm');
                if (!empty($spacings)) {
                    array_push($classes, ...$spacings);
                }
            }
        }

        // Add padding classes.
        if (isset($element['data']['settings']['paddings']) && is_array($element['data']['settings']['paddings']) && !empty($element['data']['settings']['paddings'])) {
            foreach ($element['data']['settings']['paddings'] as $spacing) {
                $spacings = self::getSpacing($spacing, 'p');
                if (!empty($spacings)) {
                    array_push($classes, ...$spacings);
                }
            }
        }

        // Add column width classes.
        $widthXS = $element['data']['widthXS'] ?? null;
        if (!empty($widthXS)) {
            $classes[] = 'default' === $widthXS ? 'col' : 'col-'.$widthXS;
        }
        $widthSM = $element['data']['widthSM'] ?? null;
        if (!empty($widthSM)) {
            $classes[] = 'default' === $widthSM ? 'col-sm' : 'col-sm-'.$widthSM;
        }
        $widthMD = $element['data']['widthMD'] ?? null;
        if (!empty($widthMD)) {
            $classes[] = 'default' === $widthMD ? 'col-md' : 'col-md-'.$widthMD;
        }
        $widthLG = $element['data']['widthLG'] ?? null;
        if (!empty($widthLG)) {
            $classes[] = 'default' === $widthLG ? 'col-lg' : 'col-lg-'.$widthLG;
        }
        $widthXL = $element['data']['widthXL'] ?? null;
        if (!empty($widthXL)) {
            $classes[] = 'default' === $widthXL ? 'col-xl' : 'col-xl-'.$widthXL;
        }

        return implode(' ', $classes);
    }

    /**
     * @param array $element
     * @param bool $edit
     * @param string|null $bg
     * @param bool|null $visible
     *
     * @return string
     */
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

        array_walk($attributes, static function (&$value, $key) {
            $value = $key.'="'.$value.'"';
        });

        $padding = $element['data']['settings']['paddings'][0] ?? [
            'breakpoint' => null,
            'top' => null,
            'bottom' => null,
            'left' => null,
            'right' => null,
        ];
        $attributes[] = "data-padding='".json_encode($padding)."'";

        return implode(' ', $attributes);
    }

    /**
     * Sort weekdays correctly.
     *
     * @param array $input
     *
     * @return array
     */
    public function sortWeekDays(array $input): array
    {
        $templateArray = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        uasort($input, static function ($a, $b) use ($templateArray) {
            $keyA = array_search($a, $templateArray, true);
            $keyB = array_search($b, $templateArray, true);

            return $keyA < $keyB ? -1 : 1;
        });

        return $input;
    }
}
