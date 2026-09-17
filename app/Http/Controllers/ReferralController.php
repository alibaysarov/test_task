<?php

namespace App\Http\Controllers;

use App\Exceptions\CurrentMasterNotFoundException;
use App\Http\Requests\AttachReferralRequest;
use App\Models\Master;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Реферальная программа
 *
 * X-Master-Id — заглушка идентификации, не Bearer-токен.
 * @header Accept application/json
 * @response 401 {"message":"Current master not found."}
 */
class ReferralController extends Controller
{
    public function __construct(private ReferralService $referrals)
    {
    }

    /**
     * Привязать реферальный код.
     *
     * Для первой привязки в демо-наборе используйте мастера 2 (Лена).
     * Повтор с допустимым чужим кодом возвращает прежнюю привязку.
     * Привязка сама по себе не создаёт начислений.
     *
     * @header X-Master-Id 2
     * @header Content-Type application/json
     * @bodyParam code string required Код реферера, до 255 символов; регистр сохраняется. Example: MASHA10
     * @response 201 {"created":true,"data":{"id":5,"referrer_master_id":1,"referred_master_id":2,"status":"pending","attached_at":"2026-01-10T10:00:00.000000Z"}}
     * @response 200 {"created":false,"data":{"id":5,"referrer_master_id":1,"referred_master_id":2,"status":"pending","attached_at":"2026-01-10T10:00:00.000000Z"}}
     * @response 422 {"message":"The referral code is invalid.","errors":{"code":["The referral code is invalid."]}}
     * @response 422 scenario="Ошибка валидации параметров" {"message":"The given data was invalid.","errors":{"code":["The code field is required."]}}
     */
    public function attach(AttachReferralRequest $request): JsonResponse
    {
        $master = $this->currentMaster($request);

        $referral = $this->referrals->registerReferral($master, $request->validated('code'));

        return response()->json([
            'created' => $referral->wasRecentlyCreated,
            'data' => [
                'id' => $referral->id,
                'referrer_master_id' => $referral->referrer_master_id,
                'referred_master_id' => $referral->referred_master_id,
                'status' => $referral->status,
                'attached_at' => $referral->created_at?->utc()->toISOString(),
            ],
        ], $referral->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Приглашённые текущим мастером.
     *
     * Сортировка по ID привязки. Целочисленные суммы включают pending и paid.
     * Даты — ISO 8601 UTC; counted соответствует статусу rewarded.
     *
     * @header X-Master-Id 1
     * @response 200 {"data":[{"id":1,"master_id":3,"name":"Ира","attached_at":"2026-01-10T10:00:00.000000Z","counted":true,"earned_amount":30000}]}
     * @response 200 scenario="Нет приглашённых" {"data":[]}
     */
    public function my(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->referrals->invitedMasters($this->currentMaster($request)),
        ]);
    }

    /**
     * Сводка начислений текущего мастера.
     *
     * Все значения целочисленные. Суммы берутся из сохранённых начислений.
     * counted_referrals — число привязок со статусом rewarded.
     *
     * @header X-Master-Id 1
     * @response 200 {"total_accrued":30000,"pending":30000,"paid":0,"counted_referrals":1}
     * @response 200 scenario="Нет начислений" {"total_accrued":0,"pending":0,"paid":0,"counted_referrals":0}
     */
    public function earnings(Request $request): JsonResponse
    {
        return response()->json($this->referrals->earningsSummary($this->currentMaster($request)));
    }

    private function currentMaster(Request $request): Master
    {
        $master = $request->attributes->get('current_master');

        if (!$master instanceof Master) {
            throw new CurrentMasterNotFoundException();
        }

        return $master;
    }
}
