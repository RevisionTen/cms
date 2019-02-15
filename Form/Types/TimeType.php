<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\ReversedTransformer;

class TimeType extends \Symfony\Component\Form\Extension\Core\Type\TimeType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        // Save in a format that corresponds to the form options.
        // Fix for https://github.com/symfony/symfony/issues/30253
        if ('string' === $options['input'] && 'single_text' === $options['widget']) {
            $format = 'H';

            if ($options['with_minutes']) {
                $format .= ':i';
            }
            if ($options['with_seconds']) {
                $format .= ':s';
            }

            $builder->resetModelTransformers();
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'], $format)
            ));
        }
    }
}
