<?php

namespace Ernestdefoe\Dossier;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item in a section of a dossier: a job or a degree, a skill, a language,
 * a certificate or a link. Its fields live in `data`, checked by Input.
 *
 * @property int $id
 * @property int $user_id
 * @property string $section
 * @property int $position
 * @property int|null $skill_id
 * @property array<string, mixed> $data
 * @property string|null $image
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property Skill|null $skill
 */
class Entry extends AbstractModel
{
    public const SECTIONS = ['timeline', 'skills', 'languages', 'certificates', 'links'];

    /** How many entries one section can hold. */
    public const LIMITS = ['timeline' => 40, 'skills' => 60, 'languages' => 20, 'certificates' => 30, 'links' => 20];

    protected $table = 'dossier_entries';

    public $timestamps = true;

    protected $casts = [
        'user_id' => 'integer',
        'position' => 'integer',
        'skill_id' => 'integer',
        'data' => 'array',
    ];

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
