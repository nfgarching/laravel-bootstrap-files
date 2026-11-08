{{--
    Tab „PDF“: Ergebnis der PDF-Prüfung (custom_properties.inspection) für
    Original und Archivfassung. Siehe docs/PDF-Pruefung.md.
--}}
@use('Illuminate\Support\Carbon')
@use('Illuminate\Support\Number')
@use('Nfgarching\Files\Services\Inspection\SignatureVerdict')
@php
    $inspection = $file->inspection();
    $when = fn (?string $value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('d.m.Y H:i:s') : '–';
    $yesNo = fn (?bool $value) => $value === null ? '–' : ($value ? 'ja' : 'nein');
    $icons = [
        SignatureVerdict::OK => 'bi-check-circle-fill',
        SignatureVerdict::WARN => 'bi-exclamation-triangle-fill',
        SignatureVerdict::FAIL => 'bi-x-circle-fill',
        SignatureVerdict::NONE => 'bi-dash-circle',
    ];
    $sigTypes = [
        '/ETSI.CAdES.detached' => 'PAdES (CAdES)',
        '/adbe.pkcs7.detached' => 'PKCS#7 detached',
        '/adbe.pkcs7.sha1' => 'PKCS#7 SHA-1 (veraltet)',
        '/ETSI.RFC3161' => 'Dokumentzeitstempel',
    ];
    $variantLabels = ['original' => 'Original', 'archive' => 'Archivfassung'];
    $pdfaStatus = [
        'compliant' => ['success', 'konform'],
        'noncompliant' => ['danger', 'nicht konform'],
        'unclaimed' => ['secondary', 'keine PDF/A-Kennung'],
        'notool' => ['warning', 'veraPDF nicht installiert'],
        'error' => ['danger', 'Prüfung fehlgeschlagen'],
        'disabled' => ['secondary', 'Prüfung abgeschaltet'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-center my-2">
    <small class="text-muted">
        @if ($inspection)
            Geprüft am {{ $when($inspection['at'] ?? null) }}
            @if (! empty($inspection['trust']))
                · Vertrauensanker: {{ implode(', ', $inspection['trust']) }}
            @endif
        @else
            Noch nicht geprüft
        @endif
    </small>
    <form method="POST" action="{{ route('files.inspect', $file) }}">
        @csrf
        @foreach (request()->except('tab') as $key => $value)
            @if (is_scalar($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <button type="submit" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-arrow-repeat"></i> {{ $inspection ? 'Neu prüfen' : 'Jetzt prüfen' }}
        </button>
    </form>
</div>

@if (! $inspection)
    <div class="alert alert-info">
        Für diese Datei liegt noch kein Prüfergebnis vor. Die Prüfung liest Eigenschaften, Signaturen und
        PDF/A-Konformität von Original und Archivfassung aus und dauert einige Sekunden.
    </div>
@else
    @foreach ($inspection['variants'] ?? [] as $key => $variant)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <strong><i class="bi bi-file-earmark-pdf"></i> {{ $variantLabels[$key] ?? $key }}</strong>
                <a href="{{ route('download', ['disk' => $variant['disk'], 'file' => $variant['path']]) }}" target="_blank"
                    class="small text-truncate ms-2" style="max-width: 60%">{{ basename($variant['path']) }}</a>
            </div>

            @if (($variant['status'] ?? null) === 'missing')
                <div class="card-body">
                    <div class="alert alert-danger mb-0">Datei nicht gefunden: {{ $variant['path'] }}</div>
                </div>
            @else
                @php
                    $props = $variant['properties'] ?? [];
                    $sigs = $variant['signatures'] ?? [];
                    $pdfa = $variant['pdfa'] ?? [];
                    $integrity = $variant['integrity'] ?? [];
                @endphp
                <ul class="list-group list-group-flush">

                    {{-- Signaturen ===================================================== --}}
                    <li class="list-group-item">
                        <h6 class="mb-2"><i class="bi bi-pen"></i> Signaturen</h6>
                        @switch($sigs['status'] ?? null)
                            @case('unsigned')
                                <span class="text-muted">Keine Signatur enthalten.</span>
                            @break

                            @case('signed')
                                <div class="small text-muted mb-2">
                                    {{ count($sigs['signatures']) }} Signatur(en)
                                    @if ($sigs['pades_level'] ?? null)
                                        · PAdES-Level {{ $sigs['pades_level'] }} (geschätzt)
                                    @endif
                                    · Prüfdaten (DSS) {{ ($sigs['dss'] ?? false) ? 'eingebettet' : 'nicht eingebettet' }}
                                    · Sperrprüfung {{ ($sigs['fetch'] ?? false) ? 'online' : 'nur eingebettete Daten' }}
                                </div>
                                @foreach ($sigs['signatures'] as $sig)
                                    @php
                                        $overall = SignatureVerdict::overall($sig);
                                        $signer = $sig['signer'] ?? [];
                                        $ts = $sig['timestamp'] ?? null;
                                    @endphp
                                    <div class="border rounded border-{{ $overall }} p-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <strong>
                                                <i class="bi {{ $icons[$overall] }} text-{{ $overall }}"></i>
                                                {{ $signer['common_name'] ?? $signer['organization'] ?? 'Unbekannter Unterzeichner' }}
                                            </strong>
                                            <small class="text-muted">
                                                {{ $sig['field'] }}
                                                · {{ $sigTypes[$sig['sub_filter'] ?? ''] ?? ($sig['sub_filter'] ?? '?') }}
                                                · {{ ($sig['visible'] ?? false) ? 'sichtbar' : 'unsichtbar' }}
                                            </small>
                                        </div>

                                        <table class="table table-sm table-borderless small mb-1 mt-1">
                                            @foreach (SignatureVerdict::aspects($sig) as $aspect)
                                                <tr>
                                                    <td style="width: 8.5rem">
                                                        <i class="bi {{ $icons[$aspect['level']] }} text-{{ $aspect['level'] }}"></i>
                                                        {{ $aspect['label'] }}
                                                    </td>
                                                    <td>{{ $aspect['text'] }}</td>
                                                </tr>
                                            @endforeach
                                        </table>

                                        <details class="small">
                                            <summary>Details</summary>
                                            <dl class="row mb-0 mt-1">
                                                <dt class="col-sm-4">Unterzeichner</dt>
                                                <dd class="col-sm-8">{{ $signer['subject'] ?? '–' }}</dd>
                                                <dt class="col-sm-4">Aussteller</dt>
                                                <dd class="col-sm-8">{{ $signer['issuer'] ?? '–' }}</dd>
                                                <dt class="col-sm-4">Zertifikat gültig</dt>
                                                <dd class="col-sm-8">{{ $when($signer['not_before'] ?? null) }} – {{ $when($signer['not_after'] ?? null) }}</dd>
                                                <dt class="col-sm-4">Seriennummer</dt>
                                                <dd class="col-sm-8 font-monospace text-break">{{ $signer['serial'] ?? '–' }}</dd>
                                                <dt class="col-sm-4">SHA-256</dt>
                                                <dd class="col-sm-8 font-monospace text-break">{{ $signer['sha256'] ?? '–' }}</dd>
                                                <dt class="col-sm-4">Schlüssel</dt>
                                                <dd class="col-sm-8">
                                                    {{ strtoupper($signer['key_algorithm'] ?? '?') }} {{ $signer['key_size'] ?? '' }} Bit
                                                    @if (! empty($signer['key_usage']))
                                                        · {{ implode(', ', $signer['key_usage']) }}
                                                    @endif
                                                </dd>
                                                <dt class="col-sm-4">Verfahren</dt>
                                                <dd class="col-sm-8">{{ $sig['mechanism'] ?? '–' }} ({{ $sig['md_algorithm'] ?? '?' }})</dd>
                                                <dt class="col-sm-4">Zeit laut Unterzeichner</dt>
                                                <dd class="col-sm-8">{{ $when($sig['signer_reported_time'] ?? null) }}</dd>
                                                @if ($ts)
                                                    <dt class="col-sm-4">Zeitstempel</dt>
                                                    <dd class="col-sm-8">
                                                        {{ $when($ts['time'] ?? null) }}
                                                        · {{ $ts['tsa']['subject'] ?? '' }}
                                                    </dd>
                                                @endif
                                                @foreach (['reason' => 'Grund', 'location' => 'Ort', 'contact' => 'Kontakt', 'name' => 'Name'] as $field => $label)
                                                    @if (! empty($sig[$field]))
                                                        <dt class="col-sm-4">{{ $label }}</dt>
                                                        <dd class="col-sm-8">{{ $sig[$field] }}</dd>
                                                    @endif
                                                @endforeach
                                                @if (! empty($sig['certification']))
                                                    <dt class="col-sm-4">Zertifizierung</dt>
                                                    <dd class="col-sm-8">erlaubt danach: {{ $sig['certification'] }}</dd>
                                                @endif
                                                <dt class="col-sm-4">Signierte Bytes</dt>
                                                <dd class="col-sm-8 font-monospace">
                                                    @php
                                                        $br = $sig['byte_range'] ?? [];
                                                    @endphp
                                                    @if (count($br) === 4)
                                                        [{{ $br[0] }}–{{ $br[0] + $br[1] }}], [{{ $br[2] }}–{{ $br[2] + $br[3] }}]
                                                    @endif
                                                </dd>
                                                <dt class="col-sm-4">Gesamturteil pyHanko</dt>
                                                <dd class="col-sm-8">{{ ($sig['bottom_line'] ?? false) ? 'gültig' : 'nicht bestätigt' }}</dd>
                                            </dl>
                                        </details>
                                    </div>
                                @endforeach
                                @if (! empty($sigs['trust_missing']))
                                    <div class="small text-warning">Vertrauensanker nicht lesbar: {{ implode(', ', $sigs['trust_missing']) }}</div>
                                @endif
                            @break

                            @default
                                <span class="text-danger">
                                    {{ ($sigs['status'] ?? null) === 'notool' ? 'pyHanko nicht verfügbar' : 'Prüfung fehlgeschlagen' }}:
                                    {{ $sigs['error'] ?? '' }}
                                </span>
                        @endswitch
                    </li>

                    {{-- PDF/A ========================================================= --}}
                    <li class="list-group-item">
                        @php
                            $validation = $pdfa['validation'] ?? [];
                            [$color, $text] = $pdfaStatus[$validation['status'] ?? 'error'] ?? ['secondary', $validation['status'] ?? '?'];
                        @endphp
                        <h6 class="mb-2"><i class="bi bi-archive"></i> PDF/A</h6>
                        Kennung: <strong>{{ $pdfa['claimed'] ? 'PDF/A-'.$pdfa['claimed'] : 'keine' }}</strong>
                        · veraPDF: <span class="badge text-bg-{{ $color }}">{{ $text }}</span>
                        @if (($validation['status'] ?? null) === 'noncompliant')
                            <span class="small text-muted">({{ $validation['failed_rules'] ?? '?' }} Regeln verletzt)</span>
                            <ul class="small mt-2 mb-0">
                                @foreach ($validation['rules'] ?? [] as $rule)
                                    <li>
                                        <strong>{{ $rule['clause'] ?? '' }}</strong>
                                        @if (($rule['checks'] ?? 0) > 1)
                                            <span class="text-muted">({{ $rule['checks'] }}×)</span>
                                        @endif
                                        {{ $rule['description'] ?? '' }}
                                        @if (! empty($rule['message']))
                                            <br><span class="text-muted">{{ $rule['message'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (! empty($validation['error']))
                            <div class="small text-danger">{{ $validation['error'] }}</div>
                        @endif
                    </li>

                    {{-- Integrität ===================================================== --}}
                    <li class="list-group-item">
                        <h6 class="mb-2"><i class="bi bi-fingerprint"></i> Prüfsumme</h6>
                        <div class="small font-monospace text-break">SHA-256 {{ $integrity['sha256'] ?? '–' }}</div>
                        @if (array_key_exists('ok', $integrity))
                            @if ($integrity['ok'])
                                <span class="badge text-bg-success">unverändert seit der Archivierung</span>
                            @else
                                <span class="badge text-bg-danger">weicht vom Wert bei der Archivierung ab</span>
                                <div class="small font-monospace text-break text-muted">erwartet {{ $integrity['expected'] }}</div>
                            @endif
                        @else
                            <span class="small text-muted">Kein Vergleichswert (noch nicht archiviert).</span>
                        @endif
                    </li>

                    {{-- Eigenschaften ================================================== --}}
                    <li class="list-group-item">
                        <h6 class="mb-2"><i class="bi bi-info-circle"></i> Eigenschaften</h6>
                        @if (($props['status'] ?? null) !== 'ok')
                            <span class="text-danger">{{ $props['error'] ?? 'nicht ermittelbar' }}</span>
                        @else
                            @php
                                $meta = $props['metadata'] ?? [];
                                $text = $props['text_layer'] ?? null;
                                $images = $props['images'] ?? null;
                                $fonts = $props['fonts'] ?? [];
                                $notEmbedded = collect($fonts)->where('embedded', false)->pluck('name')->unique();
                            @endphp
                            <dl class="row small mb-0">
                                @foreach (['title' => 'Titel', 'subject' => 'Betreff', 'author' => 'Autor', 'keywords' => 'Stichwörter', 'creator' => 'Erstellt mit', 'producer' => 'Producer'] as $field => $label)
                                    @if (! empty($meta[$field]))
                                        <dt class="col-sm-4">{{ $label }}</dt>
                                        <dd class="col-sm-8 text-break">{{ $meta[$field] }}</dd>
                                    @endif
                                @endforeach
                                <dt class="col-sm-4">Erstellt / geändert</dt>
                                <dd class="col-sm-8">{{ $when($meta['created'] ?? null) }} / {{ $when($meta['modified'] ?? null) }}</dd>
                                <dt class="col-sm-4">Seiten</dt>
                                <dd class="col-sm-8">{{ $props['pages'] ?? '–' }} · {{ $props['page_size'] ?? '' }}</dd>
                                <dt class="col-sm-4">PDF-Version / Größe</dt>
                                <dd class="col-sm-8">
                                    {{ $props['pdf_version'] ?? '–' }} ·
                                    {{ isset($props['file_size']) ? Number::fileSize($props['file_size'], 1) : '–' }}
                                    · {{ ($props['linearized'] ?? false) ? 'web-optimiert' : 'nicht web-optimiert' }}
                                    · {{ ($props['tagged'] ?? false) ? 'getaggt' : 'nicht getaggt' }}
                                </dd>
                                <dt class="col-sm-4">Textebene</dt>
                                <dd class="col-sm-8">
                                    @if ($text)
                                        @if ($text['present'])
                                            <span class="badge text-bg-success">vorhanden</span>
                                            <span class="text-muted">{{ $text['chars'] }} Zeichen auf den ersten {{ $text['pages'] }} Seiten</span>
                                        @else
                                            <span class="badge text-bg-warning">keine</span>
                                            <span class="text-muted">reiner Scan – OCR nötig</span>
                                        @endif
                                    @else
                                        –
                                    @endif
                                </dd>
                                @if ($images && $images['count'] > 0)
                                    <dt class="col-sm-4">Bilder</dt>
                                    <dd class="col-sm-8">
                                        {{ $images['count'] }} ·
                                        {{ $images['ppi_min'] === $images['ppi_max'] ? $images['ppi_min'] : $images['ppi_min'].'–'.$images['ppi_max'] }} dpi
                                        · {{ implode(', ', $images['encodings']) }}
                                        @if ($images['ppi_min'] < 150)
                                            <span class="badge text-bg-warning">geringe Auflösung</span>
                                        @endif
                                    </dd>
                                @endif
                                <dt class="col-sm-4">Schriften</dt>
                                <dd class="col-sm-8">
                                    {{ count($fonts) }}
                                    @if ($notEmbedded->isNotEmpty())
                                        <span class="badge text-bg-warning">nicht eingebettet: {{ $notEmbedded->implode(', ') }}</span>
                                    @elseif (count($fonts))
                                        <span class="text-muted">alle eingebettet</span>
                                    @endif
                                </dd>
                                @if (! empty($props['attachments']))
                                    <dt class="col-sm-4">Anhänge</dt>
                                    <dd class="col-sm-8">{{ implode(', ', $props['attachments']) }}</dd>
                                @endif
                                @if (! empty($props['xmp']['zugferd']))
                                    <dt class="col-sm-4">E-Rechnung</dt>
                                    <dd class="col-sm-8">
                                        ZUGFeRD/Factur-X {{ $props['xmp']['zugferd']['version'] ?? '' }}
                                        · Profil {{ $props['xmp']['zugferd']['level'] }}
                                    </dd>
                                @endif
                                @if (! empty($props['xmp']['pdfua']))
                                    <dt class="col-sm-4">Barrierefreiheit</dt>
                                    <dd class="col-sm-8">PDF/UA-{{ $props['xmp']['pdfua'] }} (Kennung)</dd>
                                @endif
                                @if (! empty($props['form']) || ($props['javascript'] ?? false) || ! empty($props['encryption']))
                                    <dt class="col-sm-4">Besonderheiten</dt>
                                    <dd class="col-sm-8">
                                        @if (! empty($props['form']))
                                            <span class="badge text-bg-info">Formular ({{ $props['form'] }})</span>
                                        @endif
                                        @if ($props['javascript'] ?? false)
                                            <span class="badge text-bg-danger">JavaScript</span>
                                        @endif
                                        @if (! empty($props['encryption']))
                                            <span class="badge text-bg-warning">verschlüsselt</span>
                                            @foreach ($props['encryption'] as $right => $allowed)
                                                @if (is_bool($allowed))
                                                    <span class="text-muted">{{ $right }}: {{ $yesNo($allowed) }}</span>
                                                @endif
                                            @endforeach
                                        @endif
                                    </dd>
                                @endif
                            </dl>
                        @endif
                    </li>
                </ul>
            @endif
        </div>
    @endforeach
@endif
