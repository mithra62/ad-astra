<?php
namespace App\Actions\Category;

use App\Models\Category;

class CreateNewCategory
{
    public function create(array $input): Category
    {
        $cat = Category::create($input);

        return $cat;
    }
}
