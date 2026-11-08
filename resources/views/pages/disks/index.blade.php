<x-app-layout>

    <x-slot name="header">
        <div class="container">
            <div class="btn-group btn-group-lg">
                <x-button type="light">
                    {{ Str::limit('Disks', 150) }}
                </x-button>
            </div>
            <div class="btn-group btn-group-sm float-end">
                @if (Route::has('files.index'))
                    <x-button.link class="btn-sm" type='outline-primary' :href="route('files.index')">
                        Files
                    </x-button.link>
                @endif
            </div>
        </div>
        <hr>
    </x-slot>

    <div class="container">
        <div class="row">

            <div class="col-xl-6">
                <ul>
                    @forelse ($disks as $name => $disk)
                        <li><a href="{{ url()->current() . '/' . $name }}">{{ $name }}</a></li>

                        @php
                            // echo $name;
                            // dump($disk);
                        @endphp

                    @empty
                        <td colspan="4">No Data found</td>
                    @endforelse
                </ul>
            </div>

            <div class="col-xl-6">

                ...
                @php

                    dump(config('filesystems'));

                @endphp


            </div>

        </div>
    </div>

</x-app-layout>
