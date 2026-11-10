<?php

namespace KriticalIt\ProductPacks\Database\Factories;

use KriticalIt\ProductPacks\Enums\PackStatus;
use KriticalIt\ProductPacks\Models\Pack;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackFactory extends Factory
{
    protected $model = Pack::class;

    public function definition(): array
    {
        return [
            'title' => ['en' => $this->faker->words(3, true)],
            'status' => PackStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PackStatus::Draft]);
    }
}
