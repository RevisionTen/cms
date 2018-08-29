<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form;

use RevisionTen\CMS\Form\Types\UploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', TextType::class, [
            'label' => 'What is the title of your page?',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('website', ChoiceType::class, [
            'label' => 'Website',
            'multiple' => false,
            'choices' => $options['page_websites'],
            'constraints' => new NotBlank(),
        ]);

        $builder->add('template', ChoiceType::class, [
            'label' => 'Page Template',
            'choices' => array_combine(array_keys($options['page_templates']), array_keys($options['page_templates'])),
            'constraints' => new NotBlank(),
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'What is the description of your page?',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'Teaser image',
            'required' => false,
        ]);

        $builder->add('robots', ChoiceType::class, [
            'label' => 'Search engine settings',
            'choices' => [
                'Index' => 'index',
                'Don\'t index' => 'noindex',
                'Follow' => 'follow',
                'Don\'t follow' => 'nofollow',
            ],
            'multiple' => true,
            'expanded' => true,
        ]);

        $builder->add('language', ChoiceType::class, [
            'label' => 'Language',
            'choices' => $options['page_languages'] ? $options['page_languages'] : [
                'English' => 'en',
                'German' => 'de',
            ],
            'placeholder' => 'Language',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('meta', $options['page_metatype'], [
            'label' => false,
        ]);

        // Change the meta type depending on the chosen template.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['template'])) {
                $metaType = $options['page_templates'][$data['template']]['metatype'] ?? $options['page_metatype'];
            } else {
                $metaType = $options['page_metatype'];
            }

            $form->add('meta', $metaType, [
                'label' => false,
            ]);
        });

        $builder->add('save', SubmitType::class);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'page_websites' => null,
            'page_templates' => null,
            'page_languages' => null,
            'page_metatype' => PageMetaType::class,
        ]);
    }
}
