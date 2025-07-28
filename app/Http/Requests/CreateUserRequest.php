<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Імʼя є обовʼязковим',
            'email.required' => 'Email є обовʼязковим',
            'email.email' => 'Email має бути коректним',
            'email.unique' => 'Користувач з таким email вже існує',
            'phone.required' => 'Телефон є обовʼязковим',
            'phone.regex' => 'Телефон має бути у форматі +380... або 0...',
            'password.required' => 'Пароль є обовʼязковим',
            'password.min' => 'Пароль має містити щонайменше 8 символів',
        ];
    }
}
