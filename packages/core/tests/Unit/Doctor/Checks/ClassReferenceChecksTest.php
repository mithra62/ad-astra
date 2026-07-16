<?php

namespace Tests\Unit\Doctor\Checks;

use AdAstra\Doctor\Checks\EntrySystem\BehaviorClassReferencesCheck;
use AdAstra\Doctor\Checks\FieldSystem\FieldTypeClassReferencesCheck;
use AdAstra\Doctor\DoctorStatus;
use AdAstra\Models\EntryBehavior;
use AdAstra\Models\Field\Type as FieldType;
use Database\Seeders\EntryBehaviorSeeder;
use Database\Seeders\FieldTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassReferenceChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_behaviors_pass(): void
    {
        $this->seed(EntryBehaviorSeeder::class);

        $results = iterator_to_array((new BehaviorClassReferencesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_unmapped_behavior_morph_key_fails(): void
    {
        EntryBehavior::create([
            'name' => 'Missing',
            'handle' => 'missing-' . uniqid(),
            'class' => 'behavior.nonexistent-' . uniqid(),
        ]);

        $results = iterator_to_array((new BehaviorClassReferencesCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('not registered in the morphMap', $results[0]->message);
    }

    public function test_seeded_field_types_pass(): void
    {
        $this->seed(FieldTypeSeeder::class);

        $results = iterator_to_array((new FieldTypeClassReferencesCheck())->run(), false);

        $this->assertCount(1, $results);
        $this->assertSame(DoctorStatus::Pass, $results[0]->status);
    }

    public function test_field_type_with_missing_class_fails(): void
    {
        FieldType::create([
            'name' => 'Broken',
            'object' => 'AdAstra\\Field\\Types\\DoesNotExist' . uniqid(),
        ]);

        $results = iterator_to_array((new FieldTypeClassReferencesCheck())->run(), false);

        $this->assertSame(DoctorStatus::Fail, $results[0]->status);
        $this->assertStringContainsString('does not exist', $results[0]->message);
    }
}
