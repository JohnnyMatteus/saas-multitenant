<?php

declare(strict_types=1);

namespace App\Modules\Branding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStudioSettingsRequest extends FormRequest
{
    public const int MAX_CANCELLATION_WINDOW_MINUTES = 7 * 24 * 60;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'cancellation_window_minutes' => ['sometimes', 'integer', 'between:0,'.self::MAX_CANCELLATION_WINDOW_MINUTES],
        ];
    }
}
