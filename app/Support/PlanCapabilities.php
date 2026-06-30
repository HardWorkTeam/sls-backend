<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Wedding;

/**
 * Resolves what a wedding is actually allowed to do, derived from the package
 * it has *paid* for. This is the single source of truth that turns a
 * confirmed plan into enforced capabilities (gated modules + guest /
 * invitation-design limits).
 *
 * Features unlock only once the subscription is PAID (admin-confirmed).
 * Selecting a package is not enough — pending, submitted and rejected all
 * stay on the locked {@see base()} allowance. The platform is not free, so a
 * wedding without a paid plan gets nothing unlocked.
 */
class PlanCapabilities
{
    public function __construct(
        public readonly bool $seating,
        public readonly bool $gallery,
        public readonly bool $gifts,
        public readonly bool $expense,
        public readonly bool $rsvp,
        public readonly bool $timeline,
        public readonly bool $checkin,
        public readonly ?int $guestLimit,            // null = unlimited
        public readonly ?int $invitationDesignLimit, // null = unlimited
    ) {}

    /**
     * Allowance for a wedding without a PAID plan (none selected, or selected
     * but pending / submitted / rejected). The platform is not free, so
     * nothing is unlocked: no gated modules, zero guests, zero invitation
     * designs. The couple can still preview the invitation templates (a
     * public catalog) before paying.
     */
    public static function base(): self
    {
        return new self(false, false, false, false, false, false, false, 0, 0);
    }

    /**
     * Capabilities for a wedding, gated on its PAID subscription only. A
     * package that is merely selected (pending / submitted / rejected) does
     * NOT unlock anything → base allowance.
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
            expense: (bool) ($modules['expense'] ?? false),
            rsvp: (bool) ($modules['rsvp'] ?? false),
            timeline: (bool) ($modules['timeline'] ?? false),
            checkin: (bool) ($modules['checkin'] ?? false),
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
            // Guest list, RSVP, expense, timeline and wedding-day check-in were
            // always-on basics for any paid plan before these became toggleable,
            // so a legacy package (one with no structured capabilities) keeps
            // them unlocked.
            expense: true,
            rsvp: true,
            timeline: true,
            checkin: true,
            guestLimit: self::parseLimit($text, 'guest'),
            invitationDesignLimit: self::parseLimit($text, 'design'),
        );
    }

    /**
     * Whether a gated module ('seating' | 'gallery' | 'gifts' | 'expense' |
     * 'rsvp' | 'timeline') is unlocked. Unknown modules are always allowed
     * (ungated features are never blocked).
     */
    public function allows(string $module): bool
    {
        return match ($module) {
            'seating' => $this->seating,
            'gallery' => $this->gallery,
            'gifts' => $this->gifts,
            'expense' => $this->expense,
            'rsvp' => $this->rsvp,
            'timeline' => $this->timeline,
            'checkin' => $this->checkin,
            default => true,
        };
    }

    /**
     * @return array{modules: array{seating: bool, gallery: bool, gifts: bool, expense: bool, rsvp: bool, timeline: bool, checkin: bool}, guest_limit: int|null, invitation_design_limit: int|null}
     */
    public function toArray(): array
    {
        return [
            'modules' => [
                'seating' => $this->seating,
                'gallery' => $this->gallery,
                'gifts' => $this->gifts,
                'expense' => $this->expense,
                'rsvp' => $this->rsvp,
                'timeline' => $this->timeline,
                'checkin' => $this->checkin,
            ],
            'guest_limit' => $this->guestLimit,
            'invitation_design_limit' => $this->invitationDesignLimit,
        ];
    }

    /**
     * Coerce a stored limit into either an int cap (>= 0) or null (unlimited).
     * Only null / empty string mean unlimited; an explicit 0 is a real cap of
     * zero (the feature is included in the plan with no allowance — e.g. the
     * Free plan grants 0 invitation designs). Negatives clamp to 0.
     */
    private static function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
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
