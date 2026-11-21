<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelSessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['sometimes', 'nullable', 'string', 'max:255']];
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
