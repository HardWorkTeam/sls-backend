<?php

namespace App\Support;

use App\Models\Package;
use App\Models\Wedding;

/**
 * Resolves what a wedding is actually allowed to do, derived from the
 * package it has *selected*. This is the single source of truth that turns a
 * selected package into enforced capabilities (gated modules + guest /
 * invitation-design limits).
 *
 * Capabilities follow the selected package immediately — payment is billing,
 * not a feature gate. The platform is not free: a wedding with no package
 * selected gets the locked {@see base()} allowance (nothing unlocked), so the
 * couple must choose a package to start.
 */
class PlanCapabilities
{
    public function __construct(
        public readonly bool $seating,
        public readonly bool $gallery,
        public readonly bool $gifts,
        public readonly ?int $guestLimit,            // null = unlimited
        public readonly ?int $invitationDesignLimit, // null = unlimited
    ) {}

    /**
     * Allowance for a wedding that has not selected any package yet. The
     * platform is not free — without a package nothing is unlocked (no gated
     * modules, zero guests, zero designs), so the couple must choose a plan
     * to start.
     */
    public static function base(): self
    {
        return new self(false, false, false, 0, 0);
    }

    /**
     * Capabilities for a wedding, following its SELECTED package
     * (`weddings.package_id`). No package selected → base allowance.
     */
    public static function forWedding(Wedding $wedding): self
    {
        $package = $wedding->relationLoaded('package')
            ? $wedding->package
            : $wedding->loadMissing('package')->package;

        return $package ? self::fromPackage($package) : self::base();
    }

    /**
     * Resolve a package's capabilities. The admin-editable structured
     * `capabilities` definition is authoritative; if a package has none yet
     * (e.g. created before the column existed) we derive them from its
     * feature strings so nothing breaks.
     */
    public static function fromPackage(Package $package): self
    {
        $stored = $package->capabilities;

        return is_array($stored) && $stored !== []
            ? self::fromArray($stored)
            : self::forFeatures($package->features ?? []);
    }

    /**
     * Build from a stored capabilities array (the shape produced by
     * {@see toArray()}), tolerating partial/legacy payloads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];

        return new self(
            seating: (bool) ($modules['seating'] ?? false),
            gallery: (bool) ($modules['gallery'] ?? false),
            gifts: (bool) ($modules['gifts'] ?? false),
            guestLimit: self::normalizeLimit($data['guest_limit'] ?? null),
            invitationDesignLimit: self::normalizeLimit($data['invitation_design_limit'] ?? null),
        );
    }

    /**
     * Parse a package's marketing feature list into capabilities. The seeded
     * features literally name the modules and limits ("Seating planner",
     * "Photo gallery", "Up to 150 guests", "Unlimited designs", "All
     * modules"), so the admin-editable feature strings stay the source of
     * truth for what each plan unlocks.
     *
     * @param  array<int, string>  $features
     */
    public static function forFeatures(array $features): self
    {
        // Join with a separator we can anchor on so a "limit" phrase in one
        // feature can't bleed into the next.
        $text = strtolower(implode(' | ', array_map('strval', $features)));
        $allModules = str_contains($text, 'all module');

        return new self(
            seating: $allModules || str_contains($text, 'seating'),
            gallery: $allModules || str_contains($text, 'gallery'),
            gifts: $allModules || str_contains($text, 'gift'),
            guestLimit: self::parseLimit($text, 'guest'),
            invitationDesignLimit: self::parseLimit($text, 'design'),
        );
    }

    /**
     * Whether a gated module ('seating' | 'gallery' | 'gifts') is unlocked.
     * Unknown modules are always allowed (base modules are never gated).
     */
    public function allows(string $module): bool
    {
        return match ($module) {
            'seating' => $this->seating,
            'gallery' => $this->gallery,
            'gifts' => $this->gifts,
            default => true,
        };
    }

    /**
     * @return array{modules: array{seating: bool, gallery: bool, gifts: bool}, guest_limit: int|null, invitation_design_limit: int|null}
     */
    public function toArray(): array
    {
        return [
            'modules' => [
                'seating' => $this->seating,
                'gallery' => $this->gallery,
                'gifts' => $this->gifts,
            ],
            'guest_limit' => $this->guestLimit,
            'invitation_design_limit' => $this->invitationDesignLimit,
        ];
    }

    /**
     * Coerce a stored limit into either a positive int cap or null
     * (unlimited). Empty string / null / 0 / negative all mean unlimited.
     */
    private static function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * Extract a numeric limit for $noun from the feature text (legacy
     * fallback for packages with no structured capabilities):
     * "unlimited ... <noun>" → null (no cap); "<N> ... <noun>" → N; if the
     * noun is not mentioned at all, treat as uncapped (the package was paid
     * for, so don't accidentally block it).
     */
    private static function parseLimit(string $text, string $noun): ?int
    {
        if (preg_match('/unlimited[^|]*'.$noun.'/', $text) === 1) {
            return null;
        }

        if (preg_match('/(\d[\d,]*)[^|]*'.$noun.'/', $text, $matches) === 1) {
            return (int) str_replace(',', '', $matches[1]);
        }

        return null;
    }
}
