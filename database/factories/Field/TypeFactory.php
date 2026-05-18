<?php

namespace Database\Factories\Field;

use App\Field\Types\Text;
use App\Models\Field\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Type>
 */
class TypeFactory extends Factory
{
    protected $model = Type::class;

    public function definition(): array
    {
        return [
            'name' => 'Text',
            'object' => Text::class,
            'settings' => [],
        ];
    }
}
