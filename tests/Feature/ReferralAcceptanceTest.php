<?php

namespace Tests\Feature;

use App\Models\Master;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralEarning;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_is_public_and_identity_requires_a_known_positive_integer(): void
    {
        $this->getJson('/api/ping')->assertOk()->assertExactJson(['ok' => true]);

        foreach ([null, '0', '-1', '1.5', 'nope', '999'] as $id) {
            $request = $this->getJson('/api/referrals/my', $id === null ? [] : ['X-Master-Id' => $id]);
            $request->assertUnauthorized();
        }
    }

    public function test_attach_validates_all_invalid_code_shapes(): void
    {
        $master = $this->master('Current', 'CURRENT');

        foreach ([[], ['code' => null], ['code' => []], ['code' => 123], ['code' => ''], ['code' => str_repeat('A', 256)]] as $payload) {
            $this->actingAsMaster($master)->postJson('/api/referrals/attach', $payload)
                ->assertStatus(422)
                ->assertJsonPath('message', 'The given data was invalid.')
                ->assertJsonValidationErrors(['code']);
        }

        foreach (['UNKNOWN', $master->referral_code] as $code) {
            $this->actingAsMaster($master)->postJson('/api/referrals/attach', ['code' => $code])
                ->assertStatus(422)
                ->assertExactJson([
                    'message' => 'The referral code is invalid.',
                    'errors' => ['code' => ['The referral code is invalid.']],
                ]);
        }
    }

    public function test_attach_returns_json_validation_errors_without_accept_header(): void
    {
        $master = $this->master('Current', 'CURRENT');

        $this->actingAsMaster($master)->post('/api/referrals/attach', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'message' => 'The given data was invalid.',
                'errors' => ['code' => ['The code field is required.']],
            ]);

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_attach_checks_identity_before_validating_parameters(): void
    {
        foreach ([null, 'abc', '999'] as $id) {
            $this->post('/api/referrals/attach', [], $id === null ? [] : ['X-Master-Id' => $id])
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'Current master not found.']);
        }

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_attach_is_idempotent_never_rebinds_and_uses_identity_from_header(): void
    {
        $first = $this->master('First', 'FIRST');
        $second = $this->master('Second', 'SECOND');
        $referred = $this->master('Referred', 'REFERRED');

        $created = $this->actingAsMaster($referred)->postJson('/api/referrals/attach', [
            'code' => 'FIRST', 'referred_master_id' => $second->id,
        ])->assertCreated()->assertJsonPath('created', true);
        $id = $created->json('data.id');

        $this->actingAsMaster($referred)->postJson('/api/referrals/attach', ['code' => 'FIRST'])
            ->assertOk()->assertJsonPath('created', false)->assertJsonPath('data.id', $id);
        $this->actingAsMaster($referred)->postJson('/api/referrals/attach', ['code' => 'SECOND'])
            ->assertOk()->assertJsonPath('data.referrer_master_id', $first->id);

        foreach (['UNKNOWN', 'REFERRED'] as $code) {
            $this->actingAsMaster($referred)->postJson('/api/referrals/attach', ['code' => $code])
                ->assertStatus(422)->assertJsonValidationErrors(['code']);
        }

        $this->assertDatabaseHas('referrals', [
            'id' => $id,
            'referrer_master_id' => $first->id,
            'referred_master_id' => $referred->id,
        ]);
        $this->assertDatabaseMissing('referrals', ['referred_master_id' => $second->id]);
        $this->assertSame(1, Referral::count());
    }

    public function test_referral_lists_are_isolated_stably_ordered_and_match_the_contract(): void
    {
        $owner = $this->master('Owner', 'OWNER');
        $other = $this->master('Other', 'OTHER');
        $first = $this->referral($owner, $this->master('First', 'FIRST'));
        $second = $this->referral($owner, $this->master('Second', 'SECOND'));
        $this->referral($other, $this->master('Private', 'PRIVATE'));
        $this->earning($first, 100);

        $response = $this->actingAsMaster($owner)->getJson('/api/referrals/my')->assertOk();
        $response->assertJsonCount(2, 'data')->assertJsonStructure([
            'data' => [['id', 'master_id', 'name', 'attached_at', 'counted', 'earned_amount']],
        ]);
        $data = $response->json('data');
        $this->assertSame([$first->id, $second->id], array_column($data, 'id'));
        $this->assertIsInt($data[0]['id']);
        $this->assertIsInt($data[0]['master_id']);
        $this->assertIsString($data[0]['name']);
        $this->assertIsString($data[0]['attached_at']);
        $this->assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}T/', $data[0]['attached_at']);
        $this->assertIsBool($data[0]['counted']);
        $this->assertIsInt($data[0]['earned_amount']);

        $this->actingAsMaster($this->master('Empty', 'EMPTY'))->getJson('/api/referrals/my')
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_earnings_summary_has_empty_zeroes_paid_pending_and_isolation(): void
    {
        $owner = $this->master('Owner', 'OWNER');
        $other = $this->master('Other', 'OTHER');
        $pending = $this->referral($owner, $this->master('Pending', 'PENDING'));
        $paid = $this->referral($owner, $this->master('Paid', 'PAID'), Referral::STATUS_REWARDED);
        $private = $this->referral($other, $this->master('Private', 'PRIVATE'), Referral::STATUS_REWARDED);
        $this->earning($pending, 125, ReferralEarning::STATUS_PENDING);
        $this->earning($paid, 75, ReferralEarning::STATUS_PAID);
        $this->earning($private, 999, ReferralEarning::STATUS_PENDING);

        $this->actingAsMaster($owner)->getJson('/api/referrals/earnings')->assertOk()->assertExactJson([
            'total_accrued' => 200,
            'pending' => 125,
            'paid' => 75,
            'counted_referrals' => 1,
        ]);
        $this->actingAsMaster($this->master('Empty', 'EMPTY'))->getJson('/api/referrals/earnings')->assertOk()
            ->assertExactJson(['total_accrued' => 0, 'pending' => 0, 'paid' => 0, 'counted_referrals' => 0]);
    }

    public function test_first_positive_card_or_sbp_payment_creates_one_earning(): void
    {
        foreach ([Payment::TYPE_CARD, Payment::TYPE_SBP] as $type) {
            $referrer = $this->master("Referrer {$type}", "REF{$type}");
            $referred = $this->master("Referred {$type}", "USER{$type}");
            $referral = $this->referral($referrer, $referred);

            Payment::create(['master_id' => $referred->id, 'amount' => 3000, 'type' => $type]);
            Payment::create(['master_id' => $referred->id, 'amount' => 3000, 'type' => $type]);

            $this->assertDatabaseHas('referral_earnings', ['referral_id' => $referral->id, 'amount' => 30000]);
        }

        $this->assertSame(2, ReferralEarning::count());
    }

    public function test_promo_and_trial_do_not_block_first_positive_card_but_zero_card_does(): void
    {
        $referrer = $this->master('Referrer', 'REFERRER');
        $allowed = $this->master('Allowed', 'ALLOWED');
        $blocked = $this->master('Blocked', 'BLOCKED');
        $allowedReferral = $this->referral($referrer, $allowed);
        $blockedReferral = $this->referral($referrer, $blocked);

        Payment::create(['master_id' => $allowed->id, 'amount' => 0, 'type' => Payment::TYPE_PROMO]);
        Payment::create(['master_id' => $allowed->id, 'amount' => 0, 'type' => Payment::TYPE_TRIAL]);
        Payment::create(['master_id' => $allowed->id, 'amount' => 100, 'type' => Payment::TYPE_CARD]);
        Payment::create(['master_id' => $blocked->id, 'amount' => 0, 'type' => Payment::TYPE_CARD]);
        Payment::create(['master_id' => $blocked->id, 'amount' => 100, 'type' => Payment::TYPE_CARD]);

        $this->assertDatabaseHas('referral_earnings', ['referral_id' => $allowedReferral->id, 'amount' => 1000]);
        $this->assertDatabaseMissing('referral_earnings', ['referral_id' => $blockedReferral->id]);
    }

    public function test_payment_history_is_not_retroactively_rewarded_but_blocks_later_payment(): void
    {
        $referrer = $this->master('Referrer', 'REFERRER');
        $referred = $this->master('Referred', 'REFERRED');

        Payment::create(['master_id' => $referred->id, 'amount' => 100, 'type' => Payment::TYPE_CARD]);
        $referral = $this->referral($referrer, $referred);
        $this->assertSame(0, ReferralEarning::count());
        Payment::create(['master_id' => $referred->id, 'amount' => 100, 'type' => Payment::TYPE_SBP]);

        $this->assertDatabaseMissing('referral_earnings', ['referral_id' => $referral->id]);
        $this->assertSame(Referral::STATUS_PENDING, $referral->fresh()->status);
    }

    public function test_reward_is_saved_using_current_config_and_is_not_recalculated_on_get(): void
    {
        $referrer = $this->master('Referrer', 'REFERRER');
        $first = $this->master('First', 'FIRST');
        $second = $this->master('Second', 'SECOND');
        $this->referral($referrer, $first);
        $this->referral($referrer, $second);

        config()->set('referral.percent', 10);
        Payment::create(['master_id' => $first->id, 'amount' => 3000, 'type' => Payment::TYPE_CARD]);
        config()->set('referral.percent', 5);
        Payment::create(['master_id' => $second->id, 'amount' => 2000, 'type' => Payment::TYPE_SBP]);

        $this->assertSame([30000, 10000], ReferralEarning::orderBy('id')->pluck('amount')->all());
        config()->set('referral.percent', 1);
        $this->actingAsMaster($referrer)->getJson('/api/referrals/earnings')->assertOk()
            ->assertJsonPath('total_accrued', 40000);
    }

    public function test_failed_referral_update_rolls_back_the_new_earning(): void
    {
        $referrer = $this->master('Referrer', 'REFERRER');
        $referred = $this->master('Referred', 'REFERRED');
        $referral = $this->referral($referrer, $referred);

        Referral::updating(static function (): void {
            throw new \RuntimeException('Simulated referral write failure.');
        });

        try {
            Payment::create(['master_id' => $referred->id, 'amount' => 100, 'type' => Payment::TYPE_CARD]);
            $this->fail('The simulated referral update must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated referral write failure.', $exception->getMessage());
        } finally {
            Referral::flushEventListeners();
        }

        $this->assertDatabaseMissing('referral_earnings', ['referral_id' => $referral->id]);
        $this->assertSame(Referral::STATUS_PENDING, $referral->fresh()->status);
    }

    public function test_seeder_matches_demo_acceptance_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $masha = Master::where('name', 'Маша')->firstOrFail();

        $this->assertSame(6, Master::count());
        $this->assertSame(4, Referral::count());
        $this->actingAsMaster($masha)->getJson('/api/referrals/earnings')->assertOk()->assertExactJson([
            'total_accrued' => 30000, 'pending' => 30000, 'paid' => 0, 'counted_referrals' => 1,
        ]);
        $this->actingAsMaster($masha)->getJson('/api/referrals/my')->assertOk()
            ->assertJsonCount(4, 'data')->assertJsonPath('data.0.name', 'Ира')->assertJsonPath('data.0.counted', true);
    }

    private function actingAsMaster(Master $master): static
    {
        return $this->withHeader('X-Master-Id', (string) $master->id);
    }

    private function master(string $name, string $code): Master
    {
        return Master::create(['name' => $name, 'referral_code' => $code]);
    }

    private function referral(Master $referrer, Master $referred, string $status = Referral::STATUS_PENDING): Referral
    {
        return Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
            'status' => $status,
        ]);
    }

    private function earning(Referral $referral, int $amount, string $status = ReferralEarning::STATUS_PENDING): ReferralEarning
    {
        $payment = Payment::withoutEvents(fn () => Payment::create([
            'master_id' => $referral->referred_master_id, 'amount' => 1, 'type' => Payment::TYPE_CARD,
        ]));

        return ReferralEarning::create([
            'referrer_master_id' => $referral->referrer_master_id,
            'referred_master_id' => $referral->referred_master_id,
            'referral_id' => $referral->id,
            'payment_id' => $payment->id,
            'payment_amount' => 1,
            'amount' => $amount,
            'percent' => 10,
            'status' => $status,
        ]);
    }
}
