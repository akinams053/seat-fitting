<?php

namespace CryptaTech\Seat\Fitting\Validation;

use Illuminate\Foundation\Http\FormRequest;

class FittingValidation extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'fitSelection' => 'nullable',
            'eftfitting' => 'required',
            'minimum_dps' => 'nullable|numeric|min:0',
            'minimum_dph' => 'nullable|numeric|min:0',
            'advanced_dps' => 'nullable|numeric|min:0',
            'advanced_dph' => 'nullable|numeric|min:0',
        ];
    }
}
