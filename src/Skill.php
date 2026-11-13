<?php

namespace Ernestdefoe\Dossier;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A skill in the catalogue, or one a member typed that is waiting for an
 * admin (approved = false).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $category_id
 * @property bool $approved
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property SkillCategory|null $category
 */
class Skill extends AbstractModel
{
    protected $table = 'dossier_skills';

    public $timestamps = false;

    protected $casts = [
        'category_id' => 'integer',
        'approved' => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public static function slugFor(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /** @return BelongsTo<SkillCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'skill_id');
    }

    /** A member's own skill nobody uses any more is removed; the catalogue's are kept. */
    public static function pruneUnused(?int $id): void
    {
        if ($id) {
            static::query()->where('id', $id)->where('approved', false)->whereDoesntHave('entries')->delete();
        }
    }
}
