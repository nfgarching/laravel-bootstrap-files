<div>

    <x-card>

        @include('core::listing.card.headerLeft')

        <x-slot name="headerRight">
            @if ($rows->isNotEmpty())
                <x-button.chatbot :data="[
                    'file_ids' => $fileIds,
                    'model_type' => $rows->first()->model_type,
                    'model_id' => $rows->first()->model_id,
                ]" />
            @endif
        </x-slot>

        @include('core::listing.select.header')

        @php
            $token = rand();
            $toggleCollapse = false;
            $toggleChecks = false;
            $toggleRadios = false;
        @endphp
        <x-table :acc_id="$token" class="table-hover table-sm" :$toggleChecks>
            {{-- <x-table class="table" accordion="{{ $item->NR }}"> --}}

            @forelse ($rows as $file)
                @if ($loop->first)
                    <x-slot:header>
                        <th width="10%"></th>
                        <th width="85%"></th>
                        <th width="05%"></th>
                    </x-slot:header>
                @endif

                <x-table.row.line1 :acc_id="$token" :row_id="$file->id" :$toggleCollapse :$toggleChecks :$toggleRadios>
                    <td nowrap>
                        <x-button.filelink :file="$file" :preview="true" :thumb="true" />
                    </td>
                    <td>
                        <x-button class="text-start" data-bs-toggle="collapse"
                            data-bs-target="#collapse{{ $token . $file->id }}" type="light">
                            {{ Str::limit($file->name, 150) }}
                        </x-button>
                    </td>
                    <td nowrap>
                        {{ Number::fileSize($file->size) }}
                    </td>
                </x-table.row.line1>

                <x-table.row.line2 :acc_id="$token" :row_id="$file->id">

                    <x-table.cell cols="1"></x-table.cell>

                    <x-table.cell cols="1">

                        desc ...

                        <hr>

                        <x-button.group>
                            <x-chatbot :data="[
                                'model_type' => $file::class,
                                'model_id' => $file->id,
                            ]" />
                            <x-button.link :href="route('files.show', $file)" type="outline-secondary">
                                <i class="bi bi-info-square"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.edit', $file)" type="outline-primary">
                                <i class="bi bi-pencil-square"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.show', $file)" type="outline-secondary">
                                <i class="bi bi-eye"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.show', $file)" type="outline-success">
                                <i class="bi bi-download"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.show', $file)" type="outline-info">
                                <i class="bi bi-printer"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.show', $file)" type="outline-warning">
                                <i class="bi bi-envelope"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.show', $file)" type="outline-secondary">
                                <i class="bi bi-files"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.show', $file)" type="outline-secondary">
                                <i class="bi bi-arrows-move"></i>
                            </x-button.link>
                            <x-button.link :href="route('files.destroy', $file)" type="outline-danger"
                                onclick="return confirm('Are you sure?')">
                                <i class="bi bi-trash"></i>
                            </x-button.link>
                        </x-button.group>

                    </x-table.cell>

                </x-table.row.line2>

                @if ($loop->last && $rows->lastpage() > 1)
                    @include('core::listing.table.footer')
                @endif

            @empty
                @include('core::listing.nodata')
            @endforelse

        </x-table>

    </x-card>

    <x-counter startTime="{{ $startTime }}" />

</div>
