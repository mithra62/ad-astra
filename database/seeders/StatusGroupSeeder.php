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
        $publication = StatusGroup::firstOrCreate(
            ['handle' => 'publication'],
            ['name' => 'Publication Status', 'sort_order' => 1]
        );

        $statuses = [
            ['name' => 'Draft',     'handle' => 'draft',     'color' => '#9CA3AF', 'is_default' => true,  'sort_order' => 1],
            ['name' => 'Published', 'handle' => 'published', 'color' => '#10B981', 'is_default' => false, 'sort_order' => 2],
            ['name' => 'Archived',  'handle' => 'archived',  'color' => '#EF4444', 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['status_group_id' => $publication->id, 'handle' => $status['handle']],
                array_merge($status, ['status_group_id' => $publication->id])
            );
        }
    }
}
