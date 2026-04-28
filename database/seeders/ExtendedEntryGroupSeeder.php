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
use App\Models\Category\Group as CategoryGroup;
use App\Models\EntryGroup;
use App\Models\EntryType;
use App\Models\Field\Group as FieldGroup;
use App\Models\StatusGroup;
use Database\Seeders\Concerns\BuildsLayouts;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExtendedEntryGroupSeeder extends Seeder
{
    use BuildsLayouts, WithoutModelEvents;

    public function run(): void
    {
        $publication  = StatusGroup::where('handle', 'publication')->firstOrFail();
        $jobStatus    = StatusGroup::where('handle', 'job-status')->firstOrFail();
        $contentFields = FieldGroup::where('handle', 'content-fields')->firstOrFail();
        $seoFields     = FieldGroup::where('handle', 'seo-fields')->firstOrFail();

        $this->seedEventsGroup($publication, $contentFields, $seoFields);
        $this->seedNewsGroup($publication, $contentFields, $seoFields);
        $this->seedPagesGroup($publication, $contentFields, $seoFields);
        $this->seedJobsGroup($jobStatus, $contentFields, $seoFields);
        $this->seedPodcastGroup($publication, $contentFields, $seoFields);
        $this->seedPortfolioGroup($publication, $contentFields, $seoFields);
        $this->seedVideosGroup($publication, $contentFields, $seoFields);
        $this->seedRecipesGroup($publication, $contentFields, $seoFields);
    }

    private function seedEventsGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $eventFields = FieldGroup::where('handle', 'event-fields')->firstOrFail();
        $eventTypes  = CategoryGroup::where('handle', 'event-types')->firstOrFail();

        $layout = $this->createLayout('Events Layout', [
            'Details' => ['body', 'excerpt'],
            'SEO'     => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'events'],
            [
                'name'            => 'Events',
                'description'     => 'Upcoming and past events, conferences, and webinars.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 3,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $eventFields->id]);
        $group->categoryGroups()->syncWithoutDetaching([$eventTypes->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Event Details', [
            'start_date', 'end_date', 'event_location', 'venue',
            'is_online', 'ticket_url', 'registration_deadline', 'capacity',
        ], 10);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'event'],
            ['name' => 'Event', 'class' => EventEntryType::class, 'sort_order' => 1]
        );
    }

    private function seedNewsGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $newsFields = FieldGroup::where('handle', 'news-fields')->firstOrFail();

        $layout = $this->createLayout('News Layout', [
            'Content' => ['body', 'excerpt'],
            'SEO'     => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'news'],
            [
                'name'            => 'News',
                'description'     => 'News articles and press releases.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 4,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $newsFields->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Attribution', [
            'source', 'source_url', 'dateline',
        ], 10);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'news_article'],
            ['name' => 'News Article', 'class' => NewsArticleEntryType::class, 'sort_order' => 1]
        );
    }

    private function seedPagesGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $pageFields = FieldGroup::where('handle', 'page-fields')->firstOrFail();

        $layout = $this->createLayout('Pages Layout', [
            'Content' => ['body'],
            'SEO'     => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'pages'],
            [
                'name'            => 'Pages',
                'description'     => 'Static content pages (About, Contact, Privacy Policy, etc.).',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 5,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $pageFields->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Page Options', [
            'layout', 'cta_text', 'cta_url',
        ], 10);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'page'],
            [
                'name'             => 'Page',
                'class'            => PageEntryType::class,
                'sort_order'       => 1,
                'default_template' => 'entries.page',
                'has_entry_tree'   => true,
            ]
        );
    }

    private function seedJobsGroup(StatusGroup $jobStatus, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $jobFields        = FieldGroup::where('handle', 'job-fields')->firstOrFail();
        $employmentTypes  = CategoryGroup::where('handle', 'employment-types')->firstOrFail();
        $experienceLevels = CategoryGroup::where('handle', 'experience-levels')->firstOrFail();

        $layout = $this->createLayout('Jobs Layout', [
            'Listing' => ['body', 'excerpt'],
            'SEO'     => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'jobs'],
            [
                'name'            => 'Jobs',
                'description'     => 'Job listings and career opportunities.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $jobStatus->id,
                'sort_order'      => 6,
            ]
        );

        // Ensure the job-status group is used (backfills existing rows).
        $group->update(['status_group_id' => $jobStatus->id]);

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $jobFields->id]);
        $group->categoryGroups()->syncWithoutDetaching([$employmentTypes->id, $experienceLevels->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Role Details', [
            'department', 'job_location', 'salary_min', 'salary_max',
            'closing_date', 'application_url', 'application_email',
        ], 10);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'job_listing'],
            ['name' => 'Job Listing', 'class' => JobListingEntryType::class, 'sort_order' => 1]
        );
    }

    private function seedPodcastGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $podcastFields = FieldGroup::where('handle', 'podcast-fields')->firstOrFail();

        $layout = $this->createLayout('Podcast Layout', [
            'Episode' => ['body', 'excerpt'],
            'SEO'     => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'podcast'],
            [
                'name'            => 'Podcast',
                'description'     => 'Podcast episodes and show notes.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 7,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $podcastFields->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Episode Details', [
            'episode_number', 'season_number', 'audio_url',
            'episode_duration', 'guest_names', 'sponsor',
        ], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Transcript', ['podcast_transcript'], 11);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'podcast_episode'],
            ['name' => 'Podcast Episode', 'class' => PodcastEpisodeEntryType::class, 'sort_order' => 1]
        );
    }

    private function seedPortfolioGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $portfolioFields = FieldGroup::where('handle', 'portfolio-fields')->firstOrFail();

        $layout = $this->createLayout('Portfolio Layout', [
            'Case Study' => ['body', 'excerpt'],
            'SEO'        => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'portfolio'],
            [
                'name'            => 'Portfolio',
                'description'     => 'Portfolio items, case studies, and work samples.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 8,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $portfolioFields->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Project Details', [
            'client_name', 'project_date', 'role',
            'technologies', 'project_url', 'testimonial',
        ], 10);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'portfolio_item'],
            [
                'name'             => 'Portfolio Item',
                'class'            => PortfolioItemEntryType::class,
                'sort_order'       => 1,
                'default_template' => 'entries.page',
                'has_entry_tree'   => true,
            ]
        );
    }

    private function seedVideosGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $videoFields = FieldGroup::where('handle', 'video-fields')->firstOrFail();

        $layout = $this->createLayout('Videos Layout', [
            'Video' => ['body', 'excerpt'],
            'SEO'   => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'videos'],
            [
                'name'            => 'Videos',
                'description'     => 'Video content, tutorials, and recordings.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 9,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $videoFields->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Video', [
            'video_platform', 'platform_id', 'video_url', 'video_duration', 'captions_url',
        ], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Transcript', ['video_transcript'], 11);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'video'],
            [
                'name'             => 'Video',
                'class'            => VideoEntryType::class,
                'sort_order'       => 1,
                'default_template' => 'entries.page',
                'has_entry_tree'   => true,
            ]
        );
    }

    private function seedRecipesGroup(StatusGroup $publication, FieldGroup $contentFields, FieldGroup $seoFields): void
    {
        $recipeFields = FieldGroup::where('handle', 'recipe-fields')->firstOrFail();
        $cuisines     = CategoryGroup::where('handle', 'cuisines')->firstOrFail();
        $dietTypes    = CategoryGroup::where('handle', 'diet-types')->firstOrFail();

        $layout = $this->createLayout('Recipes Layout', [
            'Recipe' => ['body', 'excerpt'],
            'SEO'    => ['meta_title', 'meta_description'],
        ]);

        $group = EntryGroup::firstOrCreate(
            ['handle' => 'recipes'],
            [
                'name'            => 'Recipes',
                'description'     => 'Food recipes with ingredients and instructions.',
                'field_layout_id' => $layout->id,
                'status_group_id' => $publication->id,
                'sort_order'      => 10,
            ]
        );

        $group->fieldGroups()->syncWithoutDetaching([$contentFields->id, $seoFields->id, $recipeFields->id]);
        $group->categoryGroups()->syncWithoutDetaching([$cuisines->id, $dietTypes->id]);

        $this->addTabIfMissing($group->field_layout_id, 'Recipe Details', [
            'prep_time', 'cook_time', 'total_time', 'servings', 'calories',
        ], 10);
        $this->addTabIfMissing($group->field_layout_id, 'Content', [
            'ingredients', 'instructions',
        ], 11);

        EntryType::firstOrCreate(
            ['entry_group_id' => $group->id, 'handle' => 'recipe'],
            [
                'name'             => 'Recipe',
                'class'            => RecipeEntryType::class,
                'sort_order'       => 1,
                'default_template' => 'entries.page',
                'has_entry_tree'   => true,
            ]
        );
    }

}
