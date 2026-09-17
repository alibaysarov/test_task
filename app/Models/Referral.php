<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Привязка приведённого мастера к тому, кто его привёл.
 *
 * Программы:
 *  - master_invite — мастер пригласил мастера по своему коду
 *  - influencer    — мастер пришёл по промокоду инфлюенсера
 *
 * @property-read Master $referrerMaster
 * @property-read Master $referredMaster
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReferralEarning> $earnings
 */
class Referral extends Model
{
    use HasFactory;

    public const PROGRAM_MASTER_INVITE = 'master_invite';
    public const PROGRAM_INFLUENCER = 'influencer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_REWARDED = 'rewarded';

    protected $fillable = [
        'referrer_master_id',
        'referred_master_id',
        'program',
        'status',
    ];

    /** Возвращает мастера, который привёл реферала. */
    public function referrerMaster(): BelongsTo
    {
        return $this->belongsTo(Master::class, 'referrer_master_id');
    }

    /** Возвращает приглашённого мастера. */
    public function referredMaster(): BelongsTo
    {
        return $this->belongsTo(Master::class, 'referred_master_id');
    }

    /** Начисления по этой привязке. */
    public function earnings(): HasMany
    {
        return $this->hasMany(ReferralEarning::class);
    }

    /**
     * Активные привязки — те, что ещё могут принести вознаграждение.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REWARDED);
    }
}
