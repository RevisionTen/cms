<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use function is_string;

class FileWithMetaDataTransformer implements DataTransformerInterface
{
    /**
     * @param mixed $file
     *
     * @return array
     */
    public function transform($file): array
    {
        if (empty($file)) {
            return [];
        }

        if (is_string($file)) {
            return [
                'file' => $file,
            ];
        }

        // Transform managed upload legacy data.
        if (!empty($file['file']['path'])) {
            return [
                'file' => $file['file']['path'] ?? null,
                'uuid' => $file['file']['uuid'] ?? null,
                'version' => $file['file']['version'] ?? null,
                'mimeType' => $file['file']['mimeType'] ?? null,
                'size' => $file['file']['size'] ?? null,
            ];
        }

        return $file;
    }

    /**
     * @param mixed $data
     *
     * @return mixed|null
     */
    public function reverseTransform($data)
    {
        return $data ?? null;
    }
}
