<?php

namespace Database\Factories;

use App\Enums\RsvpStatus;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RsvpResponse>
 */
class RsvpResponseFactory extends Factory
{
    protected $model = RsvpResponse::class;

    public function definition(): array
    {
        return [
            'wedding_id' => Wedding::factory(),
            'invitation_id' => Invitation::factory(),
            'guest_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'number_of_guests' => fake()->numberBetween(1, 4),
            'message' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(RsvpStatus::submittable()),
            'responded_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
