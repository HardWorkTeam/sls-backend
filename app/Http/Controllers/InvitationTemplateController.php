<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvitationTemplateResource;
use App\Models\InvitationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('invitation_templates', 'slug')->ignore($template->id),
            ],
            'name' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return InvitationTemplateResource::make($template);
    }
}
