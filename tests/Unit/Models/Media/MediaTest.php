<?php

namespace Tests\Unit\Models\Media;

use App\Models\Category;
use App\Models\Media;
use App\Models\Media\Library;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Tests\TestCase;

class MediaTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_library_relationship_is_belongs_to(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Media)->library());
    }

    public function test_library_is_related_to_library_model(): void
    {
        $this->assertInstanceOf(Library::class, (new Media)->library()->getRelated());
    }

    public function test_transformations_relationship_is_has_many(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Media)->transformations());
    }

    public function test_categories_relationship_is_morph_to_many(): void
    {
        $this->assertInstanceOf(MorphToMany::class, (new Media)->categories());
    }

    public function test_categories_is_related_to_category_model(): void
    {
        $this->assertInstanceOf(Category::class, (new Media)->categories()->getRelated());
    }

    public function test_categories_uses_categorizable_morph_name(): void
    {
        $this->assertEquals('categorizable_type', (new Media)->categories()->getMorphType());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_is_image_returns_true_for_image_mime(): void
    {
        $media = new Media(['mime_type' => 'image/jpeg']);
        $this->assertTrue($media->isImage());
    }

    public function test_is_image_returns_false_for_non_image_mime(): void
    {
        $media = new Media(['mime_type' => 'application/pdf']);
        $this->assertFalse($media->isImage());
    }

    public function test_is_image_returns_false_when_mime_is_null(): void
    {
        $media = new Media(['mime_type' => null]);
        $this->assertFalse($media->isImage());
    }
}
