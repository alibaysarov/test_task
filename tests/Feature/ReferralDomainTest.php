<?php

namespace Tests\Feature;

use App\Exceptions\InvalidReferralCodeException;
use App\Models\Master;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_expected_referrals_and_earning(): void
    {
        $this->seed(DatabaseSeeder::class);

        $masha = Master::where('name', 'Маша')->firstOrFail();
        $ira = Master::where('name', 'Ира')->firstOrFail();

        $this->assertSame(6, Master::count());
        $this->assertSame(4, Referral::count());
        $this->assertDatabaseHas('referral_earnings', [
            'referrer_master_id' => $masha->id,
            'referred_master_id' => $ira->id,
            'payment_amount' => 3000,
            'amount' => 30000,
            'status' => ReferralEarning::STATUS_PENDING,
        ]);

        $this->assertSame(
            Referral::STATUS_REWARDED,
            Referral::where('referred_master_id', $ira->id)->firstOrFail()->status
        );
        $this->assertSame(0, ReferralEarning::whereHas('referredMaster', fn ($query) => $query->whereIn('name', ['Оля', 'Катя', 'Даша']))->count());
        $this->assertSame(0, Referral::whereHas('referredMaster', fn ($query) => $query->where('name', 'Лена'))->count());
    }

    public function test_registration_keeps_the_first_referrer_and_rejects_invalid_codes(): void
    {
        $firstReferrer = Master::create(['name' => 'Первый', 'referral_code' => 'FIRST']);
        $secondReferrer = Master::create(['name' => 'Второй', 'referral_code' => 'SECOND']);
        $referred = Master::create(['name' => 'Реферал', 'referral_code' => 'REFERRED']);
        $service = app(ReferralService::class);

        $first = $service->registerReferral($referred, $firstReferrer->referral_code);
        $second = $service->registerReferral($referred, $secondReferrer->referral_code);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($firstReferrer->id, $second->referrer_master_id);
        $this->assertSame(1, Referral::count());

        foreach (['UNKNOWN', $referred->referral_code] as $code) {
            try {
                $service->registerReferral($referred, $code);
                $this->fail('Invalid referral codes must throw an exception.');
            } catch (InvalidReferralCodeException $exception) {
                $this->assertSame('The referral code is invalid.', $exception->getMessage());
            }
        }

        $this->assertSame(1, Referral::count());
    }

    public function test_database_prevents_duplicate_referrals(): void
    {
        $referrer = Master::create(['name' => 'Реферер', 'referral_code' => 'REFERRER']);
        $referred = Master::create(['name' => 'Реферал', 'referral_code' => 'REFERRED']);
        Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
        ]);
        $this->expectException(QueryException::class);
        Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
        ]);

    }

    public function test_database_prevents_duplicate_earnings_for_a_referral_or_payment(): void
    {
        $referrer = Master::create(['name' => 'Реферер', 'referral_code' => 'REFERRER']);
        $referred = Master::create(['name' => 'Реферал', 'referral_code' => 'REFERRED']);
        $referral = Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
        ]);
        $payment = Payment::withoutEvents(fn () => Payment::create([
            'master_id' => $referred->id,
            'amount' => 100,
            'type' => Payment::TYPE_CARD,
        ]));
        $earning = [
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
            'referral_id' => $referral->id,
            'payment_id' => $payment->id,
            'payment_amount' => 100,
            'amount' => 1000,
            'percent' => 10,
        ];

        ReferralEarning::create($earning);

        $this->expectException(QueryException::class);
        ReferralEarning::create($earning);
    }

    public function test_only_first_monetary_payment_can_create_an_earning(): void
    {
        $referrer = Master::create(['name' => 'Реферер', 'referral_code' => 'REFERRER']);
        $referred = Master::create(['name' => 'Реферал', 'referral_code' => 'REFERRED']);
        Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
        ]);

        Payment::create(['master_id' => $referred->id, 'amount' => 0, 'type' => Payment::TYPE_CARD]);
        Payment::create(['master_id' => $referred->id, 'amount' => 2000, 'type' => Payment::TYPE_CARD]);
        Payment::create(['master_id' => $referred->id, 'amount' => 2000, 'type' => Payment::TYPE_SBP]);

        $this->assertSame(0, ReferralEarning::count());
        $this->assertSame(Referral::STATUS_PENDING, Referral::firstOrFail()->status);
    }
}
