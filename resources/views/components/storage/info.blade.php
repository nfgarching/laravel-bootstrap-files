@props(['disk', 'file'])

{{-- {{ Storage::disk($disk)->path($file) }} --}}

@if (Storage::disk($disk)->exists($file))

    <dl class="row small">
        <dt class="col-3 text-truncate">Dateiname</dt>
        <dl class="col-9 text-truncate">
            {{-- {{ basename(Storage::path($root)) }} --}}
            <a class="nav-link" href="{{ route('download', "disk=$disk&file=$file") }}" target="_blank">
                <i class="bi bi-box-arrow-in-up-right"></i>
                {{ basename($file) }}
            </a>
        </dl>
        <dt class="col-3 text-truncate">Dateidisk</dt>
        <dl class="col-9 text-truncate">
            {{ $disk }}
        </dl>
        <dt class="col-3 text-truncate">Dateidriver</dt>
        <dl class="col-9 text-truncate">
            {{ config("filesystems.disks.$disk.driver") }}
        </dl>

        <hr>

        <dt class="col-3 text-truncate">Dateidatum</dt>
        <dl class="col-9 text-truncate">
            {{ date('d.m.Y H:i:s', Storage::disk($disk)->lastModified($file)) }}
        </dl>
        <dt class="col-3 text-truncate">Dateigröße</dt>
        <dl class="col-9 text-truncate">
            {{ Number::fileSize(Storage::disk($disk)->size($file)) }}
        </dl>

        <dt class="col-3 text-truncate">mimeType</dt>
        <dl class="col-9 text-truncate">
            {{ Storage::disk($disk)->mimeType($file) }}
        </dl>

        <hr>

        <dt class="col-3 text-truncate">Absoluter Pfad</dt>
        <dl class="col-9 text-truncate">
            {{ Storage::disk($disk)->path($file) }}
        </dl>
        <dt class="col-3 text-truncate">Relativer Pfad</dt>
        <dl class="col-9 text-truncate">
            {{ $file }}
        </dl>

        <hr>

        @if (config("filesystems.disks.$disk.driver") == 'local')
            <dt class="col-3 text-truncate">Letzte Änderung</dt>
            <dl class="col-9 text-truncate">
                {{ date('d.m.Y H:i:s', filectime(Storage::disk($disk)->path($file))) }}
            </dl>
            <dt class="col-3 text-truncate">Letzter Zugriff</dt>
            <dl class="col-9 text-truncate">
                {{ date('d.m.Y H:i:s', fileatime(Storage::disk($disk)->path($file))) }}
            </dl>
            <hr>
            <dt class="col-3 text-truncate">Hashwert</dt>
            <dl class="col-9 text-truncate">
                {{ hash_file('md5', Storage::disk($disk)->path($file)) }}
            </dl>
            <hr>
            <dt class="col-3 text-truncate">filegroup</dt>
            <dl class="col-9 text-truncate">
                {{ filegroup(Storage::disk($disk)->path($file)) }}
            </dl>
            <dt class="col-3 text-truncate">fileinode</dt>
            <dl class="col-9 text-truncate">
                {{ fileinode(Storage::disk($disk)->path($file)) }}
            </dl>
            <dt class="col-3 text-truncate">fileowner</dt>
            <dl class="col-9 text-truncate">
                {{ fileowner(Storage::disk($disk)->path($file)) }}
            </dl>
            <dt class="col-3 text-truncate">fileperms</dt>
            <dl class="col-9 text-truncate">
                {{ fileperms(Storage::disk($disk)->path($file)) }}
            </dl>
            <dt class="col-3 text-truncate">filesize</dt>
            <dl class="col-9 text-truncate">
                {{ filesize(Storage::disk($disk)->path($file)) }}
            </dl>
            <dt class="col-3 text-truncate">filetype</dt>
            <dl class="col-9 text-truncate">
                {{ filetype(Storage::disk($disk)->path($file)) }}
            </dl>
        @endif

    </dl>

@else
    No File found
@endif
