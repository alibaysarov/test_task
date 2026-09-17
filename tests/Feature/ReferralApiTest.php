<?php

namespace Tests\Feature;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_creates_referral_and_repeat_keeps_it(): void
    {
        $referrer = Master::create(['name' => 'Реферер', 'referral_code' => 'REFERRER']);
        $referred = Master::create(['name' => 'Реферал', 'referral_code' => 'REFERRED']);

        $response = $this->withHeader('X-Master-Id', (string) $referred->id)
            ->postJson('/api/referrals/attach', ['code' => $referrer->referral_code]);

        $response->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('data.referrer_master_id', $referrer->id)
            ->assertJsonPath('data.referred_master_id', $referred->id);
        $id = $response->json('data.id');

        $this->withHeader('X-Master-Id', (string) $referred->id)
            ->postJson('/api/referrals/attach', ['code' => $referrer->referral_code])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('data.id', $id);

        $this->assertSame(1, Referral::count());
        $this->assertSame(0, ReferralEarning::count());
    }

    public function test_referral_api_rejects_missing_or_invalid_identity_and_codes(): void
    {
        $master = Master::create(['name' => 'Мастер', 'referral_code' => 'MASTER']);

        $this->getJson('/api/referrals/my')->assertUnauthorized()
            ->assertJson(['message' => 'Current master not found.']);
        $this->withHeader('X-Master-Id', 'abc')->getJson('/api/referrals/earnings')->assertUnauthorized();
        $this->withHeader('X-Master-Id', (string) $master->id)
            ->postJson('/api/referrals/attach', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
        $this->withHeader('X-Master-Id', (string) $master->id)
            ->postJson('/api/referrals/attach', ['code' => 'MASTER'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_my_and_earnings_are_aggregated_and_isolated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $masha = Master::where('name', 'Маша')->firstOrFail();
        $lena = Master::where('name', 'Лена')->firstOrFail();

        $this->withHeader('X-Master-Id', (string) $masha->id)
            ->getJson('/api/referrals/my')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Ира')
            ->assertJsonPath('data.0.counted', true)
            ->assertJsonPath('data.0.earned_amount', 30000)
            ->assertJsonPath('data.1.earned_amount', 0);

        $this->withHeader('X-Master-Id', (string) $masha->id)
            ->getJson('/api/referrals/earnings')
            ->assertOk()
            ->assertJson([
                'total_accrued' => 30000,
                'pending' => 30000,
                'paid' => 0,
                'counted_referrals' => 1,
            ]);

        $this->withHeader('X-Master-Id', (string) $lena->id)
            ->getJson('/api/referrals/my')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
