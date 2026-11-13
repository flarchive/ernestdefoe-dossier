<?php

/*
 * This file is part of ernestdefoe/dossier.
 *
 * A member's professional profile: About, a timeline, skills, languages,
 * projects, certificates, links and what they are open to.
 */

use Ernestdefoe\Dossier\Api;
use Ernestdefoe\Dossier\Listener\DeleteDossier;
use Ernestdefoe\Dossier\Visibility;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\User\Event\Deleting;
use Flarum\User\User;

return [
    // The page and its editor are chunks fetched when a dossier is opened;
    // without jsDirectory they 404. No server route: core's /u/{username}[/{filter}]
    // already answers /u/{username}/dossier (and /posts) with the profile preloaded.
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // No forum.css: the page's styles come with its chunk (less/page.less).
        ->jsDirectory(__DIR__.'/js/dist/forum'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Filesystem())
        ->disk('ernestdefoe-dossier', fn (Paths $paths, UrlGenerator $url) => [
            'root' => "$paths->public/assets/dossier",
            'url' => $url->to('forum')->path('assets/dossier'),
            'visibility' => 'public',
        ]),

    // String literals, not Settings:: constants: reading a class here autoloads it at boot,
    // and while a deploy swaps composer's autoload map that fatals every request.
    (new Extend\Settings())
        ->default('ernestdefoe-dossier.default_tab', true)
        ->default('ernestdefoe-dossier.custom_skills', true)
        ->default('ernestdefoe-dossier.open_to', '[{"id":"collaboration"},{"id":"mentoring"},{"id":"freelance"},{"id":"jobs"}]')
        ->default('ernestdefoe-dossier.pinned_builds', 3)
        ->serializeToForum('dossierDefault', 'ernestdefoe-dossier.default_tab', 'boolval'),

    (new Extend\Routes('api'))
        ->get('/dossiers/{id:\d+}', 'dossier.show', Api\ShowDossier::class)
        ->patch('/dossiers/{id:\d+}', 'dossier.update', Api\UpdateDossier::class)
        ->post('/dossiers/{id:\d+}/entries', 'dossier.entries.create', Api\SaveEntry::class)
        ->patch('/dossiers/{id:\d+}/entries/{entry:\d+}', 'dossier.entries.update', Api\SaveEntry::class)
        ->delete('/dossiers/{id:\d+}/entries/{entry:\d+}', 'dossier.entries.delete', Api\DeleteEntry::class)
        ->post('/dossiers/{id:\d+}/entries/{entry:\d+}/image', 'dossier.entries.image', Api\CertificateImage::class)
        ->delete('/dossiers/{id:\d+}/entries/{entry:\d+}/image', 'dossier.entries.image.delete', Api\CertificateImage::class)
        ->post('/dossiers/{id:\d+}/order', 'dossier.order', Api\OrderEntries::class)
        ->get('/dossier-catalogue', 'dossier.catalogue', Api\Catalogue::class)
        ->post('/dossier-catalogue/categories', 'dossier.categories.create', Api\Admin\SaveCategory::class)
        ->post('/dossier-catalogue/categories/order', 'dossier.categories.order', Api\Admin\SaveCategory::class)
        ->patch('/dossier-catalogue/categories/{id:\d+}', 'dossier.categories.update', Api\Admin\SaveCategory::class)
        ->delete('/dossier-catalogue/categories/{id:\d+}', 'dossier.categories.delete', Api\Admin\SaveCategory::class)
        ->post('/dossier-catalogue/skills', 'dossier.skills.create', Api\Admin\SaveSkill::class)
        ->patch('/dossier-catalogue/skills/{id:\d+}', 'dossier.skills.update', Api\Admin\SaveSkill::class)
        ->delete('/dossier-catalogue/skills/{id:\d+}', 'dossier.skills.delete', Api\Admin\SaveSkill::class),

    // Three booleans for the profile tab and editor; the actor's permissions are already loaded.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewDossiers')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('ernestdefoe-dossier.view')),
            Schema\Boolean::make('canHaveDossier')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('ernestdefoe-dossier.have')),
            Schema\Boolean::make('canEditAnyDossier')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('ernestdefoe-dossier.editAny')),
        ]),

    // Whether this viewer would find anything on the member's dossier: read
    // from a column, no query. Only on a profile (GET /api/users/{id}), never
    // in the user lists that discussions and posts carry.
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('hasDossier')
                ->visible(fn (User $user, Context $context) => $context->showing(UserResource::class))
                ->get(function (User $user, Context $context) {
                    $actor = $context->getActor();
                    $reach = (int) $user->getAttribute('dossier_reach');

                    return $reach > 0 && Visibility::canView($actor, $user) && $reach >= Visibility::rankFor($actor, $user);
                }),
        ]),

    (new Extend\Event())
        ->listen(Deleting::class, DeleteDossier::class),
];
