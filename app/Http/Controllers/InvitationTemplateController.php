<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreTemplateRequest;
use App\Http\Requests\Catalog\UpdateTemplateRequest;
use App\Http\Resources\InvitationTemplateResource;
use App\Models\InvitationTemplate;
use App\Repositories\TemplateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvitationTemplateController extends Controller
{
    public function __construct(private readonly TemplateRepository $templates) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvitationTemplateResource::collection(
            $this->templates->query()
                ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create($request->validated());

        return InvitationTemplateResource::make($template)->response()->setStatusCode(201);
    }

    public function update(UpdateTemplateRequest $request, InvitationTemplate $template): InvitationTemplateResource
    {
        $this->templates->update($template, $request->validated());

        return InvitationTemplateResource::make($template);
    }

    public function destroy(InvitationTemplate $template): JsonResponse
    {
        $this->templates->delete($template);

        return response()->json(['message' => 'Template deleted.']);
    }
}
