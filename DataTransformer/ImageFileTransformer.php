<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class ImageFileTransformer implements DataTransformerInterface
{
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

        return $file;
    }

    public function reverseTransform($data)
    {
        return $data ?? null;
    }
}
