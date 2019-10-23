<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use function is_array;

class FileTransformer implements DataTransformerInterface
{
    public function transform($file): array
    {
        if (is_array($file)) {
            // Convert from format with meta data back to simple string.
            $file = $file['file'] ?? null;
        }

        return empty($file) ? [] : [
            'file' => $file,
        ];
    }

    public function reverseTransform($data)
    {
        return $data['file'] ?? null;
    }
}
