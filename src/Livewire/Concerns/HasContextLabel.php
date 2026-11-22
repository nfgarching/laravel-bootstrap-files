<?php

namespace Nfgarching\Files\Livewire\Concerns;

use Illuminate\Foundation\Auth\User as AuthUser;
use Livewire\Attributes\Computed;

/**
 * Anzeige der Zuordnung im Kopf von Upload/Import: „Dokument #123“ bei
 * vorhandenem Datensatz, sonst „neu: Dokument“ für das Model, das der
 * Listener zu NewFiles anlegt. User (Vorgabe ohne Bezug) = keine Zuordnung.
 */
trait HasContextLabel
{
    #[Computed]
    public function contextLabel(): ?string
    {
        if ($this->modelType === '' || is_a($this->modelType, AuthUser::class, true)) {
            return null;
        }

        $name = class_basename($this->modelType);

        return $this->modelId > 0
            ? $name.' #'.$this->modelId
            : 'neu: '.$name;
    }
}
