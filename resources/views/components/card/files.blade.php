<x-card>

    <x-slot name="headerLeft">

        <x-button.group>

            <x-button.dispatch modalName="openModalDisk" type="outline-dark" :data="[
                'switchTo' => 'update',
                'dokNR' => $item->NR,
            ]">
                <span class="bi bi-hdd-network"></span>
            </x-button.dispatch>

            <x-button.dispatch modalName="openModalFiles" type="outline-dark" :data="[
                'switchTo' => 'update',
                'dokNR' => $item->NR,
            ]">
                <span class="bi bi-folder"></span>
            </x-button.dispatch>

            <x-button.dispatch modalName="openModalUpload" type="outline-dark" :data="[
                'switchTo' => 'update',
                'dokNR' => $item->NR,
            ]">
                <span class="bi bi-upload"></span>
            </x-button.dispatch>

            <x-button.dispatch modalName="openModalMailbox" type="outline-dark" :data="[
                'switchTo' => 'update',
                'dokNR' => $item->NR,
            ]">
                <span class="bi bi-mailbox"></span>
            </x-button.dispatch>

        </x-button.group>

    </x-slot>

    <x-slot name="headerRight">
        @if (Route::has('names.index'))
            <x-button.link class="btn-sm" type='outline-primary' :href="route('upload.index', [
                // 'model' => 'App\\Models\\Names\\Name',
                'model' => $item->getMorphClass(),
                'modid' => $item->id,
            ])">
                <i class="bi bi-plus"></i>
            </x-button.link>
        @endif

        @if (Route::has('names.indexx'))
            <x-button.link class="btn-sm" type='outline-secondary' :href="route('names.upload.index', $item->id)">
                <i class="bi bi-plus"></i>
            </x-button.link>
        @endif

    </x-slot>

    @php
        // dump($item);
        // dump($item->files());
        // dump($item->files()->count());
        // dump($item->files()->get());
    @endphp

    <x-table :acc_id="$item->id">

        @forelse ($item->files()->get() as $file)
            <x-table.row :acc_id="$item->id" :row_id="$file->id" :class="$item->state->color()">

                {{-- <x-table.row.line1 :acc_id="$item->id" :row_id="$file->id"> --}}
                <x-table.cell>
                    {{-- <x-file.href :$file /> --}}
                    {{-- {{ $file('button') }} --}}

                    <a class="btn btn-primary btn-sm" role="button"
                        href="{{ route('download', [
                            'disk' => $file->disk,
                            'file' => $file->file,
                            // 'file' => $file->getPathToFile(),
                        ]) }}"
                        target="_blank">{{ $file->type }}</a>

                </x-table.cell>
                <td><span class="inline-block text-truncate" style="max-width: 150px;">{{ $file->name }}</span></td>
                {{-- <x-table.cell>{{ Str::limit($file->name, 25) }}</x-table.cell> --}}
                <x-table.cell>{{ $file->human_readable_size }}</x-table.cell>

                <x-slot:row2>
                    <x-table.cell cols="2">

                        x-table.cell.line2 {{ $file->id }}

                        @php
                            $url = Storage::url('file.jpg');

                            
                            // $url = Storage::disk($file->disk)->url($file->getPathToFile());
                            dump($url);
                        @endphp

                        <img src="{{ $url }}" alt="{{ $file->name }}" />

                        @php
                            dump($file);
                        @endphp


                        {{-- {{ $file->getUrl('thumb') }} --}}

                        {{-- <a class="btn btn-default" role="button" target="_blank"
                                                href="{{ route('download', [
                                                    'disk' => $file['disk'],
                                                    'file' => $file['file'],
                                                ]) }}">{{ $file['name'] }}</a> --}}

                        <br>
                        {{-- {{ $file('button') }} --}}
                        {{-- {{ $file('old-picture') }} --}}
                        {{-- {{ $file('thumb-cropped') }} --}}
                        {{-- {{ $file('preview') }} --}}

                        {{-- <img src="{{ $file->getUrl('thumb') }}" class="img-fluid" alt=""> --}}
                        {{-- <img src="{{ $file->getUrl('thumb_small') }}" class="img-fluid" alt=""> --}}

                    </x-table.cell>
                </x-slot:row2>

                {{-- </x-table.row.line1> --}}
            </x-table.row>

            {{-- <x-table.row.line2 :acc_id="$item->id" :row_id="$file->id"></x-table.row.line2> --}}

        @empty
            ---
        @endforelse

    </x-table>

    <hr>

</x-card>
