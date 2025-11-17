<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receiver_email' => 'required|email|exists:users,email',
            'amount' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receiver_email.required' => 'Receiver email is required',
            'receiver_email.email' => 'Invalid email format',
            'receiver_email.exists' => 'No user found with this email',
            'amount.required' => 'Amount is required',
            'amount.integer' => 'Amount must be an integer (in cents)',
            'amount.min' => 'Amount must be greater than 0',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if trying to send to self
            if ($this->receiver_email === auth()->user()->email) {
                $validator->errors()->add('receiver_email', 'You cannot transfer money to yourself');
            }
        });
    }
}
