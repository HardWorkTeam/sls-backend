<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Wedding;

/**
 * Resolves what a wedding is actually allowed to do, derived from the
 * marketing feature strings on the package it has *paid* for. This is the
 * single source of truth that turns a selected package into enforced
 * capabilities (gated modules + guest / invitation-design limits).
 *
 * Features unlock only once the subscription is PAID; before that (or with
 * no package) the wedding gets the conservative {@see base()} allowance.
 */
class PlanCapabilities
{
    /** Guests allowed for a wedding without a paid plan. */
    private const BASE_GUEST_LIMIT = 25;

    /** Invitation designs allowed for a wedding without a paid plan. */
    private const BASE_DESIGN_LIMIT = 1;

    public function __construct(
        public readonly bool $seating,
        public readonly bool $gallery,
        public readonly bool $gifts,
        public readonly ?int $guestLimit,            // null = unlimited
        public readonly ?int $invitationDesignLimit, // null = unlimited
    ) {}

    /**
     * Allowance for a wedding that has not paid for any package yet.
     */
    public static function base(): self
    {
        return new self(false, false, false, self::BASE_GUEST_LIMIT, self::BASE_DESIGN_LIMIT);
    }

    /**
     * Capabilities for a wedding, gated on its PAID subscription only.
     */
    public static function forWedding(Wedding $wedding): self
    {
        $paid = $wedding->subscriptions()
            ->where('status', SubscriptionStatus::Paid->value)
            ->with('package')
            ->latest('id')
            ->first();

        return self::forPaidPackage($paid?->package, $paid !== null);
    }

    /**
     * Build from the package the wedding has paid for. A non-paid or absent
     * package yields the base allowance.
     */
    public static function forPaidPackage(?Package $package, bool $isPaid): self
    {
        return $isPaid && $package
            ? self::fromPackage($package)
            : self::base();
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
            guestLimit: self::parseLimit($text, 'guest', self::BASE_GUEST_LIMIT),
            invitationDesignLimit: self::parseLimit($text, 'design', self::BASE_DESIGN_LIMIT),
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
     * Extract a numeric limit for $noun from the feature text:
     * "unlimited ... <noun>" → null (no cap); "<N> ... <noun>" → N; if the
     * noun is not mentioned at all, fall back to the base allowance.
     */
    private static function parseLimit(string $text, string $noun, int $fallback): ?int
    {
        if (preg_match('/unlimited[^|]*'.$noun.'/', $text) === 1) {
            return null;
        }

        if (preg_match('/(\d[\d,]*)[^|]*'.$noun.'/', $text, $matches) === 1) {
            return (int) str_replace(',', '', $matches[1]);
        }

        return $fallback;
    }
}
