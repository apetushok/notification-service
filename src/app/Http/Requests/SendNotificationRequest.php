<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => [
                'required',
                'string',
                Rule::in(NotificationChannel::values()),
            ],
            'priority' => [
                'sometimes',
                'string',
                Rule::in(NotificationPriority::values()),
            ],
            'content' => [
                'required',
                'string',
                'max:5000',
            ],
            'recipients' => [
                'required',
                'array',
                'min:1',
                'max:10000', // Лимит на один запрос
            ],
            'recipients.*' => [
                'required',
                'string',
                'max:255',
            ],
            'metadata' => [
                'sometimes',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Необходимо указать канал отправки',
            'channel.in' => 'Поддерживаются только каналы: sms, email, push',
            'content.required' => 'Текст сообщения обязателен',
            'content.max' => 'Текст сообщения не может превышать 5000 символов',
            'recipients.required' => 'Необходимо указать получателей',
            'recipients.min' => 'Должен быть хотя бы один получатель',
            'recipients.max' => 'Максимальное количество получателей в одном запросе: 10000',
        ];
    }

    public function attributes(): array
    {
        return [
            'channel' => 'канал отправки',
            'priority' => 'приоритет',
            'content' => 'текст сообщения',
            'recipients' => 'получатели',
        ];
    }
}
