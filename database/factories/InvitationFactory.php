<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'invitation_code' => strtoupper(Str::random(8)),
            'title' => 'You are invited!',
            'status' => InvitationStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
