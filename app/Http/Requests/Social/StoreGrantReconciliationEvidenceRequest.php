<?php

namespace App\Http\Requests\Social;

use App\Domain\Social\GrantReconciliationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreGrantReconciliationEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return GrantReconciliationRules::evidence();
    }
}
