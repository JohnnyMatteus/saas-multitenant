<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação exige todos os campos; na edição o número de aulas não muda (pedidos já feitos
 * guardam o seu) e todos os campos são opcionais. Moeda fixa em BRL.
 */
final class LessonPackageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:120'],
            'lesson_count' => $this->isMethod('POST') ? ['required', 'integer', 'between:1,500'] : ['prohibited'],
            'price_cents' => [$presence, 'integer', 'between:1,100000000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
