<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'ernestdefoe-dossier.view' => Group::GUEST_ID,
    'ernestdefoe-dossier.have' => Group::MEMBER_ID,
    'ernestdefoe-dossier.editAny' => Group::MODERATOR_ID,
]);
