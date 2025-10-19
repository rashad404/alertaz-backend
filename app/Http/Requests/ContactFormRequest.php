<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ad' => 'required|string|max:255',
            'soyad' => 'required|string|max:255',
            'telefon' => 'required|string|max:32',
            'email' => 'required|email|max:255',
            'mesaj' => 'nullable|string',
            'terms' => 'accepted',
        ];
    }

    public function messages()
    {
        return [
            'ad.required' => 'Ad boş ola bilməz.',
            'soyad.required' => 'Soyad boş ola bilməz.',
            'telefon.required' => 'Telefon boş ola bilməz.',
            'email.required' => 'E-mail boş ola bilməz.',
            'email.email' => 'E-mail düzgün formatda deyil.',
            'terms.accepted' => 'Qaydalar və şərtlərlə razılaşmalısınız.',
        ];
    }
}
