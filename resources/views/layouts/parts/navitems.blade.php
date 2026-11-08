{{-- <x-button.dispatch modalName="openModalFiles" class="navbar-toggler">
    <span class="bi bi-folder"></span>
</x-button.dispatch> --}}

{{-- <x-button.dispatch modalName="openModalUpload" class="navbar-toggler">
    <span class="bi bi-upload"></span>
</x-button.dispatch> --}}

{{-- <x-button.group class="btn-group-sm"> --}}

<li class="nav-item">
    <x-nav.popup :href="route('file-import')" tooltip="Fileimport">
        <i class="bi bi-hdd-network"></i>
    </x-nav.popup>
</li>

<li class="nav-item">
    <x-nav.popup :href="route('file-upload')" tooltip="Fileupload">
        <i class="bi bi-upload"></i>
    </x-nav.popup>
</li>

{{-- </x-button.group> --}}
