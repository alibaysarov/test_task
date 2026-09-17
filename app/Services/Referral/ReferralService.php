<?php

namespace App\Services\Referral;

use App\Exceptions\InvalidReferralCodeException;
use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;

/**
 * Регистрирует реферальные привязки, получает приглашённых и сводку начислений.
 * Рассчитывает вознаграждение; само начисление выполняет PaymentObserver.
 */
class ReferralService
{
    /**
     * Создаёт привязку приведённого мастера к владельцу кода без начислений.
     * Повтор с допустимым чужим кодом возвращает исходную привязку.
     *
     * @throws InvalidReferralCodeException Если код не найден или принадлежит самому мастеру.
     */
    public function registerReferral(Master $referred, string $code): Referral
    {
        $referrer = Master::where('referral_code', $code)->first();

        if (empty($referrer) || $referrer->id === $referred->id) {
            throw new InvalidReferralCodeException();
        }

        return Referral::firstOrCreate(
            [
                'referred_master_id' => $referred->id,
            ],
            [
                'referrer_master_id' => $referrer->id,
                'program' => Referral::PROGRAM_MASTER_INVITE,
                'status' => Referral::STATUS_PENDING,
            ]
        );
    }

    /**
     * Возвращает приглашённых мастера по ID привязки с суммой всех начислений.
     * counted означает статус rewarded, дата привязки представлена в ISO 8601 UTC.
     *
     * @return list<array{id: int, master_id: int, name: string|null, attached_at: string|null, counted: bool, earned_amount: int}>
     */
    public function invitedMasters(Master $master): array
    {
        return Referral::query()
            ->where('referrer_master_id', $master->id)
            ->with('referredMaster')
            ->withSum('earnings', 'amount')
            ->orderBy('id')
            ->get()
            ->map(fn (Referral $referral) => [
                'id' => $referral->id,
                'master_id' => $referral->referred_master_id,
                'name' => $referral->referredMaster?->name,
                'attached_at' => $referral->created_at?->utc()->toISOString(),
                'counted' => $referral->status === Referral::STATUS_REWARDED,
                'earned_amount' => (int) ($referral->earnings_sum_amount ?? 0),
            ])->values()->all();
    }

    /**
     * Суммирует сохранённые начисления мастера и считает привязки со статусом rewarded.
     * Суммы целочисленные, не пересчитываются при изменении настроек вознаграждения.
     *
     * @return array{total_accrued: int, pending: int, paid: int, counted_referrals: int}
     */
    public function earningsSummary(Master $master): array
    {
        $earnings = ReferralEarning::where('referrer_master_id', $master->id);

        return [
            'total_accrued' => (int) (clone $earnings)->sum('amount'),
            'pending' => (int) (clone $earnings)->where('status', ReferralEarning::STATUS_PENDING)->sum('amount'),
            'paid' => (int) (clone $earnings)->where('status', ReferralEarning::STATUS_PAID)->sum('amount'),
            'counted_referrals' => Referral::where('referrer_master_id', $master->id)
                ->where('status', Referral::STATUS_REWARDED)->count(),
        ];
    }

    /**
     * Сумма вознаграждения реферера с одного платежа реферала.
     *
     * Сумма платежа умножается на целочисленное значение referral.percent
     * без деления на 100, результат округляется через round() и приводится к int.
     */
    public function rewardAmount(int $paymentAmount): int
    {
        $percent = (int) config('referral.percent');

        return (int) round($paymentAmount * $percent);
    }
}
