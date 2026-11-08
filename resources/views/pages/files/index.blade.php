<x-app-layout>

    <x-slot name="header">
        <div class="container">
            <div class="btn-group">
                <x-button type="light">
                    {{ Str::limit('Files', 150) }}
                </x-button>
            </div>
            <div class="btn-group btn-group-sm float-end">
                @if (Route::has('disks.index'))
                    <x-button.link class="btn-sm" type='outline-primary' :href="route('disks.index')">
                        Disks
                    </x-button.link>
                @endif
            </div>
        </div>
        <hr>
    </x-slot>

    @php
        $activeFilters = [];
        if ($request->disk) {
            $activeFilters['disk'] = 'Disk: ' . $request->disk;
        }
        if ($request->state) {
            $activeFilters['state'] = 'Status: ' . $request->state;
        }
        if ($request->type) {
            $activeFilters['type'] = 'Typ: ' . ($typeOptions[$request->type] ?? $request->type);
        }
        if ($request->jahr) {
            $activeFilters['jahr'] = 'Jahr: ' . $request->jahr;
        }
        $activeFilterCount = count($activeFilters);
        $toggleSelect = $request->boolean('select', false);
    @endphp

    <x-card>

        <x-slot name="headerLeft">
            @php
                $sortField = strtolower((string) $request->get('sort', 'id'));
                $sortAsc = strtoupper((string) $request->get('direction', 'DESC')) === 'ASC';
                $sortFields = [
                    'id' => 'Anlage',
                    'name' => 'Dateiname',
                    'disk' => 'Disk',
                    'state' => 'Status',
                    'type' => 'Typ',
                    'size' => 'Größe',
                ];
            @endphp

            <x-table.filter>
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    @if ($activeFilterCount)
                        <span class="badge bg-warning text-dark">{{ $activeFilterCount }}</span>
                    @endif
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                            data-bs-target="#filesFilterModal">
                            <i class="bi bi-funnel{{ $activeFilterCount ? '-fill' : '' }}"></i> Filter
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <x-dropdown.link :href="route(
                        'files.index',
                        array_merge($request->except('select'), ['select' => $toggleSelect ? 0 : 1]),
                    )">
                        <i class="bi bi-{{ $toggleSelect ? 'check2-square' : 'square' }}"></i>
                        {{ $toggleSelect ? 'Select deaktivieren' : 'Select aktivieren' }}
                    </x-dropdown.link>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <h6 class="dropdown-header">Sortieren</h6>
                    </li>
                    @foreach ($sortFields as $field => $label)
                        @php $active = $sortField === strtolower($field); @endphp
                        <x-dropdown.link :href="route(
                            'files.index',
                            array_merge($request->except(['sort', 'direction', 'page']), [
                                'sort' => $field,
                                'direction' => $active && $sortAsc ? 'desc' : 'asc',
                            ]),
                        )">
                            @if ($active)
                                <i class="bi bi-sort-{{ $sortAsc ? 'up' : 'down' }}"></i>
                            @else
                                <i class="bi bi-dash"></i>
                            @endif
                            {{ $label }}
                        </x-dropdown.link>
                    @endforeach
                </ul>
                @foreach (['jahr', 'per_page', 'sort', 'direction', 'select'] as $param)
                    @if ($request->filled($param))
                        <input type="hidden" name="{{ $param }}" value="{{ $request->get($param) }}">
                    @endif
                @endforeach
                <x-table.filter.search field="search" table="files" :oldval="$request->search" />
                <x-file.filter.select field="disk" />
                <x-file.filter.select field="state" />
                <x-file.filter.select field="type" />
            </x-table.filter>

            @if ($activeFilterCount)
                <div class="d-flex flex-wrap gap-1 mt-1">
                    @foreach ($activeFilters as $param => $label)
                        <a href="{{ route('files.index', $request->except([$param, 'page'])) }}"
                            class="badge bg-primary text-decoration-none">
                            {{ $label }} <i class="bi bi-x"></i>
                        </a>
                    @endforeach
                    <a href="{{ route('files.index', array_filter($request->only(['search', 'per_page']))) }}"
                        class="badge bg-danger text-decoration-none">
                        Alle Filter <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            @endif
        </x-slot>

        <x-slot name="headerRight">
            <x-button.group>
                @if (Route::has('disk-inbox.index'))
                    <x-button.dispatch X_modalName="openModalUpload" :href="route('disk-inbox.index')" type="outline-secondary">
                        <i class="bi bi-hdd-network"></i>
                    </x-button.dispatch>
                @endif
                @if (Route::has('upload'))
                    <x-button.dispatch :href="route('upload')" type="outline-secondary">
                        <i class="bi bi-upload"></i>
                    </x-button.dispatch>
                @endif

                {{-- <div class="dropdown"> --}}
                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                    data-bs-toggle="dropdown"></button>
                <ul class="dropdown-menu">
                    <li><span class="dropdown-item-text">Testing:</span></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    @if (Route::has('upload'))
                        <x-button.dispatch class="dropdown-item" :href="route('upload')" :data="[
                            'model_type' => 'Nfgarching\Documents\Models\Document',
                            'subject' => 'Telefonanruf: ',
                            'title' => 'New Document',
                        ]">
                            <i class="bi bi-upload"></i> New Document
                        </x-button.dispatch>
                        <x-button.dispatch class="dropdown-item" :href="route('upload')" :data="[
                            'model_type' => 'App\Models\Dokument',
                            'subject' => 'Telefonanruf: ',
                            'title' => 'Neues Dokument',
                        ]">
                            <i class="bi bi-upload"></i> Neues Dokument
                        </x-button.dispatch>
                    @endif
                    @if (Route::has('disk-inbox.index'))
                        <x-button.dispatch class="dropdown-item" :href="route('disk-inbox.index')" :data="[
                            'model_type' => 'Nfgarching\Documents\Models\Document',
                            'subject' => 'Telefonanruf: ',
                            'title' => 'New Document',
                        ]">
                            <i class="bi bi-hdd-network"></i> New Document
                        </x-button.dispatch>
                        <x-button.dispatch class="dropdown-item" :href="route('disk-inbox.index')" :data="[
                            'model_type' => 'App\Models\Dokument',
                            'subject' => 'Telefonanruf: ',
                            'title' => 'Neues Dokument',
                        ]">
                            <i class="bi bi-hdd-network"></i> Neues Dokument
                        </x-button.dispatch>
                    @endif
                </ul>
                {{-- </div> --}}

            </x-button.group>
        </x-slot>

        <x-form :action="route('files.bulk-action')">

            @php
                $token = rand();
                $toggleCollapse = false;
                $toggleChecks = (bool) $toggleSelect;
                $toggleRadios = (bool) $toggleSelect;
            @endphp
            <x-table :acc_id="$token" class="table-hover table-sm" :$toggleChecks>
                @forelse ($files as $file)
                    @if ($loop->first)
                        <x-slot:header>
                            <th></th>
                            <th></th>
                            <x-table.head.sort field="disk" />
                            <x-table.head.sort field="name" label="Dateiname" />
                            <x-table.head.sort field="state" />
                            <x-table.head.sort field="type" />
                            <x-table.head.sort field="size" />
                        </x-slot:header>
                    @endif

                    @php
                        $showParams = array_merge(['file' => $file->id], $request->except('page'));
                    @endphp
                    <x-table.row.line1 :acc_id="$token" :row_id="$file->id" :$toggleCollapse :$toggleChecks :$toggleRadios>
                        <td>
                            <x-button.group>
                                <x-button.filelink :file="$file" />
                                <x-button.link :href="route('files.show', $showParams)" type="outline-secondary"
                                    size="sm">
                                    <i class="bi bi-info-square"></i>
                                </x-button.link>
                            </x-button.group>
                        </td>
                        <td nowrap>
                            <x-button.model :model="$file->model_type" :modid="$file->model_id" />
                        </td>
                        <td align="center" nowrap>
                            <a class="btn btn-sm btn-outline-primary" role="button"
                                href="{{ route('files.index', array_merge($request->except('page'), ['disk' => $file->disk])) }}">
                                {{ $file->disk }}
                            </a>
                        </td>
                        <td width="80%">
                            <x-button data-bs-toggle="collapse" data-bs-target="#collapse{{ $token . $file->id }}"
                                type="light" class="text-start">
                                {{ Str::limit($file->name, 60) }}
                            </x-button>
                        </td>
                        <td align="center" nowrap>
                            <a class="btn btn-sm btn-outline-primary" role="button"
                                href="{{ route('files.index', array_merge($request->except('page'), ['state' => $file->state])) }}">
                                {{ $file->state }}
                            </a>
                        </td>
                        <td align="center" nowrap>
                            <a class="btn btn-sm btn-outline-primary" role="button"
                                href="{{ route('files.index', array_merge($request->except('page'), ['type' => $file->type?->value])) }}">
                                {{ $file->type?->label() ?? 'n/a' }}
                            </a>
                        </td>
                        <td align="right" nowrap>{{ $file->formatBytes() }}</td>
                    </x-table.row.line1>

                    <x-table.row.line2 :acc_id="$token" :row_id="$file->id">
                        <td colspan="7">
                            <p>
                                {{ $file->name }} (HASH: {{ $file->hash }})
                            </p>
                            {{ $file->generated_conversions['preview'] ?? 'no preview' }}

                            <hr>

                            <x-button.group>
                                <x-chatbot :data="[
                                    'model_type' => $file::class,
                                    'model_id' => $file->id,
                                ]" />
                                <x-button.link :href="route('files.show', $showParams)" type="outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </x-button.link>
                                {{-- Löschen-Formular steht unterhalb der Listen-Form (keine verschachtelten Forms) --}}
                                <button type="submit" form="delete-file-{{ $file->id }}"
                                    class="btn btn-outline-danger"
                                    onclick="return confirm('Datei „{{ addslashes(Str::limit($file->name, 60)) }}“ wirklich löschen?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </x-button.group>
                        </td>
                    </x-table.row.line2>

                    @if ($loop->last && $toggleChecks)
                        <tr>
                            <td></td>
                            <td colspan="8">
                                <hr>
                                <x-button.group>
                                    <button class="btn btn-info" type="submit" value="testCheck"
                                        name="switchTo">Action</button>
                                    <button class="btn btn-danger" type="submit" value="delete" name="switchTo"
                                        onclick="return confirm('Markierte Dateien wirklich löschen?')">Delete</button>
                                </x-button.group>
                                <hr>
                            </td>
                        </tr>
                    @endif

                @empty
                    <tr>
                        <td colspan="9">--- No Data ---</td>
                    </tr>
                @endforelse

            </x-table>

        </x-form>

        @foreach ($files as $file)
            <x-form :action="route('files.destroy', $file)" method="DELETE" id="delete-file-{{ $file->id }}" />
        @endforeach

        <x-slot name="footer">
            @php $perPage = $files->perPage(); @endphp
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

                <div class="input-group input-group-sm w-auto">
                    <label class="input-group-text" for="filesPerPage">
                        {{ $files->firstItem() ?? 0 }} - {{ $files->lastItem() ?? 0 }} von {{ $files->total() }}
                    </label>
                    <select class="form-select" id="filesPerPage" onchange="window.location.href = this.value">
                        @foreach ([5, 10, 25, 50, 100] as $n)
                            <option
                                value="{{ route('files.index', array_merge($request->except(['per_page', 'page']), ['per_page' => $n])) }}"
                                @selected($perPage === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    {{ $files->withQueryString()->links('pagination.links') }}
                </div>

            </div>
        </x-slot>

    </x-card>

    {{-- Filter Modal --}}
    <div class="modal fade" id="filesFilterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-funnel"></i> Filter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET" action="{{ route('files.index') }}">
                    @foreach (['search', 'per_page', 'sort', 'direction', 'select'] as $param)
                        @if ($request->filled($param))
                            <input type="hidden" name="{{ $param }}" value="{{ $request->get($param) }}">
                        @endif
                    @endforeach
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Disk</label>
                                <x-table.filter.dropdown field="disk" :options="$diskOptions" :oldval="$request->disk"
                                    label="Disk" onchange="" />
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Status</label>
                                <x-table.filter.dropdown field="state" :options="$stateOptions" :oldval="$request->state"
                                    label="Status" onchange="" />
                            </div>
                            <div class="col-8">
                                <label class="form-label small fw-semibold">Typ</label>
                                <x-table.filter.dropdown field="type" :options="$typeOptions" :oldval="$request->type"
                                    label="Typ" onchange="" />
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-semibold">Jahr</label>
                                <x-table.filter.dropdown field="jahr" :options="$jahre" :oldval="$request->jahr"
                                    label="Jahr" onchange="" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('files.index', array_filter($request->only(['search', 'per_page']))) }}"
                            class="btn btn-outline-danger">
                            <i class="bi bi-x-circle"></i> Zurücksetzen
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Anwenden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <x-counter :$startTime />
    </x-slot>

</x-app-layout>
