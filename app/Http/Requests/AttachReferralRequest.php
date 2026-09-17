<?php

namespace App\Http\Requests;

use App\Exceptions\CurrentMasterNotFoundException;
use App\Models\Master;
use Illuminate\Foundation\Http\FormRequest;

class AttachReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->attributes->get('current_master') instanceof Master) {
            throw new CurrentMasterNotFoundException();
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
