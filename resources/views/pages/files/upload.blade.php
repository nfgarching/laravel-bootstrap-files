<div>
    {{-- <div class="container-fluid"><br>

    <div class="row justify-content-center">
        <div class="col-md-10"> --}}

    <x-card>

        <x-slot name="headerLeft">
            <h5 class="card-title mb-0">Datei - Upload</h5>
        </x-slot>

        <x-slot name="headerRight"></x-slot>

        {{-- Upload-Formular --}}
        <div class="mb-3" x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true"
            x-on:livewire-upload-finish="uploading = false; $wire.storeFiles()"
            x-on:livewire-upload-cancel="uploading = false" x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress">
            <label for="fileInput" class="form-label">Dateien auswählen</label>
            <input class="form-control" type="file" id="fileInput" wire:model="files" multiple>
            <div class="form-text">Maximale Dateigröße: 10 MB pro Datei</div>
            @error('files.*')
                <span class="text-danger small">{{ $message }}</span>
            @enderror

            <div x-show="uploading" x-cloak class="mt-2">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                        :style="'width: ' + progress + '%'" :aria-valuenow="progress" aria-valuemin="0"
                        aria-valuemax="100" x-text="progress + '%'"></div>
                </div>
            </div>
        </div>

        <hr>

        @if (count($uploadedFiles) > 0)
            <div class="mb-3">
                <div class="form-text mb-2">
                    <i class="bi bi-info-circle"></i>
                    Die geprüfte und gesiegelte PDF/A-Fassung ersetzt das Original; das Original bleibt
                    {{ config('files.archive.original.trash_days', 90) }} Tage im Papierkorb.
                    Bei „Original Dokument“ bleibt das Original die Hauptdatei.
                </div>
                <h6>
                    Hochgeladene Dateien
                    <span class="badge text-bg-primary">{{ count($uploadedFiles) }}</span>
                </h6>
                @php
                    $fileModels = \Nfgarching\Files\Models\File::whereIn('id', array_column($uploadedFiles, 'id'))
                        ->get()
                        ->keyBy('id');
                @endphp
                <ul class="list-group">
                    @foreach ($uploadedFiles as $file)
                        <li class="list-group-item d-flex justify-content-between align-items-center"
                            wire:key="{{ $file['id'] }}">
                            <span class="d-flex align-items-center gap-2">
                                @if ($fileModels->has($file['id']))
                                    <x-button.filelink :file="$fileModels[$file['id']]" size="fullscreen" />
                                @endif
                                {{ $file['name'] }}
                                <span class="badge text-bg-secondary ms-1">
                                    {{ $file['file_type_label'] }}
                                </span>
                            </span>
                            @if ($file['file_type'] === 'pdf')
                                <div class="form-check form-switch mb-0 text-nowrap"
                                    title="{{ $file['producer'] ? 'Erstellt mit: '.$file['producer'] : 'Kein Erzeugerprogramm erkannt' }}">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="original-{{ $file['id'] }}" @checked($file['original'])
                                        wire:click="toggleOriginal({{ $file['id'] }})" wire:loading.attr="disabled">
                                    <label class="form-check-label small" for="original-{{ $file['id'] }}">
                                        Original Dokument
                                        @if ($file['original'] && in_array($file['original_source'], ['producer', 'disk'], true))
                                            <span class="text-muted">(erkannt)</span>
                                        @endif
                                    </label>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-slot name="footer">
            {{-- Speichern --}}
            <div class="d-flex gap-2 align-items-center mt-3">
                <input type="text" wire:model.live="subject" class="form-control" placeholder="Bezeichnung..."
                    @disabled($modelId)>
                <button type="button" class="btn btn-sm btn-primary text-nowrap" wire:click="dispatchToModel"
                    wire:loading.attr="disabled" wire:target="dispatchToModel" @disabled(count($uploadedFiles) === 0)>
                    <i class="bi bi-check-lg me-2"></i>
                    <span wire:loading.remove wire:target="dispatchToModel">Speichern</span>
                    <span wire:loading wire:target="dispatchToModel">Wird gespeichert…</span>
                </button>
            </div>
        </x-slot>

    </x-card>

    {{-- Debug-Ausgabe --}}
    <hr>
    <small class="text-muted">[-- DEBUG: Livewire State (Debug) --]
        <pre class="mb-0">mmodelType: {{ $modelType }}</pre>
        <pre class="mb-0">modelId: {{ $modelId }}</pre>
        <pre class="mb-0">subject: {{ $subject }}</pre>
        <pre class="mb-0">uploadedFiles: {{ json_encode($uploadedFiles, JSON_PRETTY_PRINT) }}</pre>
        <pre class="mb-0">data: {{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
    </small>

    {{-- </div>
    </div>
</div> --}}
</div>
