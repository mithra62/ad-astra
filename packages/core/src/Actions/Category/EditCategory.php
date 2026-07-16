<?php

namespace AdAstra\Actions\Category;

use AdAstra\Actions\AbstractAction;
use AdAstra\Models\Category;
use AdAstra\Repositories\CategoryRepository;

class EditCategory extends AbstractAction
{
    public function edit(Category $category, array $input): Category
    {
        return app(CategoryRepository::class)->applyData($category, $input);
    }
}
