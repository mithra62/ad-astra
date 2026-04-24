<?php

namespace App\Actions\Category;

use App\Actions\AbstractAction;
use App\Models\Category;
use App\Repositories\CategoryRepository;

class EditCategory extends AbstractAction
{
    public function edit(Category $category, array $input): Category
    {
        return app(CategoryRepository::class)->applyData($category, $input);
    }
}
