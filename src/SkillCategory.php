<?php

namespace Ernestdefoe\Dossier;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $name
 * @property int $position
 */
class SkillCategory extends AbstractModel
{
    protected $table = 'dossier_skill_categories';

    public $timestamps = false;

    protected $casts = ['position' => 'integer'];
}
