<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers multi-staff org logins: adding a teammate, and the guard against
 * removing yourself or the last remaining login (mirrors
 * AdminResource::canDelete()'s self/last-admin guard).
 */
class OrganizationTeamTest extends TestCase
{
    use RefreshDatabase;

    private function organizationOwner(): array
    {
        $organization = Organization::create([
            'name' => 'Team Test Bank', 'slug' => 'team-test-bank-' . uniqid(), 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $owner = User::factory()->organization($organization)->create();

        return [$organization, $owner];
    }

    public function test_owner_can_add_a_teammate_who_can_then_log_in(): void
    {
        [, $owner] = $this->organizationOwner();

        $this->actingAs($owner, 'organization')->post(route('org.dashboard.team.store', ['locale' => 'en']), [
            'name' => 'New Teammate',
            'email' => 'teammate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $teammate = User::where('email', 'teammate@example.com')->first();
        $this->assertNotNull($teammate);
        $this->assertTrue($teammate->isOrganization());
        $this->assertSame($owner->organization_id, $teammate->organization_id);

        $this->post(route('org.login', ['locale' => 'en']), [
            'email' => 'teammate@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('org.dashboard.index'));
    }

    public function test_owner_cannot_remove_themselves(): void
    {
        [$organization, $owner] = $this->organizationOwner();
        User::factory()->organization($organization)->create(); // a second teammate, so "last one" isn't the blocker here

        $this->actingAs($owner, 'organization')
            ->delete(route('org.dashboard.team.destroy', ['locale' => 'en', 'user' => $owner->id]))
            ->assertRedirect();

        $this->assertNotNull($owner->fresh());
    }

    public function test_owner_cannot_remove_the_last_remaining_teammate(): void
    {
        [, $owner] = $this->organizationOwner();

        // $owner is the only login this org has - removing anyone (even
        // via a crafted request against their own id) must be blocked.
        $this->actingAs($owner, 'organization')
            ->delete(route('org.dashboard.team.destroy', ['locale' => 'en', 'user' => $owner->id]))
            ->assertRedirect();

        $this->assertNotNull($owner->fresh());
    }

    public function test_owner_can_remove_a_teammate_when_more_than_one_exists(): void
    {
        [$organization, $owner] = $this->organizationOwner();
        $teammate = User::factory()->organization($organization)->create();

        $this->actingAs($owner, 'organization')
            ->delete(route('org.dashboard.team.destroy', ['locale' => 'en', 'user' => $teammate->id]))
            ->assertRedirect();

        $this->assertNull($teammate->fresh());
    }

    public function test_owner_cannot_remove_another_orgs_teammate(): void
    {
        [, $owner] = $this->organizationOwner();
        [$otherOrg] = $this->organizationOwner();
        $otherTeammate = User::factory()->organization($otherOrg)->create();

        $this->actingAs($owner, 'organization')
            ->delete(route('org.dashboard.team.destroy', ['locale' => 'en', 'user' => $otherTeammate->id]))
            ->assertNotFound();

        $this->assertNotNull($otherTeammate->fresh());
    }
}
