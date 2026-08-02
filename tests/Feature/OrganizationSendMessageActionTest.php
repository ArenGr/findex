<?php

namespace Tests\Feature;

use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Mail\AdminMessageToOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationSendMessageActionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Test Admin', 'email' => 'test-admin@example.com']);
        $this->actingAs($admin, 'admin');
    }

    public function test_send_message_action_on_view_page_emails_every_organization_user(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $organization = Organization::create([
            'name' => 'Ameria Bank', 'slug' => 'ameria-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $orgUser = User::factory()->organization($organization)->create(['email' => 'staff@ameria.example']);

        Livewire::test(ViewOrganization::class, ['record' => $organization->getKey()])
            ->callAction('sendMessage', data: [
                'from' => 'support',
                'subject' => 'A quick update',
                'body' => 'Hello from the Findex team.',
            ]);

        // hasFrom() isn't used here: from() is only set inside build(), which
        // Mail::fake() doesn't invoke before handing the mailable to this
        // closure - the fromAddress/fromName constructor properties (set
        // directly from the selected "Send as" option) are the reliable
        // way to assert which identity was chosen.
        Mail::assertQueued(AdminMessageToOrganization::class, function (AdminMessageToOrganization $mail) use ($organization, $orgUser) {
            return $mail->organization->is($organization)
                && $mail->messageSubject === 'A quick update'
                && $mail->fromAddress === 'support@findex.am'
                && $mail->fromName === 'Findex Support'
                && $mail->hasTo($orgUser->email);
        });
    }

    public function test_send_message_action_warns_instead_of_sending_when_organization_has_no_users(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $organization = Organization::create([
            'name' => 'No Staff Bank', 'slug' => 'no-staff-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        Livewire::test(ListOrganizations::class)
            ->callTableAction('sendMessage', $organization, data: [
                'from' => 'findex-team',
                'subject' => 'A quick update',
                'body' => 'Hello from the Findex team.',
            ]);

        Mail::assertNothingQueued();
    }
}
