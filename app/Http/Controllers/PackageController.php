<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StorePackageRequest;
use App\Http\Requests\Catalog\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Repositories\PackageRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    public function __construct(private readonly PackageRepository $packages) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PackageResource::collection(
            $this->packages->query()
                ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
                ->orderBy('price')
                ->get(),
        );
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packages->create($request->validated());

        return PackageResource::make($package)->response()->setStatusCode(201);
    }

    public function update(UpdatePackageRequest $request, Package $package): PackageResource
    {
        $this->packages->update($package, $request->validated());

        return PackageResource::make($package);
    }

    public function destroy(Package $package): JsonResponse
    {
        $this->packages->delete($package);

        return response()->json(['message' => 'Package deleted.']);
    }
}
