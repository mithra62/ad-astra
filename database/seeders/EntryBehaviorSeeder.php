<?php

namespace Database\Seeders;

use App\Models\EntryBehavior;
use Illuminate\Database\Seeder;

class EntryBehaviorSeeder extends Seeder
{
    public function run(): void
    {
        $behaviors = [
            [
                'name'        => 'General',
                'handle'      => 'general',
                'class'       => 'behavior.general',
                'description' => 'A general-purpose entry type with no special behavior.',
            ],
            [
                'name'        => 'Blog Post',
                'handle'      => 'blog-post',
                'class'       => 'behavior.blog-post',
                'description' => 'Blog post entries. Automatically computes reading time from word count.',
            ],
            [
                'name'        => 'Product',
                'handle'      => 'product',
                'class'       => 'behavior.product',
                'description' => 'Product entries. Normalizes pricing, enforces SKU on publish, and manages stock status.',
            ],
            [
                'name'        => 'Page',
                'handle'      => 'page',
                'class'       => 'behavior.page',
                'description' => 'Static page entries.',
            ],
            [
                'name'        => 'Event',
                'handle'      => 'event',
                'class'       => 'behavior.event',
                'description' => 'Event entries. Validates that end date is not before start date.',
            ],
            [
                'name'        => 'Job Listing',
                'handle'      => 'job-listing',
                'class'       => 'behavior.job-listing',
                'description' => 'Job listing entries.',
            ],
            [
                'name'        => 'News Article',
                'handle'      => 'news-article',
                'class'       => 'behavior.news-article',
                'description' => 'News article entries.',
            ],
            [
                'name'        => 'Podcast Episode',
                'handle'      => 'podcast-episode',
                'class'       => 'behavior.podcast-episode',
                'description' => 'Podcast episode entries.',
            ],
            [
                'name'        => 'Portfolio Item',
                'handle'      => 'portfolio-item',
                'class'       => 'behavior.portfolio-item',
                'description' => 'Portfolio item entries.',
            ],
            [
                'name'        => 'Recipe',
                'handle'      => 'recipe',
                'class'       => 'behavior.recipe',
                'description' => 'Recipe entries.',
            ],
            [
                'name'        => 'Video',
                'handle'      => 'video',
                'class'       => 'behavior.video',
                'description' => 'Video entries.',
            ],
        ];

        foreach ($behaviors as $behavior) {
            EntryBehavior::firstOrCreate(
                ['handle' => $behavior['handle']],
                $behavior
            );
        }
    }
}
