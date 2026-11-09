<?php

namespace Nfgarching\Files\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Models\File;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = $this->faker->randomElement(['jpg', 'JPG', 'png', 'PNG', 'pdf', 'PDF']);
        $filename = Str::random(8).'.'.$extension;

        return [
            'uuid' => $this->faker->uuid(),
            'model_type' => 'App\Models\User',
            'model_id' => 1,
            'collection_name' => 'default',
            'state' => 'active',
            'type' => FileType::fromExtension($extension),
            'disk' => $this->faker->randomElement(['local', 'public', 's3']),
            'file' => $filename,
            'hash' => md5($filename),
            'mime' => 'application/pdf',
            'name' => $this->faker->words(3, true).'.'.$extension,
            'size' => $this->faker->numberBetween(1024, 10485760),
            'order_column' => 1,
        ];
    }

    /**
     * File with PDF type
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pdf',
            'mime' => 'application/pdf',
            'file' => Str::random(8).'.pdf',
            'name' => $this->faker->words(3, true).'.pdf',
        ]);
    }

    /**
     * File with JPG type
     */
    public function jpg(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'jpg',
            'mime' => 'image/jpeg',
            'file' => Str::random(8).'.jpg',
            'name' => $this->faker->words(3, true).'.jpg',
        ]);
    }

    /**
     * File with PNG type
     */
    public function png(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'png',
            'mime' => 'image/png',
            'file' => Str::random(8).'.png',
            'name' => $this->faker->words(3, true).'.png',
        ]);
    }

    /**
     * File with uncomplete status
     */
    public function uncomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'uncomplete',
        ]);
    }

    /**
     * File with inactive status
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
