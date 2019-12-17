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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\NotBlank;
use function array_combine;
use function array_keys;

class PageType extends AbstractType
{
    /**
     * @var Security
     */
    protected $security;

    /**
     * PageType constructor.
     *
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'page.label.title',
            'translation_domain' => 'cms',
            'constraints' => new NotBlank(),
        ]);

        if ($options['page_websites']) {
            $builder->add('website', ChoiceType::class, [
                'label' => 'page.label.website',
                'translation_domain' => 'cms',
                'multiple' => false,
                'choices' => $options['page_websites'],
                'constraints' => new NotBlank(),
                'attr' => [
                    'class' => 'custom-select',
                ],
            ]);
        }

        $builder->add('language', ChoiceType::class, [
            'label' => 'page.label.language',
            'translation_domain' => 'cms',
            'choices' => $options['page_languages'] ?: [
                'English' => 'en',
                'German' => 'de',
            ],
            'choice_translation_domain' => 'messages',
            'placeholder' => 'page.placeholder.language',
            'constraints' => new NotBlank(),
            'attr' => [
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('template', ChoiceType::class, [
            'label' => 'page.label.template',
            'translation_domain' => 'cms',
            'choices' => array_combine(array_keys($options['page_templates']), array_keys($options['page_templates'])),
            'choice_translation_domain' => 'messages',
            'constraints' => new NotBlank(),
            'attr' => [
                'data-condition' => true,
                'class' => 'custom-select',
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'page.label.description',
            'translation_domain' => 'cms',
            'constraints' => new NotBlank(),
        ]);

        $builder->add('image', UploadType::class, [
            'label' => 'page.label.image',
            'translation_domain' => 'cms',
            'required' => false,
            'show_file_picker' => true,
        ]);

        if ($this->security->isGranted('page_change_seo_settings')) {
            $builder->add('robots', ChoiceType::class, [
                'label' => 'page.label.robots',
                'translation_domain' => 'cms',
                'choices' => [
                    'page.choices.index' => 'index',
                    'page.choices.noindex' => 'noindex',
                    'page.choices.follow' => 'follow',
                    'page.choices.nofollow' => 'nofollow',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
        }

        $builder->add('meta', $options['page_metatype'], [
            'label' => false,
            'allow_extra_fields' => true,
        ]);

        // Change the meta type depending on the chosen template.
        $formModifier = static function (FormInterface $form, $template = null) use ($options) {
            if ($template) {
                $metaType = $options['page_templates'][$template]['metatype'] ?? $options['page_metatype'];
            } else {
                $metaType = $options['page_metatype'];
            }

            $form->add('meta', $metaType, [
                'label' => false,
                'allow_extra_fields' => true,
            ]);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($formModifier) {
            $data = $event->getData();
            $template = $data['template'] ?? null;
            $formModifier($event->getForm(), $template);
        });

        $builder->get('template')->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($formModifier) {
            $template = $event->getForm()->getData();
            $formModifier($event->getForm()->getParent(), $template);
        });

        $builder->add('save', SubmitType::class);
    }

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'page_websites' => null,
            'page_templates' => null,
            'page_languages' => null,
            'page_metatype' => PageMetaType::class,
        ]);
    }
}
