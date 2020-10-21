<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Form\Types;

use RevisionTen\CMS\DataTransformer\FileTransformer;
use RevisionTen\CMS\DataTransformer\FileWithMetaDataTransformer;
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
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function is_object;
use function is_string;

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

    /**
     * @param UploadedFile $uploadedFile
     * @param string       $upload_dir
     * @param boolean      $keepOriginalFileName
     *
     * @return array|null
     */
    public function storeFile(UploadedFile $uploadedFile, string $upload_dir, bool $keepOriginalFileName = false): ?array
    {
        $title = $uploadedFile->getClientOriginalName();
        // Remove extension.
        $title = str_replace('.'.$uploadedFile->getClientOriginalExtension(), '', $title);

        return $this->fileService->createFile(
            null,
            $uploadedFile,
            $title,
            $upload_dir,
            $this->website,
            $this->language,
            $keepOriginalFileName
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
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

        if ($options['file_with_meta_data']) {
            $builder->addModelTransformer(new FileWithMetaDataTransformer());

            $builder->add('uuid', HiddenType::class, [
                'required' => false,
            ]);
            $builder->add('version', HiddenType::class, [
                'required' => false,
            ]);
            $builder->add('title', HiddenType::class, [
                'required' => false,
            ]);
            $builder->add('size', HiddenType::class, [
                'required' => false,
            ]);
            $builder->add('width', HiddenType::class, [
                'required' => false,
            ]);
            $builder->add('height', HiddenType::class, [
                'required' => false,
            ]);
            $builder->add('mimeType', HiddenType::class, [
                'required' => false,
            ]);
        } else {
            $builder->addModelTransformer(new FileTransformer());
        }

        $addDeleteReplaceForm = static function (FormInterface $form) use ($options): void {
            if ($options['allow_replace']) {
                $form->add('uploadedFile', FileType::class, [
                    'label' => false,
                    'attr' => $options['attr'],
                    // The file field to replace the file must not be required.
                    'required' => false,
                ]);
            } else {
                $form->remove('uploadedFile');
            }

            if ($options['allow_delete']) {
                $form->add('delete', CheckboxType::class, [
                    'label' => 'upload.label.delete',
                    'translation_domain' => 'cms',
                    'mapped' => true,
                    'required' => false,
                ]);
            }
        };

        // Add delete and replace form if the form is loaded with existing data.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($addDeleteReplaceForm, $options): void {
            $data = $event->getData();
            if (!empty($data) && ($options['file_with_meta_data'] || is_string($data))) {
                $addDeleteReplaceForm($event->getForm());
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options, $addDeleteReplaceForm): void {
            $form = $event->getForm();
            $requestHandler = $form->getConfig()->getRequestHandler();
            $data = $event->getData();
            $delete = !empty($data['delete']);

            $file = $data['file'] ?? null;
            $uuid = $data['uuid'] ?? null;
            $version = $data['version'] ?? null;
            $title = $data['title'] ?? null;
            $size = $data['size'] ?? null;
            $width = $data['width'] ?? null;
            $height = $data['height'] ?? null;
            $mimeType = $data['mimeType'] ?? null;

            /**
             * @var UploadedFile $uploadedFile
             */
            $uploadedFile = $data['uploadedFile'] ?? null;
            $isFileUpload = $requestHandler->isFileUpload($uploadedFile);
            $constraints = $options['constraints'] ?? NULL;

            if ($isFileUpload) {
                // Validate file field.
                $valid = true;
                if ($constraints) {
                    $uploadedFileForm = $form->get('uploadedFile');
                    /**
                     * @var ConstraintViolationListInterface $violations
                     */
                    $violations = $this->validator->validate($uploadedFile, $constraints);
                    foreach ($violations as $violation) {
                        /**
                         * @var ConstraintViolationInterface $violation
                         */
                        $formError = new FormError(
                            $violation->getMessage(),
                            $violation->getMessageTemplate(),
                            $violation->getParameters(),
                            $violation->getPlural()
                        );
                        $uploadedFileForm->addError($formError);
                        $valid = false;
                    }
                }
                if ($valid) {
                    // Save the file.
                    $file = $this->storeFile($uploadedFile, $options['upload_dir'], $options['keepOriginalFileName']);
                    // Overwrite uploaded File with file path string.
                    $event->setData([
                        'file' => $file['path'],
                        'uuid' => $file['uuid'] ?? null,
                        'version' => $file['version'] ?? null,
                        'title' => $file['title'] ?? null,
                        'size' => $file['size'] ?? null,
                        'width' => $file['width'] ?? null,
                        'height' => $file['height'] ?? null,
                        'mimeType' => $file['mimeType'] ?? null,
                    ]);
                } else {
                    $event->setData(null);
                }
            } elseif ($delete) {
                // Delete the file.
                $event->setData(null);
            } elseif (is_string($file)) {
                // Keep the file.
                $event->setData([
                    'file' => $file,
                    'uuid' => $uuid,
                    'version' => $version,
                    'title' => $title,
                    'size' => $size,
                    'width' => $width,
                    'height' => $height,
                    'mimeType' => $mimeType,
                ]);
            } elseif (is_object($file) && ($file instanceof UploadedFile || $file instanceof File)) {
                // File is object, just get the file path.
                $event->setData([
                    'file' => $file->getPathname(),
                    'uuid' => $uuid,
                    'version' => $version,
                    'title' => $title,
                    'size' => $size,
                    'width' => $width,
                    'height' => $height,
                    'mimeType' => $mimeType,
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
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['show_file_picker'] = $options['show_file_picker'];
        $view->vars['file_picker_mime_types'] = $options['file_picker_mime_types'];

        parent::buildView($view, $form, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'label' => false,
            'attr' => [],
            'upload_dir' => '/uploads/files/',
            'keep_deleted_file' => true,
            'allow_delete' => true,
            'allow_replace' => true,
            'show_file_picker' => true,
            'file_picker_mime_types' => null,
            'file_with_meta_data' => false,
            // Do not validate this form type with the passed constraints, use them for the file field instead.
            'validation_groups' => false,
            'constraints' => null,
            'allow_extra_fields' => true,
            'keepOriginalFileName' => false,
        ]);

        $resolver->setDeprecated('keep_deleted_file');
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return 'cms_upload';
    }
}
