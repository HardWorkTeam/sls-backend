<?php

namespace Database\Factories;

use App\Enums\WeddingStatus;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wedding>
 */
class WeddingFactory extends Factory
{
    protected $model = Wedding::class;

    public function definition(): array
    {
        $bride = fake()->firstNameFemale();
        $groom = fake()->firstNameMale();

        return [
            'wedding_code' => 'WED-'.strtoupper(Str::random(6)),
            'wedding_name' => "{$bride} & {$groom}",
            'bride_name' => $bride.' '.fake()->lastName(),
            'groom_name' => $groom.' '.fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'wedding_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'wedding_time' => fake()->time('H:i'),
            'ceremony_venue' => fake()->company().' Hall',
            'reception_venue' => fake()->company().' Ballroom',
            'story_description' => fake()->paragraph(),
            'status' => WeddingStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => WeddingStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
