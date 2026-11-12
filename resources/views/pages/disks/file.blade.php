<x-app-layout>

    <div class="row">

        <div class="col-xl-6">

            <x-card>

                <x-slot name="headerLeft">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm" href="{{ old(' url', url()->previous()) }}">
                                <i class="bi bi-arrow-left-square"></i> &nbsp; Zurück
                            </a>
                        </li>
                        {{-- <li class="nav-item">
                            <a class="btn btn-primary btn-sm" href="{{ route('disk.index') }}">Home</a>
                        </li> --}}

                        {{-- @if ($updir)
                            <li class="nav-item">
                                <a class="btn btn-secondary btn-sm"
                                    href="{{ route('disk.index') }}/?path={{ $updir }}" type="button">
                                    {{ $updir }}
                                </a>
                            </li>
                        @endif --}}
                    </ul>
                </x-slot>

                <x-slot name="headerRight">
                </x-slot>

                <x-file.info :disk="$disk" :file="$file" />

            </x-card>

        </div>

        <div class="col-xl-6">

            <embed src="{{ route('download', ['disk' => $disk, 'file' => $file]) }}"
                type="{{ Storage::disk($disk)->mimeType($file) }}" width="100%" height="1000px">

        </div>

    </div>

</x-app-layout>
