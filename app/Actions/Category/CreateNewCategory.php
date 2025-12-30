<?php
namespace App\Actions\Category;

use App\Models\Category;
use App\Actions\AbstractAction;

class CreateNewCategory extends AbstractAction
{
    public function create(array $input): Category
    {
        $cat = Category::create($input);

        return $cat;
    }
}
