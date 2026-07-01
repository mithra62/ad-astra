<?php

namespace Database\Seeders;

use AdAstra\Field\Types\Boolean;
use AdAstra\Field\Types\Date;
use AdAstra\Field\Types\EmailAddress;
use AdAstra\Field\Types\Number;
use AdAstra\Field\Types\Relationship;
use AdAstra\Field\Types\Text;
use AdAstra\Field\Types\Textarea;
use AdAstra\Field\Types\Url;
use AdAstra\Models\Field;
use AdAstra\Models\Field\Group as FieldGroup;
use AdAstra\Models\Field\Type as FieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    private FieldType $text;
    private FieldType $textarea;
    private FieldType $number;
    private FieldType $date;
    private FieldType $url;
    private FieldType $email;
    private FieldType $boolean;
    private FieldType $relationship;

    public function run(): void
    {
        $this->text = FieldType::where('object', Text::class)->firstOrFail();
        $this->textarea = FieldType::where('object', Textarea::class)->firstOrFail();
        $this->number = FieldType::where('object', Number::class)->firstOrFail();
        $this->date = FieldType::where('object', Date::class)->firstOrFail();
        $this->url = FieldType::where('object', Url::class)->firstOrFail();
        $this->email = FieldType::where('object', EmailAddress::class)->firstOrFail();
        $this->boolean = FieldType::where('object', Boolean::class)->firstOrFail();
        $this->relationship = FieldType::where('object', Relationship::class)->firstOrFail();

        // Base groups
        $this->seedContentFields();
        $this->seedSeoFields();
        $this->seedRelationshipFields();

        // Domain-specific groups
        $this->seedBlogFields();
        $this->seedEventFields();
        $this->seedJobFields();
        $this->seedNewsFields();
        $this->seedPageFields();
        $this->seedPodcastFields();
        $this->seedPortfolioFields();
        $this->seedProductFields();
        $this->seedRecipeFields();
        $this->seedVideoFields();
    }

    // =========================================================================
    // Base groups
    // =========================================================================

    private function seedContentFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'content-fields'],
            ['name' => 'Content Fields', 'description' => 'Core content fields for entries.']
        );

        $fields = [
            [
                'field_type_id' => $this->textarea->id,
                'name' => 'Body',
                'handle' => 'body',
                'label' => 'Body',
                'instructions' => 'The main content of the entry.',
            ],
            [
                'field_type_id' => $this->textarea->id,
                'name' => 'Excerpt',
                'handle' => 'excerpt',
                'label' => 'Excerpt',
                'instructions' => 'A short summary of the entry.',
            ],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedSeoFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'seo-fields'],
            ['name' => 'SEO Fields', 'description' => 'Search engine optimisation metadata.']
        );

        $fields = [
            [
                'field_type_id' => $this->text->id,
                'name' => 'Meta Title',
                'handle' => 'meta_title',
                'label' => 'Meta Title',
                'instructions' => 'Override the page title for search engines (max 60 chars).',
                'settings' => ['maxLength' => 60],
            ],
            [
                'field_type_id' => $this->textarea->id,
                'name' => 'Meta Description',
                'handle' => 'meta_description',
                'label' => 'Meta Description',
                'instructions' => 'Summary for search engine result pages (max 160 chars).',
                'settings' => ['maxLength' => 160],
            ],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedRelationshipFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'relationship-fields'],
            ['name' => 'Relationship Fields', 'description' => 'Fields for linking related entries.']
        );

        $fields = [
            [
                'field_type_id' => $this->relationship->id,
                'name' => 'Related Entries',
                'handle' => 'related_entries',
                'label' => 'Related Entries',
                'instructions' => 'Link related entries from the same section.',
                'settings' => ['limit' => 5],
            ],
        ];

        $this->attachFields($group, $fields);
    }

    // =========================================================================
    // Domain-specific groups
    // =========================================================================

    private function seedBlogFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'blog-fields'],
            ['name' => 'Blog Fields', 'description' => 'System-computed fields for blog posts.']
        );

        $fields = [
            [
                'field_type_id' => $this->number->id,
                'name' => 'Reading Time',
                'handle' => 'reading_time',
                'label' => 'Reading Time (minutes)',
                'instructions' => 'Computed automatically from body word count.',
                'hidden' => true,
            ],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedEventFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'event-fields'],
            ['name' => 'Event Fields', 'description' => 'Scheduling and logistics fields for events.']
        );

        $fields = [
            ['field_type_id' => $this->date->id, 'name' => 'Start Date', 'handle' => 'start_date', 'label' => 'Start Date'],
            ['field_type_id' => $this->date->id, 'name' => 'End Date', 'handle' => 'end_date', 'label' => 'End Date'],
            ['field_type_id' => $this->text->id, 'name' => 'Event Location', 'handle' => 'event_location', 'label' => 'Location'],
            ['field_type_id' => $this->text->id, 'name' => 'Venue', 'handle' => 'venue', 'label' => 'Venue'],
            ['field_type_id' => $this->url->id, 'name' => 'Ticket URL', 'handle' => 'ticket_url', 'label' => 'Ticket URL'],
            ['field_type_id' => $this->date->id, 'name' => 'Registration Deadline', 'handle' => 'registration_deadline', 'label' => 'Registration Deadline'],
            ['field_type_id' => $this->number->id, 'name' => 'Capacity', 'handle' => 'capacity', 'label' => 'Capacity'],
            ['field_type_id' => $this->boolean->id, 'name' => 'Online Event', 'handle' => 'is_online', 'label' => 'Online Event'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedJobFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'job-fields'],
            ['name' => 'Job Fields', 'description' => 'Role details and application fields for job listings.']
        );

        $fields = [
            ['field_type_id' => $this->text->id, 'name' => 'Department', 'handle' => 'department', 'label' => 'Department'],
            ['field_type_id' => $this->text->id, 'name' => 'Job Location', 'handle' => 'job_location', 'label' => 'Location'],
            ['field_type_id' => $this->number->id, 'name' => 'Salary Minimum', 'handle' => 'salary_min', 'label' => 'Salary Min'],
            ['field_type_id' => $this->number->id, 'name' => 'Salary Maximum', 'handle' => 'salary_max', 'label' => 'Salary Max'],
            ['field_type_id' => $this->date->id, 'name' => 'Closing Date', 'handle' => 'closing_date', 'label' => 'Closing Date'],
            ['field_type_id' => $this->url->id, 'name' => 'Application URL', 'handle' => 'application_url', 'label' => 'Application URL'],
            ['field_type_id' => $this->email->id, 'name' => 'Application Email', 'handle' => 'application_email', 'label' => 'Application Email'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedNewsFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'news-fields'],
            ['name' => 'News Fields', 'description' => 'Attribution fields for news articles.']
        );

        $fields = [
            ['field_type_id' => $this->text->id, 'name' => 'Source', 'handle' => 'source', 'label' => 'Source'],
            ['field_type_id' => $this->url->id, 'name' => 'Source URL', 'handle' => 'source_url', 'label' => 'Source URL'],
            ['field_type_id' => $this->date->id, 'name' => 'Dateline', 'handle' => 'dateline', 'label' => 'Dateline'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedPageFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'page-fields'],
            ['name' => 'Page Fields', 'description' => 'Layout and call-to-action options for static pages.']
        );

        $fields = [
            ['field_type_id' => $this->text->id, 'name' => 'Layout', 'handle' => 'layout', 'label' => 'Layout'],
            ['field_type_id' => $this->text->id, 'name' => 'CTA Text', 'handle' => 'cta_text', 'label' => 'CTA Text'],
            ['field_type_id' => $this->url->id, 'name' => 'CTA URL', 'handle' => 'cta_url', 'label' => 'CTA URL'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedPodcastFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'podcast-fields'],
            ['name' => 'Podcast Fields', 'description' => 'Episode metadata for podcast entries.']
        );

        $fields = [
            ['field_type_id' => $this->number->id, 'name' => 'Episode Number', 'handle' => 'episode_number', 'label' => 'Episode Number'],
            ['field_type_id' => $this->number->id, 'name' => 'Season Number', 'handle' => 'season_number', 'label' => 'Season Number'],
            ['field_type_id' => $this->number->id, 'name' => 'Episode Duration', 'handle' => 'episode_duration', 'label' => 'Duration (seconds)'],
            ['field_type_id' => $this->url->id, 'name' => 'Audio URL', 'handle' => 'audio_url', 'label' => 'Audio URL'],
            ['field_type_id' => $this->textarea->id, 'name' => 'Podcast Transcript', 'handle' => 'podcast_transcript', 'label' => 'Transcript'],
            ['field_type_id' => $this->text->id, 'name' => 'Guest Names', 'handle' => 'guest_names', 'label' => 'Guest Names'],
            ['field_type_id' => $this->text->id, 'name' => 'Sponsor', 'handle' => 'sponsor', 'label' => 'Sponsor'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedPortfolioFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'portfolio-fields'],
            ['name' => 'Portfolio Fields', 'description' => 'Project details for portfolio items.']
        );

        $fields = [
            ['field_type_id' => $this->text->id, 'name' => 'Client Name', 'handle' => 'client_name', 'label' => 'Client Name'],
            ['field_type_id' => $this->url->id, 'name' => 'Project URL', 'handle' => 'project_url', 'label' => 'Project URL'],
            ['field_type_id' => $this->date->id, 'name' => 'Project Date', 'handle' => 'project_date', 'label' => 'Project Date'],
            ['field_type_id' => $this->text->id, 'name' => 'Role', 'handle' => 'role', 'label' => 'Role'],
            ['field_type_id' => $this->text->id, 'name' => 'Technologies', 'handle' => 'technologies', 'label' => 'Technologies'],
            ['field_type_id' => $this->textarea->id, 'name' => 'Testimonial', 'handle' => 'testimonial', 'label' => 'Testimonial'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedProductFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'product-fields'],
            ['name' => 'Product Fields', 'description' => 'Pricing and inventory fields for products.']
        );

        $fields = [
            ['field_type_id' => $this->text->id, 'name' => 'SKU', 'handle' => 'sku', 'label' => 'SKU'],
            ['field_type_id' => $this->number->id, 'name' => 'Price', 'handle' => 'price', 'label' => 'Price', 'settings' => ['decimals' => 2]],
            ['field_type_id' => $this->number->id, 'name' => 'Sale Price', 'handle' => 'sale_price', 'label' => 'Sale Price', 'settings' => ['decimals' => 2]],
            ['field_type_id' => $this->number->id, 'name' => 'Stock Quantity', 'handle' => 'stock_quantity', 'label' => 'Stock Quantity'],
            ['field_type_id' => $this->number->id, 'name' => 'Weight', 'handle' => 'weight', 'label' => 'Weight', 'settings' => ['decimals' => 3]],
            ['field_type_id' => $this->text->id, 'name' => 'Dimensions', 'handle' => 'dimensions', 'label' => 'Dimensions'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedRecipeFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'recipe-fields'],
            ['name' => 'Recipe Fields', 'description' => 'Timing and nutritional fields for recipes.']
        );

        $fields = [
            ['field_type_id' => $this->number->id, 'name' => 'Prep Time', 'handle' => 'prep_time', 'label' => 'Prep Time (minutes)'],
            ['field_type_id' => $this->number->id, 'name' => 'Cook Time', 'handle' => 'cook_time', 'label' => 'Cook Time (minutes)'],
            ['field_type_id' => $this->number->id, 'name' => 'Total Time', 'handle' => 'total_time', 'label' => 'Total Time (minutes)', 'hidden' => true],
            ['field_type_id' => $this->number->id, 'name' => 'Servings', 'handle' => 'servings', 'label' => 'Servings'],
            ['field_type_id' => $this->number->id, 'name' => 'Calories', 'handle' => 'calories', 'label' => 'Calories'],
            ['field_type_id' => $this->textarea->id, 'name' => 'Ingredients', 'handle' => 'ingredients', 'label' => 'Ingredients'],
            ['field_type_id' => $this->textarea->id, 'name' => 'Instructions', 'handle' => 'instructions', 'label' => 'Instructions'],
        ];

        $this->attachFields($group, $fields);
    }

    private function seedVideoFields(): void
    {
        $group = FieldGroup::firstOrCreate(
            ['handle' => 'video-fields'],
            ['name' => 'Video Fields', 'description' => 'Platform and metadata fields for video entries.']
        );

        $fields = [
            ['field_type_id' => $this->text->id, 'name' => 'Video Platform', 'handle' => 'video_platform', 'label' => 'Platform'],
            ['field_type_id' => $this->text->id, 'name' => 'Platform ID', 'handle' => 'platform_id', 'label' => 'Platform ID'],
            ['field_type_id' => $this->url->id, 'name' => 'Video URL', 'handle' => 'video_url', 'label' => 'Video URL'],
            ['field_type_id' => $this->number->id, 'name' => 'Video Duration', 'handle' => 'video_duration', 'label' => 'Duration (seconds)'],
            ['field_type_id' => $this->textarea->id, 'name' => 'Video Transcript', 'handle' => 'video_transcript', 'label' => 'Transcript'],
            ['field_type_id' => $this->url->id, 'name' => 'Captions URL', 'handle' => 'captions_url', 'label' => 'Captions URL'],
        ];

        $this->attachFields($group, $fields);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create fields (if they don't exist) and attach them to a FieldGroup.
     *
     * @param array<array<string, mixed>> $fields
     */
    private function attachFields(FieldGroup $group, array $fields): void
    {
        foreach ($fields as $fieldData) {
            $handle = $fieldData['handle'];
            $field = Field::firstOrCreate(['handle' => $handle], $fieldData);
            $group->fields()->syncWithoutDetaching([$field->id]);
        }
    }
}
