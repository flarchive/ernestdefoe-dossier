<?php

namespace Ernestdefoe\Dossier\Api;

use Ernestdefoe\Dossier\Entry;
use Ernestdefoe\Dossier\Presenter;
use Ernestdefoe\Dossier\Storage\ImageValidator;
use Ernestdefoe\Dossier\Storage\Images;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Post\Exception\FloodingException;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * POST /api/dossiers/{id}/entries/{entry}/image (multipart "image") sets a
 * certificate's picture; DELETE removes it. The upload is checked, decoded and
 * re-encoded to WebP under a random name (Storage\Images); the file that was
 * sent is never kept.
 */
class CertificateImage extends Controller
{
    public function __construct(
        Presenter $presenter,
        protected Images $images,
        protected ImageValidator $validator,
        protected Cache $cache,
        protected TranslatorInterface $translator,
    ) {
        parent::__construct($presenter);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$actor, $owner] = $this->editable($request);

        /** @var Entry $entry */
        $entry = Entry::query()->where('user_id', $owner->id)->where('section', 'certificates')->findOrFail($this->param($request, 'entry'));
        $previous = $entry->image;

        if ($request->getMethod() === 'DELETE') {
            $entry->image = null;
        } else {
            $file = Arr::get($request->getUploadedFiles(), 'image');

            if (! $file instanceof UploadedFileInterface) {
                throw new ValidationException(['image' => $this->translator->trans('validation.required', ['attribute' => 'image'])]);
            }

            $this->throttle($actor);
            $this->validator->assertImageValid('image', $file);
            $entry->image = $this->images->store((string) $file->getStream()->getMetadata('uri'));
        }

        $entry->save();
        $this->images->delete($previous);

        return $this->respond($owner, $actor);
    }

    /** Each picture is decoded and encoded twice: ten a minute and fifty a day per member. */
    private function throttle(User $actor): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        foreach (['minute' => [10, 60, date('YmdHi')], 'day' => [50, 2 * 86400, date('Ymd')]] as $span => [$limit, $ttl, $stamp]) {
            $key = "dossier.images.$actor->id.$span.$stamp";
            $this->cache->add($key, 0, $ttl);

            if ($this->cache->increment($key) > $limit) {
                throw new FloodingException();
            }
        }
    }
}
