<?php

declare(strict_types=1);

namespace App\Modules\Branding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadLogoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // SVG fica de fora de propósito: pode carregar script (XSS).
            'logo' => ['required', 'file', 'mimes:png,jpg,webp', 'max:'.config('studio_media.max_logo_kilobytes')],
        ];
    }
}
