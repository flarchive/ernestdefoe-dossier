<?php

namespace Ernestdefoe\Dossier\Projects;

use Ernestdefoe\Dossier\PublishedProjects;
use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while Projects is enabled (Extend\Conditional in extend.php). */
class ProjectsServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(PublishedProjects::class, ProjectsList::class);
    }
}
