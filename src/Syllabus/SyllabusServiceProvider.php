<?php

namespace Ernestdefoe\Dossier\Syllabus;

use Ernestdefoe\Dossier\Earned;
use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while Syllabus is enabled (Extend\Conditional in extend.php). */
class SyllabusServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(Earned::class, SyllabusEarned::class);
    }
}
