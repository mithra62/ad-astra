<?php

namespace Database\Factories;

use App\Models\UserSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSchema>
 */
class UserSchemaFactory extends Factory
{
    protected $model = UserSchema::class;

    public function definition(): array
    {
        return [
            'field_layout_id' => null,
        ];
    }
}
