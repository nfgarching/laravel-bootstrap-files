<!-- Items -->

<li class="btn-group nav-item w-100">
    <x-nav.link :href="route('disks.index')">
        {{ __('Storage') }}
    </x-nav.link>
    <a class="nav-link dropdown-toggle ms-auto" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
        aria-label="Menü Anrufe"></a>
    <ul class="dropdown-menu dropdown-menu-end">

        @if (Route::has('disks.index'))
            <li class="dropdown-item">
                <x-nav.link :href="route('disks.index')">
                    {{ __('Disks') }}
                </x-nav.link>
            </li>
        @endif
        @if (Route::has('files.index'))
            <li class="dropdown-item">
                <x-nav.link :href="route('files.index')">
                    {{ __('Files') }}
                </x-nav.link>
            </li>
        @endif
        @if (Route::has('upload.index'))
            {{-- <li class="dropdown-item">
                <x-nav.link :href="route('file-upload')" target="_blank">
                    {{ __('Upload #1') }}
                </x-nav.link>
            </li> --}}

            {{-- <li class="dropdown-item">
                <button
                    type="button" class="nav-link" x-data
                    @click="window.open('{{ route('file-upload') }}', '_blank');"
                >
                    {{ __('Upload #2') }}
                </button>
            </li> --}}

            <li class="dropdown-item">
                <button type="button" class="nav-link" x-data
                    @click="window.open(
                        '{{ route('file-import') }}', 
                        '_blank','width=800,height=800'
                    )">
                    {{ __('Import') }}
                </button>
            </li>

            <li class="dropdown-item">
                <x-nav.link :href="route('file-import')" :data="[
                    'model_type' => 'Nfgarching\Documents\Models\Document',
                    'model_id' => $model_id ?? '',
                ]">
                    {{ __('Import Document') }}
                </x-nav.link>
            </li>

            <li class="dropdown-item">
                <x-nav.link :href="route('file-import')" :data="[
                    'model_type' => 'App\Models\Dokument',
                    'model_id' => $model_id ?? '',
                ]">
                    {{ __('Import Dokument') }}
                </x-nav.link>
            </li>

            <li class="dropdown-item">
                <button type="button" class="nav-link" x-data
                    @click="window.open(
                        '{{ route('file-upload') }}', 
                        '_blank','width=800,height=800'
                    )">
                    {{ __('Upload') }}
                </button>
            </li>

            <li class="dropdown-item">
                <x-nav.link :href="route('file-upload')" :data="[
                    'model_type' => 'Nfgarching\Documents\Models\Document',
                    'model_id' => $model_id ?? '',
                ]">
                    {{ __('Upload Document') }}
                </x-nav.link>
            </li>

            <li class="dropdown-item">
                <x-nav.link :href="route('file-upload')" :data="[
                    'model_type' => 'App\Models\Dokument',
                    'model_id' => $model_id ?? '',
                ]">
                    {{ __('Upload Dokument') }}
                </x-nav.link>
            </li>

            {{-- <li class="dropdown-item">
                <x-button.dispatch href="route('file-import')" :data="[
                    'model_type' => 'App\Models\Dokument',
                    'model_id' => $model_id ?? '',
                    'data' => [
                        'title' => 'Datenimport',
                        'Betreff' => 'Telefonanruf: ',
                        'Typ' => '44007',
                    ],
                ]">
                    <i class="bi bi-hdd-network"></i>
                </x-button.dispatch>
            </li> --}}
        @endif
    </ul>
</li>
