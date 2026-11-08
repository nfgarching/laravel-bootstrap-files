@props([
    'disk' => '',
    'file' => '',
    'popup' => 'left=100,top=100,width=800,height=800',
    'popupName' => 'attachPopup',
    'preview' => true,
    'thumb' => false,
    'size' => 'xl',
])

@php
    use Illuminate\Support\Facades\Storage;
    use Nfgarching\Files\Enums\FileType;

    $name = basename($file);
    $fileType = FileType::fromExtension(pathinfo($file, PATHINFO_EXTENSION));

    try {
        $fileExists = ! Storage::disk($disk)->missing($file);
    } catch (\Throwable) {
        $fileExists = false;
    }

    $fileUrl = route('download', ['disk' => $disk, 'file' => $file, 'name' => $name]);

    if ($preview) {
        // Kein generiertes Preview für rohe Disk-Dateien vorhanden: Originalbild direkt nutzen.
        $preview = $fileType === FileType::Image && $fileExists;
    }

    $mime = null;
    if ($fileType === FileType::Video && $fileExists) {
        try {
            $mime = Storage::disk($disk)->mimeType($file);
        } catch (\Throwable) {
            $mime = null;
        }
    }

    $modalId = 'fileModal-'.md5($disk.'|'.$file);

    // Tooltip-Inhalt vorab bauen, damit kein @if im <button>-Tag steht
    // (Livewire injiziert dort sonst HTML-Kommentare und zerlegt das Tag).
    $tooltipTitle = $preview
        ? "<img src='{$fileUrl}' class='img-rounded' alt='{$name}'>"
        : $name;
@endphp

@if (! $fileExists)
    <button {{ $attributes->merge(['class' => 'btn btn-outline-secondary']) }}
        x-data
        x-init="bootstrap.Tooltip.getOrCreateInstance($el)"
        data-bs-toggle="tooltip"
        data-bs-title="{{ $name }} (nicht gefunden: {{ $disk }} / {{ $file }})"
        type="button"
        disabled>
        <i class="bi bi-file-x opacity-50"></i>
    </button>
@else
    <button {{ $attributes->merge(['class' => 'btn btn-outline-' . $fileType->color()]) }}
        x-data
        x-init="bootstrap.Tooltip.getOrCreateInstance($el)"
        data-bs-toggle="tooltip"
        data-bs-html="true"
        data-bs-title="{{ $tooltipTitle }}"
        data-attach-url="{{ $fileUrl }}"
        data-attach-name="{{ $name }}"
        data-attach-type="{{ $fileType->value }}"
        data-attach-icon="{{ $fileType->icon() }}"
        data-attach-popup="{{ $popup }}"
        data-attach-popup-name="{{ $popupName }}"
        type="button"
        onclick="btnFileLink(event, '{{ $modalId }}', '{{ $fileUrl }}', '{{ $popup }}', '{{ $popupName }}')"
    >
        <i class="{{ $fileType->icon() }}"></i>
    </button>

    {{-- wire:ignore.self: Livewire-Morph würde sonst die von Bootstrap gesetzten
         Attribute (show, display:block) zurücksetzen – Backdrop bliebe ohne Modal stehen. --}}
    <div class="modal fade" id="{{ $modalId }}" wire:ignore.self tabindex="-1"
        aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
        <div class="modal-dialog modal-{{ $size }} modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-label">
                        <i class="{{ $fileType->icon() }} me-2"></i>
                        {{ $name }}
                    </h5>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary"
                            title="In neuem Tab öffnen">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Schließen"></button>
                    </div>
                </div>

                <div class="modal-body p-0">
                    @switch($fileType)
                        @case(FileType::Image)
                            <img src="{{ $fileUrl }}" alt="{{ $name }}"
                                class="img-fluid d-block mx-auto p-3">
                        @break

                        @case(FileType::Pdf)
                        @case(FileType::Html)
                            <iframe src="{{ $fileUrl }}" class="w-100 border-0" style="min-height: 80vh;"
                                title="{{ $name }}"></iframe>
                        @break

                        @case(FileType::Video)
                            <video controls class="w-100">
                                <source src="{{ $fileUrl }}" type="{{ $mime }}">
                            </video>
                        @break

                        @default
                            <div class="p-4 text-center">
                                <i class="{{ $fileType->icon() }} fs-1 text-secondary mb-3 d-block"></i>
                                <p class="text-muted">{{ $name }}</p>
                                <a href="{{ $fileUrl }}" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-download me-1"></i>
                                    Herunterladen
                                </a>
                            </div>
                    @endswitch
                </div>

            </div>
        </div>
    </div>
@endif

@assets
    <script>
        window.btnFileLink = function (event, modalId, fileUrl, popup, popupName) {
            popup = popup || 'left=100,top=100,width=800,height=800';
            // Benanntes Ziel: Shift-Klick verwendet dasselbe Popup-Fenster erneut,
            // statt für jede Datei ein weiteres zu öffnen (vgl. openFileModal).
            popupName = popupName || 'attachPopup';

            if (event.shiftKey) {
                window.open(fileUrl, popupName, popup);
            } else if (event.ctrlKey || event.metaKey) {
                window.open(fileUrl, '_blank');
            } else {
                bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
            }
        }
    </script>
@endassets
