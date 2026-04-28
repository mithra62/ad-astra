<?php

namespace Database\Seeders;

use App\EntryTypes\EventEntryType;
use App\EntryTypes\JobListingEntryType;
use App\EntryTypes\NewsArticleEntryType;
use App\EntryTypes\PageEntryType;
use App\EntryTypes\PodcastEpisodeEntryType;
use App\EntryTypes\PortfolioItemEntryType;
use App\EntryTypes\RecipeEntryType;
use App\EntryTypes\VideoEntryType;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field;
use App\Models\Field\Group as FieldGroup;
use App\Models\FieldLayout;
use App\Models\FieldLayout\Tab;
use App\Models\FieldLayout\TabElement;
use App\Models\StatusGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExtendedEntryGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $publication = StatusGroup::where('handle', 'publication')->firstOrFail();
        $contentFields = FieldGroup::where('handle', 'content-fields')->firstOrFail();
        $seoFields = FieldGroup::where('handle', 'seo-fields')->firstOrFail();

        $this->seedEventsGroup($publication, $contentFields, $seoFields);
        $this->seedNewsGroup($publication, $contentFields, $seoFields);
        $this->seedPagesGroup($publication, $contentFields, $seoFields);
        $this->seedJobsGroup($publication, $contentFields, $seoFields);
        $this->seedPodcastGroup($publication, $contentFields, $seoFields);
        $this->seedPortfolioGroup($publication, $contentFields, $seoFields);
        $this->seedVideosGroup($publication, $contentFields, $seoFields);
        $this->seedRecipesGroup($publication, $contentFields, $seoFields);
    }

    private function seedEventsGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Events Layout', [
            'Details' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'events'],
            [
                'name' => 'Events',
                'description' => 'Upcoming and past events, conferences, and webinars.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 3,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'event'],
            [
                'name' => 'Event',
                'class' => EventEntryType::class,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * Create a FieldLayout with named tabs, each containing field handles.
     * Fields that don't exist in the database are silently skipped.
     *
     * @param array<string, string[]> $tabs Tab name => [field handles]
     */
    private function createLayout(string $name, array $tabs): FieldLayout
    {
        $layout = FieldLayout::create(['name' => $name]);
        $tabOrder = 1;

        foreach ($tabs as $tabName => $fieldHandles) {
            $tab = Tab::create([
                'field_layout_id' => $layout->id,
                'name' => $tabName,
                'sort_order' => $tabOrder++,
            ]);

            $elementOrder = 1;
            foreach ($fieldHandles as $handle) {
                $field = Field::where('handle', $handle)->first();
                if (!$field) {
                    continue;
                }

                TabElement::create([
                    'field_layout_tab_id' => $tab->id,
                    'field_id' => $field->id,
                    'required' => false,
                    'sort_order' => $elementOrder++,
                ]);
            }
        }

        return $layout;
    }

    private function seedNewsGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('News Layout', [
            'Content' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'news'],
            [
                'name' => 'News',
                'description' => 'News articles and press releases.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 4,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'news_article'],
            [
                'name' => 'News Article',
                'class' => NewsArticleEntryType::class,
                'sort_order' => 1,
            ]
        );
    }

    private function seedPagesGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Pages Layout', [
            'Content' => ['body'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'pages'],
            [
                'name' => 'Pages',
                'description' => 'Static content pages (About, Contact, Privacy Policy, etc.).',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 5,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'page'],
            [
                'name' => 'Page',
                'class' => PageEntryType::class,
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedJobsGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Jobs Layout', [
            'Listing' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'jobs'],
            [
                'name' => 'Jobs',
                'description' => 'Job listings and career opportunities.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 6,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'job_listing'],
            [
                'name' => 'Job Listing',
                'class' => JobListingEntryType::class,
                'sort_order' => 1,
            ]
        );
    }

    private function seedPodcastGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Podcast Layout', [
            'Episode' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'podcast'],
            [
                'name' => 'Podcast',
                'description' => 'Podcast episodes and show notes.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 7,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'podcast_episode'],
            [
                'name' => 'Podcast Episode',
                'class' => PodcastEpisodeEntryType::class,
                'sort_order' => 1,
            ]
        );
    }

    private function seedPortfolioGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Portfolio Layout', [
            'Case Study' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'portfolio'],
            [
                'name' => 'Portfolio',
                'description' => 'Portfolio items, case studies, and work samples.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 8,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'portfolio_item'],
            [
                'name' => 'Portfolio Item',
                'class' => PortfolioItemEntryType::class,
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedVideosGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Videos Layout', [
            'Video' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'videos'],
            [
                'name' => 'Videos',
                'description' => 'Video content, tutorials, and recordings.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 9,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'video'],
            [
                'name' => 'Video',
                'class' => VideoEntryType::class,
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedRecipesGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $layout = $this->createLayout('Recipes Layout', [
            'Recipe' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'recipes'],
            [
                'name' => 'Recipes',
                'description' => 'Food recipes with ingredients and instructions.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 10,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id]);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'recipe'],
            [
                'name' => 'Recipe',
                'class' => RecipeEntryType::class,
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }
}
