<?php

namespace Tests\Feature;

use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Models\Admin;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationApprovalActionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = Admin::create(['name' => 'Test Admin', 'email' => 'test-admin@example.com', 'password' => 'password']);
        $this->actingAs($admin, 'admin');
    }

    public function test_approve_action_on_edit_page_activates_a_pending_organization(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::create([
            'name' => 'Pending Bank', 'slug' => 'pending-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => false,
        ]);

        Livewire::test(EditOrganization::class, ['record' => $organization->getKey()])
            ->assertActionHasLabel('toggleApproval', 'Approve')
            ->callAction('toggleApproval');

        $this->assertTrue($organization->fresh()->is_active);
    }

    public function test_suspend_action_on_edit_page_deactivates_an_approved_organization(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::create([
            'name' => 'Active Bank', 'slug' => 'active-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        Livewire::test(EditOrganization::class, ['record' => $organization->getKey()])
            ->assertActionHasLabel('toggleApproval', 'Suspend')
            ->callAction('toggleApproval');

        $this->assertFalse($organization->fresh()->is_active);
    }

    public function test_approve_action_also_available_on_view_page(): void
    {
        $this->actingAsAdmin();
        $organization = Organization::create([
            'name' => 'Pending Bank 2', 'slug' => 'pending-bank-2', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => false,
        ]);

        Livewire::test(ViewOrganization::class, ['record' => $organization->getKey()])
            ->callAction('toggleApproval');

        $this->assertTrue($organization->fresh()->is_active);
    }
}
