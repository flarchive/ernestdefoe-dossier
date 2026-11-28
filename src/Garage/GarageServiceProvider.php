<?php

namespace Ernestdefoe\Dossier\Garage;

use Ernestdefoe\Dossier\FinishedBuilds;
use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while Garage is enabled (Extend\Conditional in extend.php). */
class GarageServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(FinishedBuilds::class, GarageBuilds::class);
    }
}
