<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvitationTemplateResource;
use App\Models\InvitationTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvitationTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $templates = InvitationTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return InvitationTemplateResource::collection($templates);
    }
}
