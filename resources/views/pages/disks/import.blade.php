<div><br>

    <x-card>

        <x-slot name="headerLeft">
            <h5 class="card-title mb-0">Scan-Posteingang</h5>
        </x-slot>

        <x-slot name="headerRight">
            <small class="text-muted">{{ $disk }}: {{ $path }}</small>
        </x-slot>

        {{-- Toolbar --}}
        <div class="d-flex gap-2 mb-2 flex-wrap">

            @if ($this->mimeTypes)
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-{{ $mimeFilter ? 'primary' : 'secondary' }} dropdown-toggle"
                        type="button" data-bs-toggle="dropdown">
                        {{ $mimeFilter ? Str::afterLast($mimeFilter, '/') : 'Mimetype' }}
                    </button>
                    <ul class="dropdown-menu">
                        <li><span class="dropdown-item-text">Mimetype filtern:</span></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <button class="dropdown-item @if (!$mimeFilter) active @endif"
                                wire:click="filterMime(null)">
                                Alle
                            </button>
                        </li>
                        @foreach ($this->mimeTypes as $type)
                            <li>
                                <button class="dropdown-item @if ($mimeFilter === $type) active @endif"
                                    wire:click="filterMime('{{ $type }}')">
                                    {{ $type }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                    data-bs-toggle="dropdown">
                    {{ $perPage }} pro Seite
                </button>
                <ul class="dropdown-menu">
                    @foreach ([10, 25, 50, 100] as $n)
                        <li>
                            <button class="dropdown-item @if ($perPage === $n) active @endif"
                                wire:click="setPerPage({{ $n }})">
                                {{ $n }} pro Seite
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (count($selectedFiles) > 0)
                <span class="badge bg-primary align-self-center">
                    {{ count($selectedFiles) }} ausgewählt
                </span>
            @endif

        </div>

        {{-- Tabelle --}}
        <table class="table table-sm table-hover table-striped mb-2">
            <thead>
                <tr>
                    <th style="width: 2rem">
                        <input class="form-check-input" type="checkbox" wire:model.live="selectAll">
                    </th>
                    <th></th>
                    <th>
                        <button class="btn btn-link btn-sm p-0 text-dark text-decoration-none fw-semibold"
                            wire:click="sortBy('name')">
                            Dateiname
                            @if ($sort === 'name')
                                <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill"></i>
                            @endif
                        </button>
                    </th>
                    {{-- <th>Typ</th> --}}
                    <th>
                        <button class="btn btn-link btn-sm p-0 text-dark text-decoration-none fw-semibold"
                            wire:click="sortBy('lastModified')">
                            Gespeichert
                            @if ($sort === 'lastModified')
                                <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill"></i>
                            @endif
                        </button>
                    </th>
                    <th>
                        <button class="btn btn-link btn-sm p-0 text-dark text-decoration-none fw-semibold"
                            wire:click="sortBy('size')">
                            Größe
                            @if ($sort === 'size')
                                <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill"></i>
                            @endif
                        </button>
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->files as $file)
                    <tr wire:key="{{ $file }}">
                        <td>
                            <input class="form-check-input" type="checkbox" wire:model.live="selectedFiles"
                                value="{{ $file }}">
                        </td>
                        <td nowrap width="5%">
                            <x-storage.link :disk="$disk" :file="$file" size="fullscreen" />
                        </td>
                        <td>
                            @if ($renamingFile === $file)
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" wire:model="renameName"
                                        wire:keydown.enter="renameFile('{{ $file }}')"
                                        wire:keydown.escape="cancelRename" autofocus>
                                    <button class="btn btn-outline-success" wire:click="renameFile('{{ $file }}')"
                                        wire:loading.attr="disabled" title="Speichern">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" wire:click="cancelRename"
                                        title="Abbrechen">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            @else
                                {{ basename($file) }}
                            @endif
                        </td>
                        {{-- <td>
                        <span class="badge bg-secondary">
                            {{ strtolower(pathinfo($file, PATHINFO_EXTENSION)) }}
                        </span>
                    </td> --}}
                        <td class="text-nowrap text-muted" style="font-size: 1.0em">
                            @php
                                try {
                                    $ts = Storage::disk($disk)->lastModified($file);
                                } catch (\Throwable) {
                                    $ts = null;
                                }
                            @endphp
                            <span class="badge bg-secondary">
                                {{ $ts ? \Carbon\Carbon::createFromTimestamp($ts)->format('d.m.Y H:i') : '-' }}
                            </span>
                        </td>
                        <td align="right">
                            @php
                                try {
                                    $size = Storage::disk($disk)->size($file);
                                } catch (\Throwable) {
                                    $size = 0;
                                }
                            @endphp
                            <span class="badge bg-secondary">
                                {{ $size ? Number::fileSize($size) : '-' }}
                            </span>
                        </td>
                        <td nowrap width="1%">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" wire:click="startRename('{{ $file }}')"
                                    wire:loading.attr="disabled" title="Umbenennen">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger" wire:click="deleteFile('{{ $file }}')"
                                    wire:confirm="Datei '{{ addslashes(basename($file)) }}' wirklich löschen?"
                                    wire:loading.attr="disabled" title="Löschen">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted">--- Keine Dateien ---</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $this->files->links() }}

        <x-slot name="footer">
            {{-- Betreff & Import --}}
            <div class="d-flex gap-2 align-items-center mt-3">
                <input type="text" wire:model.live="subject" class="form-control" placeholder="Bezeichnung..."
                    @disabled($modelId)>
                <div class="form-check form-switch mb-0 text-nowrap"
                    title="PDF/A-Fassung ersetzt das Original, Original {{ config('files.archive.own.trash_days', 90) }} Tage im Papierkorb">
                    <input class="form-check-input" type="checkbox" role="switch" id="import-own" wire:model="own">
                    <label class="form-check-label small" for="import-own">Eigene Dokumente</label>
                </div>
                <button type="button" class="btn btn-sm btn-primary text-nowrap" wire:click="importFiles"
                    wire:loading.attr="disabled" wire:target="importFiles" @disabled(empty($selectedFiles))>
                    <i class="bi bi-download me-1"></i>
                    <span wire:loading.remove wire:target="importFiles">Importieren
                        ({{ count($selectedFiles) }})</span>
                    <span wire:loading wire:target="importFiles">Wird importiert…</span>
                </button>
            </div>
        </x-slot>

    </x-card>

    {{-- Debug-Ausgabe --}}
    <hr>
    <small class="text-muted">[-- DEBUG: Livewire State (Debug) --]
        <pre class="mb-0">disk: {{ $disk }}</pre>
        <pre class="mb-0">path: {{ $path }}</pre>
        <pre class="mb-0">modelType: {{ $modelType }}</pre>
        <pre class="mb-0">modelId: {{ $modelId }}</pre>
        <pre class="mb-0">subject: {{ $subject }}</pre>
        <pre class="mb-0">data: {{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
    </small>

</div>
