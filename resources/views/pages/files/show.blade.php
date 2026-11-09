<x-app-layout>

    <x-slot name="header">
        <div class="row">
            <div class="col-8">
                <div class="btn-group text-truncate" role="group">
                    <x-button.link :href="route('files.index', $request->all())" type="outline-secondary" tooltip="Zurück zur Liste">
                        <i class="bi bi-arrow-left-circle"></i>
                    </x-button.link>
                    <x-button.clipboard class="ms-1 text-truncate" :text="$file->name" style="max-width: 800px;">
                        {{-- <i class="bi bi-{{ $item->get_btn_icon() }}"></i> --}}
                        &nbsp;
                        {{ Str::limit($file->name, 250) }}
                    </x-button.clipboard>
                </div>
            </div>
            <div class="col-4 d-flex justify-content-end" role="group">
                <x-nav.buttons routeName="files.show" :$navigation :$request>
                    <x-button.raw :item="$file" />
                </x-nav.buttons>
            </div>
        </div>
        <hr>

        <div class="container">
            <div class="btn-group btn-group-sm">
                <a class="nav-link" href="{{ route('download', "disk=$file->disk&file=$file->file") }}" target="_blank">
                    <i class="bi bi-box-arrow-in-up-right"></i>
                    {{ $file->name }}
                </a>
            </div>
            <div class="btn-group btn-group-sm float-end">
                <small>(ID:{{ $file->id }} - {{ date('H:i:s') }})</small>
            </div>
        </div>
        <hr>

    </x-slot>

    <div class="row">

        <div class="col-xl-6">
            @php
                $show = 1;
                $tabs = ['<i class=\'bi bi-house\'></i>', 'Multi', 'Test'];
                $token = rand();
            @endphp
            <x-nav.tabs :$tabs :$token :$show>
                <x-nav.item tab="0" :$token :$show>{{-- Home --}}

                    @if ($file->type === \Nfgarching\Files\Enums\FileType::Pdf)
                        @php
                            $archive = $file->archive();
                            $original = $archive['original'] ?? [];
                        @endphp
                        <x-card>
                            <x-slot name="headerLeft">Archiv</x-slot>
                            <x-slot name="headerRight">
                                <a class="btn btn-sm {{ $file->isOriginalDocument() ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    href="{{ route('files.action', ['id' => $file->id, 'switchTo' => 'ORIGINAL', 'value' => $file->isOriginalDocument() ? 0 : 1]) }}"
                                    title="{{ $file->isOriginalDocument() ? 'Kennzeichnung entfernen (Archivfassung ersetzt das Original)' : 'Als Original Dokument kennzeichnen (Original bleibt Hauptdatei bzw. wird aus dem Papierkorb zurückgeholt)' }}">
                                    <i class="bi bi-{{ $file->isOriginalDocument() ? 'toggle-on' : 'toggle-off' }}"></i>
                                    Original Dokument
                                </a>
                            </x-slot>
                            <dl class="row small mb-0">
                                <dt class="col-sm-4">Archivfassung</dt>
                                <dd class="col-sm-8">
                                    {{ $archive['status'] ?? '–' }}
                                    @if ($archive)
                                        · PDF/A: {{ $archive['validation']['status'] ?? $archive['pdfa']['status'] ?? '–' }}
                                        · Siegel: {{ $archive['signature']['status'] ?? '–' }}
                                    @endif
                                </dd>
                                @if ($file->originalDocument()['producer'] ?? null)
                                    <dt class="col-sm-4">Erstellt mit</dt>
                                    <dd class="col-sm-8">{{ $file->originalDocument()['producer'] }}</dd>
                                @endif
                                <dt class="col-sm-4">Original</dt>
                                <dd class="col-sm-8">
                                    @if ($file->hasTrashedOriginal())
                                        im Papierkorb bis {{ \Illuminate\Support\Carbon::parse($original['purge_after'])->format('d.m.Y') }}
                                        <a href="{{ route('download', ['disk' => $original['disk'], 'file' => $original['trash_path']]) }}" target="_blank">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    @elseif (! empty($original['purged_at']))
                                        endgültig gelöscht am {{ \Illuminate\Support\Carbon::parse($original['purged_at'])->format('d.m.Y') }}
                                    @else
                                        Hauptdatei
                                    @endif
                                </dd>
                            </dl>
                        </x-card>
                    @endif

                    <x-card>
                        <x-slot name="header"></x-slot>
                        <x-desc.list :item="$file->toArray()" />
                    </x-card>

                </x-nav.item>
                <x-nav.item tab="1" :$token :$show>{{-- Multi --}}

                    <x-card>
                        <x-slot name="headerLeft">
                            <x-button.group>
                                <x-button.model :model="$file->model_type" :modid="$file->model_id" />
                            </x-button.group>
                        </x-slot>
                        <x-slot name="headerRight">
                            <x-chatbot :data="[
                                'model_type' => $file::class,
                                'model_id' => $file->id,
                            ]" />
                        </x-slot>
                        <textarea class="form-control form-control-sm bg-light" rows="23" readonly>{{ $file->propsText() }}</textarea>
                    </x-card>

                </x-nav.item>
                <x-nav.item tab="2" :$token :$show>{{-- Test --}}

                    <x-show.info :item="$file" />

                    <x-card>

                        <x-slot name="headerLeft">Testing</x-slot>
                        <x-slot name="headerRight">
                            <x-chatbot :data="[
                                'model_type' => $file::class,
                                'model_id' => $file->id,
                            ]" />
                            <x-button.raw :item="$file" />
                            {{-- <x-button.raw :$item /> --}}
                        </x-slot>

                        <x-form :action="route('files.action', $file->id)"></x-form>

                        <x-button.group>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'switchTo' => 'IMG_CONVERSION',
                            ])" target="_blank">
                                IMG Conversion ausführen
                            </x-button.link>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'switchTo' => 'OCR_EXECUTE',
                            ])" target="_blank">
                                OCR-Texterkennung ausführen
                            </x-button.link>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'switchTo' => 'PDF_CONVERSION',
                            ])" target="_blank">
                                PDF Conversion ausführen
                            </x-button.link>

                        </x-button.group>

                        <hr>

                        <x-button.group>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'model_type' => 'Nfgarching\Documents\Models\Document',
                                'switchTo' => 'TestingCreateDocument',
                            ])" target="_blank">
                                Create Document
                            </x-button.link>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'model_type' => 'App\Models\Dokument',
                                'switchTo' => 'TestingCreateDokument',
                            ])" target="_blank">
                                Create Dokument
                            </x-button.link>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'switchTo' => 'ProcessFile',
                            ])" target="_blank">
                                Process File
                            </x-button.link>

                            <x-button.link :href="route('files.action', [
                                'id' => $file->id,
                                'model_type' => $file->model_type,
                                'model_id' => $file->model_id,
                                'switchTo' => 'TestingUpdate',
                            ])" target="_blank">
                                Update Model {{ $file->model_type }} (ID: {{ $file->model_id }})
                            </x-button.link>

                        </x-button.group>

                    </x-card>

                </x-nav.item>
            </x-nav.tabs>
        </div>

        <div class="col-xl-6">

            @php
                $show = $request->query('tab') === 'pdf' ? 3 : 2;
                $tabs = ['INFO', 'Props', 'SHOW', 'PDF', 'Test'];
                $token = rand();
            @endphp
            <x-nav.tabs :$tabs :$token :$show>
                <x-nav.item tab="0" :$token :$show>{{-- INFO --}}

                    <x-card>
                        <x-slot name="header"></x-slot>
                        <x-file.info :disk="$file->disk" :file="$file->file" />
                    </x-card>

                </x-nav.item>
                <x-nav.item tab="1" :$token :$show>{{-- Props --}}

                    @if ($file->generated_conversions || $file->custom_properties)
                        <x-desc.list :item="[
                            'generated_conversions' => $file->generated_conversions ?? [],
                            'custom_properties' => $file->custom_properties ?? [],
                        ]" />
                    @else
                        <br>
                        --- No Data ---
                    @endif

                </x-nav.item>
                <x-nav.item tab="2" :$token :$show>{{-- SHOW --}}

                    @if (Storage::disk($file->disk)->exists($file->file))
                        @switch($file->type->value)
                            @case('image')
                                <p>img-fluid</p>
                                @php
                                    // HEIC/HEIF: JPEG-Fassung aus ConversionImg anzeigen (Browser können HEIC nicht darstellen)
                                    $imgDisk = $file->disk;
                                    $imgPath = $file->file;
                                    if (isset($file->generated_conversions['display'])) {
                                        $imgDisk = $file->conversions_disk ?? $file->disk;
                                        $imgPath = $file->generated_conversions['display'];
                                    }
                                @endphp
                                <img src="{{ route('download', ['disk' => $imgDisk, 'file' => $imgPath]) }}" class="img-fluid"
                                    alt="{{ $file->name }}">
                                {{-- <img src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                    type="{{ $file->mime }}" width="100%" height="1000px"> --}}
                            @break

                            @case('pdf')
                                {{-- <p>File im iFrame</p> --}}

                                {{-- <div class="embed-responsive embed-responsive-1by1">
                                    <iframe class="embed-responsive-item"
                                        src="{{ route('download', "disk=$file->disk&file={$file->file}") }}" allowfullscreen
                                        width="100%" height='100%'></iframe>
                                </div> --}}

                                {{-- ...
                                <p>File embed</p>
                                <embed src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                    type="{{ $file->mime }}" width="100%" height="1000px"> --}}
                                {{-- ...
                                <p>File object</p>
                                <object data="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                    type="{{ $file->mime }}" width="100%" height="1000px"></object> --}}

                                <iframe src="{{ route('download', "disk=$file->disk&file={$file->file}") }}#zoom=FitH"
                                    height="575px" width="100%"></iframe>

                                {{-- <!-- 21:9 aspect ratio -->
                                <div class="embed-responsive embed-responsive-21by9">
                                    <iframe class="embed-responsive-item"
                                        src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                        width="100%"></iframe>
                                </div>

                                <!-- 16:9 aspect ratio -->
                                <div class="embed-responsive embed-responsive-9by16">
                                    <iframe class="embed-responsive-item"
                                        src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                        width="100%"></iframe>
                                </div>

                                <!-- 4:3 aspect ratio -->
                                <div class="embed-responsive embed-responsive-4by3">
                                    <iframe class="embed-responsive-item"
                                        src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                        width="100%"></iframe>
                                </div>

                                <!-- 1:1 aspect ratio -->
                                <div class="embed-responsive embed-responsive-1by1">
                                    <iframe class="embed-responsive-item"
                                        src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                        width="100%"></iframe>
                                </div> --}}
                            @break

                            @case('video')
                                <p>Video im Video-Tag</p>
                                <video width="100%" height="1000px" controls>
                                    <source src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                        type="{{ $file->mime }}">
                                    Your browser does not support the video tag.
                                </video>
                                ...
                                <p>Video embed</p>
                                <embed src="{{ route('download', "disk=$file->disk&file={$file->file}") }}"
                                    type="{{ $file->mime }}" width="100%" height="1000px">
                            @break

                            @default
                                <p>Sontiges File: {{ $file->type }} </p>
                        @endswitch
                    @else
                        <br>
                        <div class="alert alert-danger" role="alert">
                            File "{{ $file->name }}" ({{ $file->file }}) not found on Storage
                            {{ $file->disk }}
                        </div>
                    @endif

                </x-nav.item>
                <x-nav.item tab="3" :$token :$show>{{-- PDF --}}

                    @if ($file->type->value === 'pdf')
                        @include('files::show.pdf')
                    @else
                        <br>
                        --- Kein PDF ---
                    @endif

                </x-nav.item>
                <x-nav.item tab="4" :$token :$show>{{-- Test --}}

                    Test

                </x-nav.item>
            </x-nav.tabs>
        </div>
    </div>

</x-app-layout>
