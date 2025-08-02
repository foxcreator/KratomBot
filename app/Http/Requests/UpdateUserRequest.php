<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user'); // ← просто ID

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9\s\-]{7,20}$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Імʼя обовʼязкове',
            'email.required' => 'Email обовʼязковий',
            'email.email' => 'Email має бути коректним',
            'email.unique' => 'Email вже використовується',
            'phone.required' => 'Телефон обовʼязковий',
            'phone.regex' => 'Телефон має бути у форматі +380...',
            'phone.unique' => 'Цей телефон вже використовується',
            'password.min' => 'Пароль має містити щонайменше 8 символів',
        ];
    }
}
