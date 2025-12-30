<?php
namespace App\Actions\Category;

use App\Models\Category;
use App\Actions\AbstractAction;

class EditCategory extends AbstractAction
{
    public function edit(Category $category, array $input): bool
    {
        return $category->update($input);
    }
}
