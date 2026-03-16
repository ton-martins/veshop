<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\ShopCustomer;
use App\Notifications\Shop\VerifyShopCustomerEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopEmailVerificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_customer_verification_notification_is_dispatched_to_queue(): void
    {
        Bus::fake();

        $contractor = $this->createContractor('queue-email-store');

        $customer = ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Queue Customer',
            'email' => 'queue-customer@example.com',
            'phone' => '11999999999',
            'password' => 'Password@123',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $customer->sendEmailVerificationNotification();

        Bus::assertDispatched(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($customer): bool {
            return $job->notification instanceof VerifyShopCustomerEmailNotification
                && (string) $job->queue === (string) config('queue.workloads.mail.queue')
                && (string) $job->connection === (string) config('queue.workloads.mail.connection')
                && $job->notifiables->contains(fn ($notifiable) => (int) $notifiable->id === (int) $customer->id);
        });
    }

    private function createContractor(string $slug): Contractor
    {
        return Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'email' => "{$slug}@example.com",
            'slug' => $slug,
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => Str::title(str_replace('-', ' ', $slug)),
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Pro',
                'require_email_verification' => true,
            ],
            'is_active' => true,
        ]);
    }
}
