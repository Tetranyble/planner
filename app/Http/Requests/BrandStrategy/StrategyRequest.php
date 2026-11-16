<?php

namespace App\Http\Requests\BrandStrategy;

use Illuminate\Foundation\Http\FormRequest;

abstract class StrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
