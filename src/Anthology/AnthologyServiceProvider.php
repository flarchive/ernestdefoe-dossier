<?php

namespace Ernestdefoe\Dossier\Anthology;

use Ernestdefoe\Dossier\WrittenSeries;
use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while Anthology is enabled (Extend\Conditional in extend.php). */
class AnthologyServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(WrittenSeries::class, AnthologySeries::class);
    }
}
