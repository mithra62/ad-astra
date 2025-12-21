<?php
namespace App\Actions\Category;

use App\Models\Category;

class EditCategory
{
    public function edit(Category $category, array $input): bool
    {
        return $category->update($input);
    }
}
