<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Command\FileCreateCommand;
use RevisionTen\CMS\Command\FileUpdateCommand;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
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
     * @var UserRead
     */
    private $user;

    /**
     * @var string
     */
    private $project_dir;

    /**
     * PageService constructor.
     *
     * @param AggregateFactory      $aggregateFactory
     * @param CommandBus            $commandBus
     * @param TokenStorageInterface $tokenStorage
     * @param string                $project_dir
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
     * @param bool        $qeue
     * @param string|null $commandUuid
     * @param int|null    $userId
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

    public function saveUploadedFile(UploadedFile $file, string $upload_dir, string $filename): string
    {
        $public_dir = $this->project_dir.'/public';

        // Move the file to the uploads directory.
        $newFileName = $filename.'.'.$file->getClientOriginalExtension();

        $file->move($public_dir.$upload_dir, $newFileName);

        return $upload_dir.$newFileName;
    }

    public function createFile(string $uuid = null, UploadedFile $file, string $title, string $upload_dir): ?array
    {
        if (null === $uuid) {
            $uuid = Uuid::uuid1()->toString();
        }
        $version = 0;
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        $filePath = $this->saveUploadedFile($file, $upload_dir, $uuid.'-v'.($version + 1));

        // Create file aggregate.
        $success = $this->runCommand(FileCreateCommand::class, [
            'title' => $title,
            'path' => $filePath,
            'mimeType' => $mimeType,
            'size' => $size,
        ], $uuid, 0);

        if (!$success) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'path' => $filePath,
            'mimeType' => $mimeType,
            'version' => $version + 1,
            'size' => $size,
        ];
    }

    public function replaceFile(array $file, UploadedFile $newFile, string $title, string $upload_dir): ?array
    {
        $uuid = $file['uuid'];
        $mimeType = $newFile->getMimeType();
        $size = $newFile->getSize();

        /**
         * Get Aggregate newest version.
         *
         * @var \RevisionTen\CMS\Model\File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($uuid, \RevisionTen\CMS\Model\File::class);
        $version = $aggregate->getVersion();

        $filePath = $this->saveUploadedFile($newFile, $upload_dir, $uuid.'-v'.($version + 1));

        // Update file aggregate.
        $commandData = [];
        if ($title !== $aggregate->title) {
            $commandData['title'] = $title;
        }
        if ($filePath !== $aggregate->path) {
            $commandData['path'] = $filePath;
        }
        if ($mimeType !== $aggregate->mimeType) {
            $commandData['mimeType'] = $mimeType;
        }
        if ($size !== $aggregate->size) {
            $commandData['size'] = $size;
        }

        $success = $this->runCommand(FileUpdateCommand::class, $commandData, $uuid, $version);

        if (!$success) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'path' => $filePath,
            'mimeType' => $mimeType,
            'version' => $version + 1,
            'size' => $size,
        ];
    }

    public function updateFile(array $file, string $title): ?array
    {
        // Never update only the title.

        //// Update the file.
        //$uuid = $file['uuid'];
        ///**
        // * Get Aggregate newest version.
        // *
        // * @var \RevisionTen\CMS\Model\File $aggregate
        // */
        //$aggregate = $this->aggregateFactory->build($uuid, \RevisionTen\CMS\Model\File::class);
        //$version = $aggregate->getVersion();
        //// Update file aggregate.
        //$commandData = [];
        //if ($title !== $aggregate->title) {
        //    $commandData['title'] = $title;
        //}
        //if (!empty($commandData)) {
        //    $success = $this->runCommand(FileUpdateCommand::class, $commandData, $uuid, $version);
        //    if ($success) {
        //        $file['version'] = ($version+1);
        //    }
        //}

        return $file;
    }

    public function getFile(string $uuid, int $version): ?array
    {
        /**
         * Get Aggregate newest version.
         *
         * @var \RevisionTen\CMS\Model\File $aggregate
         */
        $aggregate = $this->aggregateFactory->build($uuid, \RevisionTen\CMS\Model\File::class, $version);

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
}
