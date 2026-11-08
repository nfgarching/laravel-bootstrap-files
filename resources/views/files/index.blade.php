<x-app-layout>

    <x-slot name="header">
        <div class="container">

            <div class="btn-group btn-group-sm">
                <h4>{{ __('Files') }}</h4>
            </div>

            <div class="btn-group btn-group-sm float-end">
                @if (Route::has('disks.index'))
                    <x-button.link class="btn-sm" type='outline-primary' :href="route('disks.index')">
                        Disks
                    </x-button.link>
                @endif
                {{-- @if (Route::has('disks.index'))
                    <x-button.link class="btn-sm" type='outline-primary' :href="route('upload.index')">
                        Upload
                    </x-button.link>
                @endif --}}
            </div>

        </div>
        <hr>
    </x-slot>

    <x-card>

        <x-slot name="headerLeft">
            {{-- <x-table.filter> --}}

            {{-- <x-table.filter.search field="search" table="{{ $files[0] ? $files[0]->getTable() : '' }}"
                    :oldval="$request->search" /> --}}

            {{-- <x-table.filter.select field="disk" :options="$grpDisk"
                    table="{{ $files[0] ? $files[0]->getTable() : '' }}" :oldval="$request->disk" /> --}}

            {{-- <x-table.filter.select field="status" :options="$grpStatus"
                    table="{{ $files[0] ? $files[0]->getTable() : '' }}" :oldval="$request->status" /> --}}

            {{-- <x-table.filter.select field="type" :options="$grpType"
                    table="{{ $files[0] ? $files[0]->getTable() : '' }}" :oldval="$request->type" /> --}}

            {{-- <x-table.filter.select field="typ" :options="$grpTyp"
                    table="{{ $files[0] ? $files[0]->getTable() : '' }}" :oldval="$request->typ" /> --}}

            {{-- </x-table.filter> --}}
        </x-slot>

        <x-slot name="headerRight">
            <x-button.group>
                {{-- <x-button.create class="navbar-toggler" type="primary" route="upload.create" /> --}}
                {{-- <x-button.create class="navbar-toggler" type="primary" route="disk-inbox.create" /> --}}

                {{-- <x-button.dispatch {{ $attributes->merge(['class' => $class]) }} 
            X_modalName="openModalUpload" :href="route('disk-inbox.index')" 
            :data="[
                'model_type' => 'App\Models\Dokument',
                'model_id' => $model_id ?? '',
            ]" :type="$type">
            @if ($slot != '')
                {{ $slot }}
            @else
                <i class="bi bi-hdd-network"></i>
            @endif
        </x-button.dispatch> --}}

                <x-button.dispatch X_modalName="openModalUpload" :href="route('upload.index')">
                    <i class="bi bi-upload"></i>
                </x-button.dispatch>


            </x-button.group>
        </x-slot>

        {{-- <x-form> --}}

        <x-form :action="route('files.store')">

            <x-table class="table-hover table-responsive table-sm">

                @forelse ($files as $file)
                    @if ($loop->iteration == 1)
                        <x-slot:head>
                            <x-table.head.toggle />
                            <th></th>
                            <th></th>
                            <x-table.head.sort field="disk" />
                            <x-table.head.sort field="name" label="Dateiname" />
                            <x-table.head.sort field="status" />
                            <x-table.head.sort field="type" />
                            <x-table.head.sort field="typ" />
                            <x-table.head.sort field="size" />
                        </x-slot:head>
                    @endif

                    <x-table.row.line1 :id="$file->id">

                        <td>
                            <x-button.file-link :file="$file" :href="route('files.edit', $file)" />
                        </td>
                        <td nowrap>
                            <x-button.model :model="$file->model_type" :modid="$file->model_id" />
                            {{-- <x-button.morph model="App\Models\File" :modid="$file->id" table="" /> --}}
                            <x-button.props model="Nfgarching\Files\Models\File" :modid="$file->id" />
                        </td>
                        <td align="center">
                            <a class="btn btn-primary btn-sm" role="button"
                                href="{{ request()->fullUrlWithQuery(array_merge(request()->all(), ['page' => '1', 'disk' => $file->disk])) }}">
                                {{ $file->disk }}
                            </a>
                        </td>


                        <td width="60%">
                            {{ $file->name }}
                        </td>
                        <td align="center">
                            <a class="btn btn-primary btn-sm" role="button"
                                href="{{ request()->fullUrlWithQuery(array_merge(request()->all(), ['page' => '1', 'status' => $file->status])) }}">
                                {{ $file->status }}
                            </a>
                        </td>
                        <td align="center">
                            <a class="btn btn-primary btn-sm" role="button"
                                href="{{ request()->fullUrlWithQuery(array_merge(request()->all(), ['page' => '1', 'type' => $file->type])) }}">
                                {{ $file->type }}
                            </a>
                        </td>
                        <td align="center">
                            <a class="btn btn-primary btn-sm" role="button"
                                href="{{ request()->fullUrlWithQuery(array_merge(request()->all(), ['page' => '1', 'typ' => $file->typ])) }}">
                                {{ $file->typ }}
                            </a>
                        </td>
                        <td align="right">{{ $file->formatBytes() }}</td>

                    </x-table.row.line1>

                    <x-table.row.line2 :id="$file->id">

                        <td colspan="1">
                        <td colspan="7">

                            <x-desc.list :item="$file->toArray()" />

                        </td>

                    </x-table.row.line2>

                @empty
                    <tr>
                        <td colspan="4">No Data found</td>
                    </tr>
                @endforelse

                <x-table.row>
                    <td colspan="10">
                        <x-table.check />
                    </td>
                </x-table.row>

            </x-table>

            <div class="btn-group">
                <div class="input-group">
                    <button class="btn btn-light" onclick="return confirm('Auswahl ändern?')" type="submit"
                        value="change" name="switchTo">
                        Ändern
                    </button>
                    {{-- <x-table.filter.select field="status" table="{{ $files[0] ? $files[0]->getTable() : '' }}" /> --}}
                    {{-- <x-table.filter.select field="type" table="{{ $files[0] ? $files[0]->getTable() : '' }}" /> --}}
                    {{-- <x-table.filter.select field="typ" table="{{ $files[0] ? $files[0]->getTable() : '' }}" /> --}}
                </div>
            </div>

            <button class="btn btn-danger" onclick="return confirm('Löschen ausführen?')" type="submit" name="switchTo"
                value="deleteCheck">
                Löschen
            </button>

            <button class="btn btn-warning" type="submit" name="switchTo" value="testingCheck">
                Testing
            </button>

            <x-form.submit />

        </x-form>

        <x-slot name="footer">
            {{ $files->links() }}
        </x-slot>

    </x-card>

    <x-counter startTime="{{ isset($startTime) ? $startTime : 0 }}" />

</x-app-layout>
