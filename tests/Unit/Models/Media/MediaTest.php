<?php

namespace Tests\Unit\Models\Media;

use App\Models\Category;
use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Tests\TestCase;

class MediaTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_media_library_relationship_is_belongs_to(): void
    {
        $media = new Media;

        $this->assertInstanceOf(BelongsTo::class, $media->media_library());
    }

    public function test_media_library_is_related_to_library_model(): void
    {
        $media = new Media;
        $relation = $media->media_library();

        $this->assertInstanceOf(Library::class, $relation->getRelated());
    }


    public function test_categories_relationship_is_morph_to_many(): void
    {
        $media = new Media;

        $this->assertInstanceOf(MorphToMany::class, $media->categories());
    }

    public function test_categories_is_related_to_category_model(): void
    {
        $media = new Media;
        $relation = $media->categories();

        $this->assertInstanceOf(Category::class, $relation->getRelated());
    }

    public function test_categories_uses_categorizable_morph_name(): void
    {
        $media = new Media;
        $relation = $media->categories();

        $this->assertEquals('categorizable_type', $relation->getMorphType());
    }
}
