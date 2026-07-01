<?php

namespace Database\Seeders;

use AdAstra\Models\Category\Group as CategoryGroup;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\EntryType;
use AdAstra\Models\Field;
use AdAstra\Models\FieldLayout;
use AdAstra\Models\FieldLayout\Tab;
use AdAstra\Models\FieldLayout\TabElement;
use AdAstra\Models\StatusGroup;
use Database\Seeders\Concerns\BuildsLayouts;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExtendedEntryGroupSeeder extends Seeder
{
    use BuildsLayouts, WithoutModelEvents;

    public function run(): void
    {
        $publication = StatusGroup::where('handle', 'publication')->firstOrFail();
        $jobStatus = StatusGroup::where('handle', 'job-status')->firstOrFail();

        $this->seedEventsGroup($publication);
        $this->seedNewsGroup($publication);
        $this->seedPagesGroup($publication);
        $this->seedJobsGroup($jobStatus);
        $this->seedPodcastGroup($publication);
        $this->seedPortfolioGroup($publication);
        $this->seedVideosGroup($publication);
        $this->seedRecipesGroup($publication);
        $this->seedGeneralGroup($publication);
    }

    private function seedEventsGroup(StatusGroup $publication): void
    {
        $eventTypes = CategoryGroup::where('handle', 'event-types')->firstOrFail();

        $layout = $this->createLayout('Events Layout', [
            'Details' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'events'],
            [
                'name' => 'Events',
                'description' => 'Upcoming and past events, conferences, and webinars.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 3,
            ]
        );

        $group->categoryGroups()->syncWithoutDetaching([$eventTypes->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Event Details', [
            'start_date', 'end_date', 'event_location', 'venue',
            'is_online', 'ticket_url', 'registration_deadline', 'capacity',
        ], 10);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'event'],
            ['name' => 'Event', 'entry_behavior_id' => EntryBehavior::where('handle', 'event')->value('id'), 'sort_order' => 1]
        );
    }

    private function seedNewsGroup(StatusGroup $publication): void
    {
        $layout = $this->createLayout('News Layout', [
            'Content' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'news'],
            [
                'name' => 'News',
                'description' => 'News articles and press releases.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 4,
            ]
        );

        $this->addTabIfMissing($group->field_layout_id, 'Attribution', [
            'source', 'source_url', 'dateline',
        ], 10);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'news_article'],
            ['name' => 'News Article', 'entry_behavior_id' => EntryBehavior::where('handle', 'news-article')->value('id'), 'sort_order' => 1]
        );
    }

    private function seedPagesGroup(StatusGroup $publication): void
    {
        $layout = $this->createLayout('Pages Layout', [
            'Content' => ['body'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'pages'],
            [
                'name' => 'Pages',
                'description' => 'Static content pages (About, Contact, Privacy Policy, etc.).',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 5,
            ]
        );

        $this->addTabIfMissing($group->field_layout_id, 'Page Options', [
            'layout', 'cta_text', 'cta_url',
        ], 10);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'page'],
            [
                'name' => 'Page',
                'entry_behavior_id' => EntryBehavior::where('handle', 'page')->value('id'),
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedJobsGroup(StatusGroup $jobStatus): void
    {
        $employmentTypes = CategoryGroup::where('handle', 'employment-types')->firstOrFail();
        $experienceLevels = CategoryGroup::where('handle', 'experience-levels')->firstOrFail();

        $layout = $this->createLayout('Jobs Layout', [
            'Listing' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'jobs'],
            [
                'name' => 'Jobs',
                'description' => 'Job listings and career opportunities.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $jobStatus->id,
                'sort_order' => 6,
            ]
        );

        $group->categoryGroups()->syncWithoutDetaching([$employmentTypes->id, $experienceLevels->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Role Details', [
            'department', 'job_location', 'salary_min', 'salary_max',
            'closing_date', 'application_url', 'application_email',
        ], 10);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'job_listing'],
            ['name' => 'Job Listing', 'entry_behavior_id' => EntryBehavior::where('handle', 'job-listing')->value('id'), 'sort_order' => 1]
        );
    }

    private function seedPodcastGroup(StatusGroup $publication): void
    {
        $layout = $this->createLayout('Podcast Layout', [
            'Episode' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'podcast'],
            [
                'name' => 'Podcast',
                'description' => 'Podcast episodes and show notes.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 7,
            ]
        );

        $this->addTabIfMissing($group->field_layout_id, 'Episode Details', [
            'episode_number', 'season_number', 'audio_url',
            'episode_duration', 'guest_names', 'sponsor',
        ], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Transcript', ['podcast_transcript'], 11);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'podcast_episode'],
            ['name' => 'Podcast Episode', 'entry_behavior_id' => EntryBehavior::where('handle', 'podcast-episode')->value('id'), 'sort_order' => 1]
        );
    }

    private function seedPortfolioGroup(StatusGroup $publication): void
    {
        $layout = $this->createLayout('Portfolio Layout', [
            'Case Study' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'portfolio'],
            [
                'name' => 'Portfolio',
                'description' => 'Portfolio items, case studies, and work samples.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 8,
            ]
        );

        $this->addTabIfMissing($group->field_layout_id, 'Project Details', [
            'client_name', 'project_date', 'role',
            'technologies', 'project_url', 'testimonial',
        ], 10);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'portfolio_item'],
            [
                'name' => 'Portfolio Item',
                'entry_behavior_id' => EntryBehavior::where('handle', 'portfolio-item')->value('id'),
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedVideosGroup(StatusGroup $publication): void
    {
        $layout = $this->createLayout('Videos Layout', [
            'Video' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'videos'],
            [
                'name' => 'Videos',
                'description' => 'Video content, tutorials, and recordings.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 9,
            ]
        );

        $this->addTabIfMissing($group->field_layout_id, 'Video', [
            'video_platform', 'platform_id', 'video_url', 'video_duration', 'captions_url',
        ], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Transcript', ['video_transcript'], 11);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'video'],
            [
                'name' => 'Video',
                'entry_behavior_id' => EntryBehavior::where('handle', 'video')->value('id'),
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedRecipesGroup(StatusGroup $publication): void
    {
        $cuisines = CategoryGroup::where('handle', 'cuisines')->firstOrFail();
        $dietTypes = CategoryGroup::where('handle', 'diet-types')->firstOrFail();

        $layout = $this->createLayout('Recipes Layout', [
            'Recipe' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'recipes'],
            [
                'name' => 'Recipes',
                'description' => 'Food recipes with ingredients and instructions.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 10,
            ]
        );

        $group->categoryGroups()->syncWithoutDetaching([$cuisines->id, $dietTypes->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Recipe Details', [
            'prep_time', 'cook_time', 'total_time', 'servings', 'calories',
        ], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Content', [
            'ingredients', 'instructions',
        ], 11);

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'recipe'],
            [
                'name' => 'Recipe',
                'entry_behavior_id' => EntryBehavior::where('handle', 'recipe')->value('id'),
                'sort_order' => 1,
                'default_template' => 'entries.page',
                'has_entry_tree' => true,
            ]
        );
    }

    private function seedGeneralGroup(StatusGroup $publication): void
    {
        $layout = $this->createLayout('General Layout', [
            'Content' => ['body', 'excerpt'],
            'SEO' => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::updateOrCreate(
            ['handle' => 'general'],
            [
                'name' => 'General',
                'description' => 'General-purpose content that does not belong to a dedicated section.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order' => 11,
            ]
        );

        EntryType::updateOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'general'],
            ['name' => 'General', 'entry_behavior_id' => EntryBehavior::where('handle', 'general')->value('id'), 'sort_order' => 1]
        );
    }

    /**
     * Local idempotent override for BuildsLayouts::createLayout().
     *
     * @param array<string, string[]> $tabs
     */
    private function createLayout(string $name, array $tabs): FieldLayout
    {
        $layout = FieldLayout::query()
            ->where('handle', Str::slug($name))
            ->orderBy('id')
            ->first();

        if (!$layout instanceof FieldLayout) {
            $layout = FieldLayout::create([
                'name' => $name,
                'handle' => Str::slug($name),
            ]);
        } elseif ($layout->name !== $name) {
            $layout->update(['name' => $name]);
        }

        $tabOrder = 1;
        foreach ($tabs as $tabName => $fieldHandles) {
            $this->addTabIfMissing($layout->id, $tabName, $fieldHandles, $tabOrder++);
        }

        return $layout;
    }

    /**
     * Local idempotent override for BuildsLayouts::addTabIfMissing().
     *
     * @param string[] $fieldHandles
     */
    private function addTabIfMissing(int $layoutId, string $tabName, array $fieldHandles, int $sortOrder): void
    {
        $tab = Tab::query()->updateOrCreate(
            [
                'field_layout_id' => $layoutId,
                'handle' => Str::slug($tabName),
            ],
            [
                'name' => $tabName,
                'sort_order' => $sortOrder,
            ]
        );

        $fields = Field::query()
            ->whereIn('handle', $fieldHandles)
            ->get()
            ->keyBy('handle');

        $order = 1;
        foreach ($fieldHandles as $handle) {
            $field = $fields->get($handle);

            if (!$field instanceof Field) {
                continue;
            }

            TabElement::query()->updateOrCreate(
                [
                    'field_layout_tab_id' => $tab->id,
                    'field_id' => $field->id,
                ],
                [
                    'required' => false,
                    'sort_order' => $order++,
                ]
            );
        }
    }
}
