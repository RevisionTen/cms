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

class Vimeo extends Element
{
    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('vimeoId', TextType::class, [
            'label' => 'element.label.vimeoId',
            'translation_domain' => 'cms',
            'required' => true,
        ]);

        $builder->add('optin', CheckboxType::class, [
            'label' => 'element.label.optin',
            'translation_domain' => 'cms',
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) {
            $data = $event->getData();

            if (isset($data['vimeoId']) && $data['vimeoId']) {
                $vimeoId = $data['vimeoId'];

                // Transform URL to video id.
                if (strpos($vimeoId, 'vimeo')) {
                    preg_match('/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11})[?]?.*/', $vimeoId, $matches);
                    if (isset($matches[5]) && $matches[5]) {
                        $vimeoId = $matches[5];
                    }
                }

                $data['vimeoId'] = $vimeoId;

                $event->setData($data);
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_vimeo';
    }
}
