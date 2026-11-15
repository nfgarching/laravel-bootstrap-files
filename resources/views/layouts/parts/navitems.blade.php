{{-- <x-button.dispatch modalName="openModalFiles" class="navbar-toggler">
    <span class="bi bi-folder"></span>
</x-button.dispatch> --}}

{{-- <x-button.dispatch modalName="openModalUpload" class="navbar-toggler">
    <span class="bi bi-upload"></span>
</x-button.dispatch> --}}

{{-- <x-button.group class="btn-group-sm"> --}}

{{-- Klick => iframe-Modal (xl), SHIFT => Popup, CTRL => Tab --}}

<li class="nav-item">
    <x-nav.popup frame frameSize="xl" :href="route('file-import')" tooltip="Fileimport">
        <i class="bi bi-hdd-network"></i>
    </x-nav.popup>
</li>

<li class="nav-item">
    <x-nav.popup frame frameSize="lg" :href="route('file-upload')" tooltip="Fileupload">
        <i class="bi bi-upload"></i>
    </x-nav.popup>
</li>

{{-- </x-button.group> --}}
