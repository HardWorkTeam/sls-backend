<?php

namespace App\Repositories;

use App\Models\InvitationTemplate;

/**
 * @extends EloquentRepository<InvitationTemplate>
 */
class TemplateRepository extends EloquentRepository
{
    protected string $modelClass = InvitationTemplate::class;
}
