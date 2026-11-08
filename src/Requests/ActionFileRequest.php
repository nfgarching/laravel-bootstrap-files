<?php

namespace Nfgarching\Files\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sammelaktion aus der Datei-Liste (Formular in pages/files/index.blade.php):
 * `switchTo` ist der gedrückte Button, `checks[]` die markierten Zeilen (id).
 */
class ActionFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'switchTo' => ['required', 'string', 'in:delete,testCheck'],
            'checks' => ['required', 'array', 'min:1'],
            'checks.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'checks.required' => 'Bitte mindestens eine Datei auswählen.',
            'checks.min' => 'Bitte mindestens eine Datei auswählen.',
        ];
    }

    /**
     * Kein eigenes Formular mit Fehlerausgabe: Fehler zusätzlich als
     * Flash-Meldung, damit das Layout sie als Toast anzeigt.
     */
    protected function failedValidation(Validator $validator): void
    {
        session()->flash('message', implode("\n", $validator->errors()->all()));
        session()->flash('sticky', true);

        parent::failedValidation($validator);
    }
}
