<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Elements;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class Youtube extends Element
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();

            if (isset($data['youtubeId']) && $data['youtubeId']) {
                $youtubeId = $data['youtubeId'];

                if (strpos($youtubeId, 'youtu')) {
                    preg_match("#(?<=v=)[a-zA-Z0-9-]+(?=&)|(?<=v\/)[^&\n]+(?=\?)|(?<=embed/)‌​[^&\n]+|(?<=v=)[^&\n‌​]+|(?<=youtu.be/)[^&‌​\n]+#/u", $youtubeId, $matches);
                    if (isset($matches[0]) && $matches[0]) {
                        $youtubeId = $matches[0];
                    }
                }

                $data['youtubeId'] = $youtubeId;

                $event->setData($data);
            }
        });
    }
}
