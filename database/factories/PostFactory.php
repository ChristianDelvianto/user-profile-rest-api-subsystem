<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'body' => fake()->paragraphs(rand(1, 2), true),
            'gallery' => $this->populateGallery(),
        ];
    }

    /**
     * Indicate that the model only has 'gallery' column.
     */
    public function onlyHasGallery(): static
    {
        return $this->state(fn (array $attributes) => [
            'body' => null,
            'gallery' => $this->populateGallery(),
        ]);
    }

    /**
     * Indicate that the model is soft deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now()
        ]);
    }

    /**
     * Populate post's 'gallery' column and return as array.
     */
    protected function populateGallery(): array
    {
        return [
            [
                'extension' => 'jpg',
                'type' => 'image',
                'height_dimension' => 400,
                'width_dimension' => 400,
                'file_name' => 'test_image.jpg'
            ]
        ];
    }
}
