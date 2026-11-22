<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePackageOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['package_id' => ['required', 'string', 'ulid']];
    }
}
