<?php

namespace App\Repositories;

use App\Models\Package;

/**
 * @extends EloquentRepository<Package>
 */
class PackageRepository extends EloquentRepository
{
    protected string $modelClass = Package::class;
}
