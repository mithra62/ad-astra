<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Models\StatusGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatusGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedPublicationGroup();
        $this->seedJobStatusGroup();
        $this->seedProductStatusGroup();
    }

    // -------------------------------------------------------------------------

    private function seedPublicationGroup(): void
    {
        $group = StatusGroup::firstOrCreate(
            ['handle' => 'publication'],
            ['name' => 'Publication Status', 'sort_order' => 1]
        );

        $this->seedStatuses($group, [
            ['name' => 'Draft', 'handle' => 'draft', 'color' => '#9CA3AF', 'is_default' => true, 'is_public' => false, 'sort_order' => 1],
            ['name' => 'Published', 'handle' => 'published', 'color' => '#10B981', 'is_default' => false, 'is_public' => true, 'sort_order' => 2],
            ['name' => 'Archived', 'handle' => 'archived', 'color' => '#EF4444', 'is_default' => false, 'is_public' => false, 'sort_order' => 3],
        ]);
    }

    private function seedStatuses(StatusGroup $group, array $statuses): void
    {
        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['status_group_id' => $group->id, 'handle' => $status['handle']],
                array_merge($status, ['status_group_id' => $group->id])
            );
        }
    }

    private function seedJobStatusGroup(): void
    {
        $group = StatusGroup::firstOrCreate(
            ['handle' => 'job-status'],
            ['name' => 'Job Listing Status', 'sort_order' => 2]
        );

        $this->seedStatuses($group, [
            ['name' => 'Draft', 'handle' => 'draft', 'color' => '#9CA3AF', 'is_default' => true, 'is_public' => false, 'sort_order' => 1],
            ['name' => 'Published', 'handle' => 'published', 'color' => '#10B981', 'is_default' => false, 'is_public' => true, 'sort_order' => 2],
            ['name' => 'Expired', 'handle' => 'expired', 'color' => '#F59E0B', 'is_default' => false, 'is_public' => false, 'sort_order' => 3],
            ['name' => 'Closed', 'handle' => 'closed', 'color' => '#EF4444', 'is_default' => false, 'is_public' => false, 'sort_order' => 4],
        ]);
    }

    // -------------------------------------------------------------------------

    private function seedProductStatusGroup(): void
    {
        $group = StatusGroup::firstOrCreate(
            ['handle' => 'product-status'],
            ['name' => 'Product Status', 'sort_order' => 3]
        );

        $this->seedStatuses($group, [
            ['name' => 'Draft', 'handle' => 'draft', 'color' => '#9CA3AF', 'is_default' => true, 'is_public' => false, 'sort_order' => 1],
            ['name' => 'Published', 'handle' => 'published', 'color' => '#10B981', 'is_default' => false, 'is_public' => true, 'sort_order' => 2],
            ['name' => 'Out of Stock', 'handle' => 'out-of-stock', 'color' => '#F59E0B', 'is_default' => false, 'is_public' => false, 'sort_order' => 3],
            ['name' => 'Pre-Order', 'handle' => 'pre-order', 'color' => '#6366F1', 'is_default' => false, 'is_public' => true, 'sort_order' => 4],
            ['name' => 'Discontinued', 'handle' => 'discontinued', 'color' => '#EF4444', 'is_default' => false, 'is_public' => false, 'sort_order' => 5],
        ]);
    }
}
