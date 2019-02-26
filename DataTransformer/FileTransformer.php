<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class FileTransformer implements DataTransformerInterface
{
    public function transform($file): array
    {
        return empty($file) ? [] : [
            'file' => $file,
        ];
    }

    public function reverseTransform($data)
    {
        return $data['file'] ?? null;
    }
}
