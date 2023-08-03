<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Twig;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;
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
    private array $config;

    private TranslatorInterface $translator;

    protected Security $security;

    protected AsciiSlugger $slugger;

    public function __construct(array $config, TranslatorInterface $translator, Security $security)
    {
        $this->config = $config;
        $this->translator = $translator;
        $this->security = $security;
        $this->slugger = new AsciiSlugger($config['slugger_locale'] ?? 'de');
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
            new TwigFilter('slugify', [$this, 'slugify']),
        ];
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
            // Bootstrap < 5:
            $key = $propertyAbr.'r'.$breakpoint;
            $classes[] = $key.'-'.$right;
            // Boostrap 5:
            $key = $propertyAbr.'e'.$breakpoint;
            $classes[] = $key.'-'.$right;
        }
        if (null !== $bottom) {
            $key = $propertyAbr.'b'.$breakpoint;
            $classes[] = $key.'-'.$bottom;
        }
        if (null !== $left) {
            // Bootstrap < 5:
            $key = $propertyAbr.'l'.$breakpoint;
            $classes[] = $key.'-'.$left;
            // Boostrap 5:
            $key = $propertyAbr.'s'.$breakpoint;
            $classes[] = $key.'-'.$left;
        }

        return $classes;
    }

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

    private function addDisabledActionsFromPermission(array $disabledActions, array $elementConfig, string $name): array
    {
        $permission = $elementConfig['permissions'][$name] ?? null;
        if (!empty($permission) && $this->security->isGranted($permission) === false) {
            $disabledActions[$name] = $name;
        }

        return $disabledActions;
    }

    public function editorAttr(array $element, bool $edit, string $bg = null, bool $visible = null): string
    {
        if (!$edit) {
            return '';
        }

        $elementConfig = $this->config['page_elements'][$element['elementName']];

        $attributes = [
            'data-uuid' => $element['uuid'],
            'data-label' => $this->translator->trans($element['elementName']),
            'data-enabled' => ($element['enabled'] ?? true) ? '1' : '0',
            'data-type' => $elementConfig['type'] ?? $element['elementName'],
        ];

        if (isset($elementConfig['children'])) {
            $attributes['data-children'] = implode(',', $elementConfig['children']);
        }

        $disabledActions = [];
        if ('Section' === $element['elementName']) {
            $disabledActions['shift'] = 'shift';
        }
        $disabledActions = $this->addDisabledActionsFromPermission($disabledActions, $elementConfig, 'edit');
        // Todo: Not yet configurable permissions:
        #$disabledActions = $this->addDisabledActionsFromPermission($disabledActions, $elementConfig, 'shift');
        #$disabledActions = $this->addDisabledActionsFromPermission($disabledActions, $elementConfig, 'delete');
        #$disabledActions = $this->addDisabledActionsFromPermission($disabledActions, $elementConfig, 'duplicate');
        #$disabledActions = $this->addDisabledActionsFromPermission($disabledActions, $elementConfig, 'enable');
        #$disabledActions = $this->addDisabledActionsFromPermission($disabledActions, $elementConfig, 'disable');

        if (!empty($disabledActions)) {
            $attributes['data-disabled-actions'] = implode(',', $disabledActions);
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

    public function slugify(string $input, string $separator = '-'): string
    {
        return $this->slugger->slug($input, $separator)->toString();
    }
}
