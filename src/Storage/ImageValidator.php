<?php

namespace Ernestdefoe\Dossier\Storage;

use Flarum\Foundation\AbstractImageValidator;

/** A certificate picture: JPEG, PNG or WebP, at most 4 MB (core checks dimensions and decodes it too). */
class ImageValidator extends AbstractImageValidator
{
    public const MAX_KB = 4096;

    public function getMaxSize(): int
    {
        return self::MAX_KB;
    }

    /** @return list<string> */
    protected function getAllowedTypes(): array
    {
        return ['jpeg', 'jpg', 'png', 'webp'];
    }
}
