<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Command\FileCreateCommand;
use RevisionTen\CMS\Command\FileUpdateCommand;
use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\FileRead;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Exception;
use function getimagesize;
use function json_decode;
use function json_encode;
use function in_array;

/**
 * Class FileService.
 */
class FileService
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var AggregateFactory
     */
    protected $aggregateFactory;

    /**
     * @var CommandBus
     */
    protected $commandBus;

    /**
     * @var UserRead
     */
    protected $user;

    /**
     * @var string
     */
    protected $project_dir;

    /**
     * PageService constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     * @param CommandBus             $commandBus
     * @param TokenStorageInterface  $tokenStorage
     * @param string                 $project_dir
     */
    public function __construct(EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, CommandBus $commandBus, TokenStorageInterface $tokenStorage, string $project_dir)
    {
        $this->entityManager = $entityManager;
        $this->aggregateFactory = $aggregateFactory;
        $this->commandBus = $commandBus;
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser() : -1;
        $this->project_dir = $project_dir;
    }

    /**
     * A wrapper function to execute a Command.
     * Returns true if the command succeeds.
     *
     * @param string      $commandClass
     * @param array       $data
     * @param string      $aggregateUuid
     * @param int         $onVersion
     * @param bool        $queue
     * @param string|null $commandUuid
     * @param int|null    $userId
     *
     * @return bool
     * @throws \Exception
     */
    public function runCommand(string $commandClass, array $data, string $aggregateUuid, int $onVersion, bool $queue = false, string $commandUuid = null, int $userId = null): bool
    {
        if (null === $userId) {
            $userId = $this->user->getId();
        }

        $command = new $commandClass($userId, $commandUuid, $aggregateUuid, $onVersion, $data);

        return $this->commandBus->dispatch($command, $queue);
    }

    public function saveUploadedFile(UploadedFile $file, string $upload_dir, string $filename): string
    {
        $public_dir = $this->project_dir.'/public';

        // Move the file to the uploads directory.
        $file->move($public_dir.$upload_dir, $filename);

        return $upload_dir.$filename;
    }

    /**
     * Get the size in pixels of an image.
     * Returns width and height.
     *
     * @param UploadedFile $file
     * @return array
     */
    private function getImageDimensions(UploadedFile $file): array
    {
        $imageMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
        ];
        $width = null;
        $height = null;
        if (in_array($file->getMimeType(), $imageMimeTypes, true)) {
            try {
                $dimensions = getimagesize($file->getRealPath());
                if ($dimensions) {
                    [$width, $height] = $dimensions;
                }
            } catch (Exception $exception) {}
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    public function createFile(string $uuid = null, UploadedFile $file, string $title, string $upload_dir, int $website, string $language, bool $keepOriginalFileName = false): ?array
    {
        if (null === $uuid) {
            $uuid = Uuid::uuid1()->toString();
        }
        $version = 0;
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Get file size in px.
        $dimensions = $this->getImageDimensions($file);
        $width = $dimensions['width'];
        $height = $dimensions['height'];

        if ($keepOriginalFileName) {
            $fileName = $file->getClientOriginalName();
            $fileExtension = $file->getClientOriginalExtension();
            $fileName = str_replace($fileExtension, '', $fileName);
            $slugify = new Slugify([
                'lowercase' => false,
            ]);
            $fileName = $slugify->slugify($fileName).'.'.$fileExtension;
        } else {
            $slugify = new Slugify();
            $fileName = $slugify->slugify($title).'.'.$file->getClientOriginalExtension();
        }
        // Save files in a versioned sub folder.
        $fileFolder = '/'.$uuid.'/'.($version + 1).'/';
        $filePath = $this->saveUploadedFile($file, rtrim($upload_dir, '/').$fileFolder, $fileName);

        // Create file aggregate.
        $success = $this->runCommand(FileCreateCommand::class, [
            'title' => $title,
            'path' => $filePath,
            'mimeType' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'website' => $website,
            'language' => $language,
        ], $uuid, 0);

        if (!$success) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'path' => $filePath,
            'version' => $version + 1,
            'title' => $title,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'mimeType' => $mimeType,
        ];
    }

    public function replaceFile(array $file, UploadedFile $newFile = null, string $title, string $upload_dir, string $language = null, int $website = null, bool $keepOriginalFileName = false): ?array
    {
        $uuid = $file['uuid'];

        /**
         * Get Aggregate newest version.
         *
         * @var File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($uuid, File::class);
        $version = $aggregate->getVersion();

        // Check If the file really needs to be updated.
        $sameLanguage = null === $language || $language === $aggregate->language;
        $sameWebsite = null === $website || $website === $aggregate->website;
        if (null === $newFile && $title === $aggregate->title && $sameLanguage && $sameWebsite) {
            // Nothing to update.
            return $file;
        }

        // Update file aggregate.
        $payload = [];
        if ($title !== $aggregate->title) {
            $payload['title'] = $title;
        }
        if ($language && $language !== $aggregate->language) {
            $payload['language'] = $language;
        }
        if ($website && $website !== $aggregate->website) {
            $payload['website'] = $website;
        }
        // Update file properties if new file was passed.
        if (null !== $newFile) {
            $mimeType = $newFile->getMimeType();
            $size = $newFile->getSize();

            // Get file size in px.
            $dimensions = $this->getImageDimensions($newFile);
            $width = $dimensions['width'];
            $height = $dimensions['height'];

            if ($keepOriginalFileName) {
                $fileName = $newFile->getClientOriginalName();
                $fileExtension = $newFile->getClientOriginalExtension();
                $fileName = str_replace($fileExtension, '', $fileName);
                $slugify = new Slugify([
                    'lowercase' => false,
                ]);
                $fileName = $slugify->slugify($fileName).'.'.$fileExtension;
            } else {
                $slugify = new Slugify();
                $fileName = $slugify->slugify($title).'.'.$newFile->getClientOriginalExtension();
            }
            // Save files in a versioned sub folder.
            $fileFolder = '/'.$uuid.'/'.($version + 1).'/';
            $filePath = $this->saveUploadedFile($newFile, rtrim($upload_dir, '/').$fileFolder, $fileName);

            if ($filePath !== $aggregate->path) {
                $payload['path'] = $filePath;
            }
            if ($mimeType !== $aggregate->mimeType) {
                $payload['mimeType'] = $mimeType;
            }
            if ($size !== $aggregate->size) {
                $payload['size'] = $size;
            }
            if ($width !== $aggregate->width) {
                $payload['width'] = $width;
            }
            if ($height !== $aggregate->height) {
                $payload['height'] = $height;
            }
        }

        $success = $this->runCommand(FileUpdateCommand::class, $payload, $uuid, $version);

        if (!$success) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'path' => $filePath ?? $aggregate->path,
            'mimeType' => $mimeType ?? $aggregate->mimeType,
            'version' => $version + 1,
            'size' => $size ?? $aggregate->size,
            'width' => $width ?? $aggregate->width,
            'height' => $height ?? $aggregate->height,
            'title' => $title ?? $aggregate->title,
        ];
    }

    public function getFile(string $uuid, int $version): ?array
    {
        /**
         * Get Aggregate newest version.
         *
         * @var File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($uuid, File::class, $version);

        return [
            'uuid' => $uuid,
            'path' => $aggregate->path,
            'mimeType' => $aggregate->mimeType,
            'version' => $aggregate->getVersion(),
            'size' => $aggregate->size,
        ];
    }

    public function deleteFile(array $file): ?array
    {
        // Delete the file (detaches the file aggregate).
        // $uuid = $file['uuid'];

        return null;
    }

    /**
     * Update the FileRead entity.
     *
     * @param string $fileUuid
     */
    public function updateFileRead(string $fileUuid): void
    {
        /**
         * @var File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($fileUuid, File::class);

        /**
         * Get website.
         *
         * @var Website|null $website
         */
        $website = $aggregate->website ? $this->entityManager->getRepository(Website::class)->find($aggregate->website) : null;

        // Build FileRead entity from Aggregate.
        $fileRead = $this->entityManager->getRepository(FileRead::class)->findOneBy(['uuid' => $fileUuid]) ?? new FileRead();
        $fileRead->setVersion($aggregate->getStreamVersion());
        $fileRead->setUuid($fileUuid);
        $fileData = json_decode(json_encode($aggregate), true);
        $fileRead->setPayload($fileData);
        $fileRead->setTitle($aggregate->title);
        $fileRead->setPath($aggregate->path);
        $fileRead->setSize($aggregate->size);
        $fileRead->setMimeType($aggregate->mimeType);
        $fileRead->setWebsite($website);
        $fileRead->setLanguage($aggregate->language);
        $fileRead->setCreated($aggregate->created);
        $fileRead->setModified($aggregate->modified);

        // Persist FileRead entity.
        $this->entityManager->persist($fileRead);
        $this->entityManager->flush();
    }
}
