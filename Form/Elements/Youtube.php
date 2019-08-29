<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use function preg_match;
use function strpos;

class Youtube extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('youtubeId', TextType::class, [
            'label' => 'Youtube Video ID or URL',
            'required' => true,
        ]);

        $builder->add('optin', CheckboxType::class, [
            'label' => 'Require opt-in by user',
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) {
            $data = $event->getData();

            if (isset($data['youtubeId']) && $data['youtubeId']) {
                $youtubeId = $data['youtubeId'];

                if (strpos($youtubeId, 'youtu')) {
                    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $youtubeId, $matches);
                    if (isset($matches[1]) && $matches[1]) {
                        $youtubeId = $matches[1];
                    }
                }

                $data['youtubeId'] = $youtubeId;

                $event->setData($data);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'cms_youtube';
    }
}
