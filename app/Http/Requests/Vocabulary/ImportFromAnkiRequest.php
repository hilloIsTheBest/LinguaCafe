<?php

namespace App\Http\Requests\Vocabulary;

use Illuminate\Foundation\Http\FormRequest;

class ImportFromAnkiRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'importFile' => 'required|file',
            'onlyUpdate' => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'onlyUpdate' => $this->onlyUpdate === 'true' || $this->onlyUpdate === true,
        ]);
    }
}

