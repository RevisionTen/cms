<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Command\FileCreateCommand;
use RevisionTen\CMS\Command\FileUpdateCommand;
use RevisionTen\CMS\Model\User;
use RevisionTen\CQRS\Model\EventQeueObject;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\EventBus;
use RevisionTen\CQRS\Services\EventStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class FileService.
 */
class FileService
{
    /**
     * @var AggregateFactory
     */
    private $aggregateFactory;

    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    private $project_dir;

    /**
     * PageService constructor.
     *
     * @param \RevisionTen\CQRS\Services\AggregateFactory $aggregateFactory
     * @param \RevisionTen\CQRS\Services\CommandBus       $commandBus
     * @param string                                      $project_dir
     */
    public function __construct(AggregateFactory $aggregateFactory, CommandBus $commandBus, TokenStorageInterface $tokenStorage, string $project_dir)
    {
        $this->aggregateFactory = $aggregateFactory;
        $this->commandBus = $commandBus;
        $this->user = $tokenStorage->getToken()->getUser();
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
     * @param string|null $commandUuid
     * @param int|null    $user
     *
     * @return bool
     */
    public function runCommand(string $commandClass, array $data, string $aggregateUuid, int $onVersion, bool $qeue = false, string $commandUuid = null, int $userId = null): bool
    {
        if (null === $userId) {
            $userId = $this->user->getId();
        }

        $success = false;
        $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };

        $command = new $commandClass($userId, $commandUuid, $aggregateUuid, $onVersion, $data, $successCallback);

        $this->commandBus->dispatch($command, $qeue);

        return $success;
    }

    public function saveUploadedFile(UploadedFile $file, string $upload_dir, string $filename)
    {
        $public_dir = $this->project_dir.'/public';

        // Move the file to the uploads directory.
        $newFileName = $filename.'.'.$file->getClientOriginalExtension();

        /** @var File $storedFiled */
        $storedFiled = $file->move($public_dir.$upload_dir, $newFileName);

        $filePath = $upload_dir.$newFileName;

        return $filePath;
    }

    public function createFile(string $uuid = null, UploadedFile $file, string $title, string $upload_dir): ?array
    {
        if (null === $uuid) {
            $uuid = Uuid::uuid1()->toString();
        }
        $version = 0;
        $mimeType = $file->getMimeType();

        // Todo: Get dimensions.

        $filePath = $this->saveUploadedFile($file, $upload_dir, $uuid.'-v'.$version);

        // Create file aggregate.
        $success = $this->runCommand(FileCreateCommand::class, [
            'title' => $title,
            'path' => $filePath,
        ], $uuid,0);

        if (!$success) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'path' => $filePath,
            'mimeType' => $mimeType,
            'version' => $version,
            'title' => $title,
        ];
    }

    public function replaceFile(array $file, File $newFile, string $title, string $upload_dir): ?array
    {
        $uuid = $file['uuid'];
        $version = $file['version'];
        $mimeType = $newFile->getMimeType();
        $file['title'] = $title;

        /**
         * Get Aggregate newest version.
         *
         * @var \RevisionTen\CMS\Model\File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($uuid, \RevisionTen\CMS\Model\File::class);
        $version = $aggregate->getVersion();

        // Todo: Get dimensions.

        $filePath = $this->saveUploadedFile($newFile, $upload_dir, $uuid.'-v'.($version+1));

        // Update file aggregate.
        $commandData = [];
        if ($title !== $aggregate->title) {
            $commandData['title'] = $title;
        }
        if ($filePath !== $aggregate->path) {
            $commandData['path'] = $filePath;
        }

        $success = $this->runCommand(FileUpdateCommand::class, $commandData, $uuid, $version);

        if (!$success) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'path' => $filePath,
            'mimeType' => $mimeType,
            'version' => ($version+1),
        ];
    }

    public function updateFile(array $file, string $title): ?array
    {
        // Update the file.
        $uuid = $file['uuid'];
        $file['title'] = $title;

        /**
         * Get Aggregate newest version.
         *
         * @var \RevisionTen\CMS\Model\File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($uuid, \RevisionTen\CMS\Model\File::class);
        $version = $aggregate->getVersion();

        // Update file aggregate.
        $commandData = [];
        if ($title !== $aggregate->title) {
            $commandData['title'] = $title;
        }

        if (!empty($commandData)) {
            $success = $this->runCommand(FileUpdateCommand::class, $commandData, $uuid, $version);

            if ($success) {
                $file['version'] = ($version+1);
            }
        }

        return $file;
    }

    public function deleteFile(array $file): ?array
    {
        // Delete the file (detaches the file aggregate).
        $uuid = $file['uuid'];

        return null;
    }
}
