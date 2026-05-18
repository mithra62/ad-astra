<?php

namespace Database\Seeders;

use App\Facades\Content;
use App\Models\Category;
use App\Models\Entry;
use App\Models\EntryAuthor;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EntrySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Look up the canonical seed user by email rather than position, so the
        // seeder stays correct regardless of insertion order.
        $author = User::where('email', config('app.default_dev_email'))->firstOrFail();

        // Guard: UsersSeeder must have run first and promoted this user to author.
        // If this fails, check the DatabaseSeeder run order.
        EntryAuthor::where('user_id', $author->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Auth::setUser() works in CLI context without requiring a session-backed guard.
        Auth::setUser($author);

        $posts = $this->seedBlogPosts($author);
        $this->linkRelatedPosts($posts);

        $this->seedProducts($author);
    }

    /**
     * @return array<string, Entry>  keyed by a short name for cross-linking
     */
    private function seedBlogPosts(User $author): array
    {
        $technology = Category::where('handle', 'technology')->firstOrFail();
        $design = Category::where('handle', 'design')->firstOrFail();
        $business = Category::where('handle', 'business')->firstOrFail();

        $definitions = [
            'laravel' => [
                'title' => 'Getting Started with Laravel',
                'published_at' => now()->subDays(14),
                'authors' => [$author->id],
                'categories' => [$technology->id],
                'status' => 'published',
                'fields' => [
                    'body' => 'Laravel is a web application framework with expressive, elegant syntax. It provides structure and a starting point for building applications, allowing you to focus on creating something amazing while the framework handles the boilerplate.',
                    'excerpt' => 'A practical introduction to the Laravel PHP framework and why developers love it.',
                    'meta_title' => 'Getting Started with Laravel | Blog',
                    'meta_description' => 'Learn how to get started with Laravel, the PHP framework for web artisans.',
                ],
            ],
            'design_systems' => [
                'title' => 'Design Systems in Practice',
                'published_at' => now()->subDays(7),
                'authors' => [$author->id],
                'categories' => [$design->id],
                'status' => 'published',
                'fields' => [
                    'body' => 'A design system is a collection of reusable components, guided by clear standards, that can be assembled to build any number of applications. It keeps your product consistent, speeds up development, and gives designers and developers a shared language.',
                    'excerpt' => 'How design systems reduce inconsistency and accelerate product development.',
                    'meta_title' => 'Design Systems in Practice | Blog',
                    'meta_description' => 'Explore how design systems help teams build better products faster and more consistently.',
                ],
            ],
            'roadmap' => [
                'title' => 'Building a Product Roadmap',
                'published_at' => null,
                'authors' => [$author->id],
                'categories' => [$business->id],
                'status' => 'draft',
                'fields' => [
                    'body' => 'A product roadmap is a high-level visual summary that maps out the vision and direction of your product offering over time. It communicates the why and what behind what you are building.',
                    'excerpt' => 'Step-by-step guide to creating a product roadmap your whole team can rally behind.',
                    'meta_title' => 'Building a Product Roadmap | Blog',
                    'meta_description' => 'Learn how to build a clear, actionable product roadmap that aligns your team.',
                ],
            ],
            'remote_work' => [
                'title' => 'The Future of Remote Work',
                'published_at' => now()->subDays(3),
                'authors' => [$author->id],
                'categories' => [$business->id],
                'status' => 'published',
                'fields' => [
                    'body' => 'Remote work has shifted from a perk to an expectation for many knowledge workers. Companies that adapt their culture, tooling, and communication patterns will attract the best talent regardless of geography.',
                    'excerpt' => 'How remote work is reshaping company culture and what it means for the future.',
                    'meta_title' => 'The Future of Remote Work | Blog',
                    'meta_description' => 'Explore how distributed teams are changing the way we think about work and culture.',
                ],
            ],
        ];

        $created = [];
        foreach ($definitions as $key => $data) {
            $data['handle'] ??= $key;
            $created[$key] = Content::create('blog_post', $data);
        }

        return $created;
    }

    /**
     * Back-fill the related_entries relationship field once all posts exist.
     *
     * @param array<string, Entry> $posts
     */
    private function linkRelatedPosts(array $posts): void
    {
        $relationships = [
            // Technology post relates to design systems (both are craft-focused)
            'laravel' => ['design_systems'],
            // Design systems relates to both tech and product roadmap
            'design_systems' => ['laravel', 'roadmap'],
            // Roadmap relates to remote work (both are business/strategy)
            'roadmap' => ['remote_work'],
            // Remote work relates to roadmap
            'remote_work' => ['roadmap'],
        ];

        foreach ($relationships as $key => $relatedKeys) {
            $relatedIds = array_map(
                fn(string $k) => $posts[$k]->id,
                $relatedKeys
            );

            Content::update($posts[$key], [
                'fields' => ['related_entries' => $relatedIds],
            ]);
        }
    }

    private function seedProducts(User $author): void
    {
        $electronics = Category::where('handle', 'electronics')->firstOrFail();
        $clothing = Category::where('handle', 'clothing')->firstOrFail();
        $books = Category::where('handle', 'books')->firstOrFail();

        $products = [
            [
                'title' => 'Wireless Noise-Cancelling Headphones',
                'published_at' => now()->subDays(10),
                'authors' => [$author->id],
                'categories' => [$electronics->id],
                'status' => 'published',
                'fields' => [
                    'sku'              => 'ELEC-WH-001',
                    'body'             => 'Premium wireless headphones with active noise cancellation, 30-hour battery life, and a foldable design for easy portability. Compatible with all Bluetooth 5.0 devices.',
                    'excerpt'          => 'Immersive sound with up to 30 hours of battery life and best-in-class noise cancellation.',
                    'meta_title'       => 'Wireless Noise-Cancelling Headphones',
                    'meta_description' => 'Shop our premium wireless headphones featuring active noise cancellation and 30-hour battery.',
                ],
            ],
            [
                'title' => 'Classic Merino Wool Sweater',
                'published_at' => now()->subDays(5),
                'authors' => [$author->id],
                'categories' => [$clothing->id],
                'status' => 'published',
                'fields' => [
                    'sku'              => 'CLTH-MW-001',
                    'body'             => '100% merino wool sweater with a classic crew neck silhouette. Naturally temperature-regulating, soft against skin, and machine washable. Available in 8 colours.',
                    'excerpt'          => 'Timeless crew neck in 100% merino wool — soft, warm, and machine washable.',
                    'meta_title'       => 'Classic Merino Wool Sweater',
                    'meta_description' => 'Shop our classic merino wool crew neck sweater, available in 8 colours.',
                ],
            ],
            [
                'title' => 'The Pragmatic Programmer',
                'published_at' => null,
                'authors' => [$author->id],
                'categories' => [$books->id],
                'status' => 'draft',
                'fields' => [
                    'body' => 'A landmark book in software engineering, The Pragmatic Programmer examines the core process of software development: what it means to write good code in a changing world.',
                    'excerpt' => 'A must-read for developers who want to sharpen their craft and become more effective.',
                    'meta_title' => 'The Pragmatic Programmer — Book',
                    'meta_description' => 'The Pragmatic Programmer by Andrew Hunt and David Thomas — a classic software engineering book.',
                ],
            ],
        ];

        foreach ($products as $product) {
            $product['handle'] ??= Str::slug($product['title']);
            Content::create('product', $product);
        }
    }
}
