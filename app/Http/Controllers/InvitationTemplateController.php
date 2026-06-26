<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvitationTemplateResource;
use App\Models\InvitationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvitationTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InvitationTemplate::query()->orderBy('name');

        // Super admins see all templates (including inactive);
        // everyone else only sees active ones.
        if (! $request->user()?->hasRole('super_admin')) {
            $query->where('is_active', true);
        }

        return InvitationTemplateResource::collection($query->get());
    }

    public function update(Request $request, InvitationTemplate $template): InvitationTemplateResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return InvitationTemplateResource::make($template);
    }
}
