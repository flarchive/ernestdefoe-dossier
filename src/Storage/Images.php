<?php

namespace Ernestdefoe\Dossier\Storage;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Certificate pictures on Dossier's own disk (public/assets/dossier unless a
 * storage driver says otherwise). Every upload is decoded and re-encoded as
 * WebP, full size and small, under a random name: the original file is never
 * stored or served, and whatever metadata it carried (a phone photo's
 * location included) is gone. A stored value is the full image's path; the
 * small one is derived from it.
 */
class Images
{
    public const DISK = 'ernestdefoe-dossier';

    private const FULL = 1600;

    private const SMALL = 400;

    private const MAX_HEIGHT = 4096;

    protected Cloud $disk;

    public function __construct(Factory $filesystems, protected ImageManager $images)
    {
        $this->disk = $filesystems->disk(self::DISK);
    }

    /** Store an image from a local path; returns the stored path. */
    public function store(string $source): string
    {
        $image = $this->images->read($source);
        $name = 'certificates/'.Str::random(32).'.webp';
        $written = [];

        try {
            foreach ([$name => self::FULL, self::small($name) => self::SMALL] as $target => $width) {
                $copy = clone $image;
                $copy->scaleDown(width: $width, height: self::MAX_HEIGHT);
                $encoded = (string) $copy->toWebp(quality: 82);

                if ($encoded === '') {
                    throw new RuntimeException('The image could not be encoded.');
                }

                $this->disk->put($target, $encoded);
                $written[] = $target;
            }
        } catch (Throwable $e) {
            $this->disk->delete($written);

            throw $e;
        }

        return $name;
    }

    public function delete(?string $stored): void
    {
        if ($stored && self::valid($stored)) {
            $this->disk->delete([$stored, self::small($stored)]);
        }
    }

    public function url(?string $stored, bool $small = false): ?string
    {
        return $stored && self::valid($stored) ? $this->disk->url($small ? self::small($stored) : $stored) : null;
    }

    public static function small(string $name): string
    {
        return substr($name, 0, -5).'-sm.webp';
    }

    /** Only names this class made: never a path someone could steer elsewhere. */
    private static function valid(string $stored): bool
    {
        return (bool) preg_match('#^certificates/[A-Za-z0-9]{32}\.webp$#', $stored);
    }
}
