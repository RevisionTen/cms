<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use RevisionTen\CMS\DataTransformer\FileTransformer;
use RevisionTen\CMS\Services\FileService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UploadType extends AbstractType
{
    /**
     * @var FileService
     */
    private $fileService;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var int
     */
    protected $website = 1;

    /**
     * @var string
     */
    protected $language = 'en';

    /**
     * UploadType constructor.
     *
     * @param FileService        $fileService
     * @param RequestStack       $requestStack
     * @param ValidatorInterface $validator
     */
    public function __construct(FileService $fileService, RequestStack $requestStack, ValidatorInterface $validator)
    {
        $this->fileService = $fileService;
        $this->validator = $validator;

        $request = $requestStack->getMasterRequest();
        if (null !== $request) {
            $this->website = $request->get('currentWebsite') ?? ($request->get('websiteId') ?? $this->website);
            $this->language = $request->getLocale();
        }
    }

    public function storeFile(UploadedFile $uploadedFile, string $upload_dir): ?string
    {
        $file = $this->fileService->createFile(null, $uploadedFile, $uploadedFile->getClientOriginalName(), $upload_dir, $this->website, $this->language);

        return $file['path'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', HiddenType::class, [
            'label' => false,
            'required' => false,
            'attr' => $options['attr'],
        ]);

        $builder->add('uploadedFile', FileType::class, [
            'label' => false,
            'required' => $options['required'],
            'attr' => $options['attr'],
        ]);

        $builder->addModelTransformer(new FileTransformer());

        $addDeleteReplaceForm = static function (FormInterface $form): void {
            $form->add('delete', CheckboxType::class, [
                'label' => 'delete the existing file',
                'mapped' => true,
                'required' => false,
            ]);
        };

        // Add delete and replace form if the form is loaded with existing data.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($addDeleteReplaceForm): void {
            $data = $event->getData();
            if (!empty($data) && is_string($data) ) {
                $addDeleteReplaceForm($event->getForm());
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options, $addDeleteReplaceForm): void {
            $form = $event->getForm();
            $requestHandler = $form->getConfig()->getRequestHandler();
            $data = $event->getData();
            $delete = !empty($data['delete']);
            $file = $data['file'] ?? null;
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
            $uploadedFile = $data['uploadedFile'] ?? null;
            $isFileUpload = $requestHandler->isFileUpload($uploadedFile);
            $constraints = $options['constraints'] ?? NULL;

            if ($isFileUpload) {
                // Validate file field.
                $valid = true;
                if ($constraints) {
                    /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
                    $violations = $this->validator->validate($uploadedFile, $constraints);
                    foreach ($violations as $violation) {
                        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
                        $formError = new FormError(
                            $violation->getMessage(),
                            $violation->getMessageTemplate(),
                            $violation->getParameters(),
                            $violation->getPlural()
                        );
                        $uploadedFileForm = $form->get('uploadedFile');
                        $uploadedFileForm->addError($formError);
                        $valid = false;
                    }
                }
                if ($valid) {
                    // Save the file.
                    $file = $this->storeFile($uploadedFile, $options['upload_dir']);
                    // Overwrite uploaded File with file path string.
                    $event->setData([
                        'file' => $file,
                    ]);
                }
            } elseif ($delete) {
                // Delete the file.
                $event->setData(null);
            } elseif (is_string($file)) {
                // Keep the file.
                $event->setData([
                    'file' => $file
                ]);
            } elseif (is_object($file) && ($file instanceof UploadedFile || $file instanceof File)) {
                // File is object, just get the file path.
                $event->setData([
                    'file' => $file->getPathname(),
                ]);
            } else {
                $event->setData(null);
            }

            // Add delete and replace form if the form has submitted data.
            $data = $event->getData();
            $file = $data['file'] ?? null;
            if ($file) {
                $addDeleteReplaceForm($event->getForm());
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'label' => false,
            'attr' => [],
            'upload_dir' => '/uploads/files/',
            'keep_deleted_file' => true,
            // Do not validate this form type with the passed constraints, use them for the file field insead.
            'validation_groups' => false,
            'constraints' => null,
        ]);

        $resolver->setDeprecated('keep_deleted_file');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'cms_upload';
    }
}
