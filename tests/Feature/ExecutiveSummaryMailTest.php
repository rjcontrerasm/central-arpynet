<?php

namespace Tests\Feature;

use App\Mail\ExecutiveSummaryMail;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExecutiveSummaryMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_summary_email_is_sent(): void
    {
        Mail::fake();

        [$user] = $this->context();

        $this->artisan('summary:email today')
            ->assertExitCode(0);

        Mail::assertSent(
            ExecutiveSummaryMail::class,
            fn (ExecutiveSummaryMail $mail): bool =>
                $mail->hasTo($user->email)
                && $mail->period === 'today',
        );

        $this->assertDatabaseHas(
            'summary_email_deliveries',
            [
                'user_id' => $user->id,
                'period' => 'today',
                'status' => 'sent',
            ],
        );
    }

    public function test_same_email_is_not_duplicated(): void
    {
        Mail::fake();

        $this->context();

        $this->artisan('summary:email today')
            ->assertExitCode(0);

        $this->artisan('summary:email today')
            ->assertExitCode(0);

        Mail::assertSentCount(1);

        $this->assertDatabaseCount(
            'summary_email_deliveries',
            1,
        );
    }

    public function test_force_can_resend_same_period(): void
    {
        Mail::fake();

        $this->context();

        $this->artisan('summary:email today')
            ->assertExitCode(0);

        $this->artisan(
            'summary:email today --force',
        )->assertExitCode(0);

        Mail::assertSentCount(2);

        $this->assertDatabaseCount(
            'summary_email_deliveries',
            1,
        );
    }

    public function test_mail_uses_aligned_sender(): void
    {
        $mail = new ExecutiveSummaryMail(
            'today',
            [
                'critical' => 1,
                'attention' => 2,
            ],
            now()->toIso8601String(),
            'https://central.arpynet.com/resumen',
        );

        $from = $mail->envelope()->from;

        $this->assertNotNull($from);

        $this->assertSame(
            'notificaciones@central.arpynet.com',
            $from->address,
        );

        $this->assertSame(
            'Central ARPYNET',
            $from->name,
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' =>
                $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        return [$user, $organization];
    }
}
