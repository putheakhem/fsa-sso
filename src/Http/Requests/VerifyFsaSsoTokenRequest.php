<?php

declare(strict_types=1);

namespace PutheaKhem\FsaSso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyFsaSsoTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'authToken' => ['required', 'string'],
        ];
    }
}
