<?php

namespace Ernestdefoe\Dossier;

use Flarum\Database\AbstractModel;

/**
 * The "About" part of a member's dossier, their open-to chips and the
 * visibility of every section. One row per member, keyed by their id.
 *
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $role
 * @property string|null $bio
 * @property list<string>|null $specializations
 * @property list<string>|null $interests
 * @property list<string>|null $open_to
 * @property array<string, string>|null $visibility
 * @property list<int>|null $pinned_builds
 * @property \Carbon\Carbon|null $updated_at
 */
class Dossier extends AbstractModel
{
    protected $table = 'dossiers';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'integer',
        'specializations' => 'array',
        'interests' => 'array',
        'open_to' => 'array',
        'visibility' => 'array',
        'pinned_builds' => 'array',
        'updated_at' => 'datetime',
    ];

    /** A section's visibility; everyone unless the member chose otherwise. */
    public function visibilityOf(string $section): string
    {
        return Visibility::normalize($this->visibility[$section] ?? null);
    }

    /** @return array<string, string> */
    public function visibilities(): array
    {
        $all = [];

        foreach (Visibility::SECTIONS as $section) {
            $all[$section] = $this->visibilityOf($section);
        }

        return $all;
    }
}
