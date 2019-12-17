<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use function is_string;

class ImageFileTransformer implements DataTransformerInterface
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
