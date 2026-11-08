<x-popup-layout>

    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-md-12">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Scan-Posteingang</h5>
                        <small class="text-muted">{{ $disk }}: {{ $path }}</small>
                    </div>
                    <div class="card-body p-2">

                        {{-- Toolbar: Mime-Filter & pro-Seite --}}
                        <div class="d-flex gap-2 mb-2">

                            @if ($mimeTypes)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-{{ $mimeFilter ? 'primary' : 'secondary' }} dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">
                                        {{ $mimeFilter ? Str::afterLast($mimeFilter, '/') : 'Mimetype' }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><span class="dropdown-item-text">Mimetype filtern:</span></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item @if (!$mimeFilter) active @endif"
                                                href="{{ $request->fullUrlWithQuery(['mime' => null, 'file_page' => 1]) }}">
                                                Alle
                                            </a>
                                        </li>
                                        @foreach ($mimeTypes as $type)
                                            <li>
                                                <a class="dropdown-item @if ($mimeFilter === $type) active @endif"
                                                    href="{{ $request->fullUrlWithQuery(['mime' => $type, 'file_page' => 1]) }}">
                                                    {{ $type }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                    type="button" data-bs-toggle="dropdown">
                                    {{ $perPage }} pro Seite
                                </button>
                                <ul class="dropdown-menu">
                                    @foreach ([10, 25, 50, 100] as $n)
                                        <li>
                                            <a class="dropdown-item @if ($perPage === $n) active @endif"
                                                href="{{ $request->fullUrlWithQuery(['per_page' => $n, 'file_page' => 1]) }}">
                                                {{ $n }} pro Seite
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                        </div>

                        <form action="{{ route('disk-inbox.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="disk" value="{{ $disk }}">
                            <input type="hidden" name="model_type" value="{{ $model_type }}">
                            <input type="hidden" name="model_id" value="{{ $model_id }}">

                            <table class="table table-sm table-hover table-striped mb-2">
                                <thead>
                                    <tr>
                                        <th>
                                            <input class="form-check-input" type="checkbox" id="toggleAll"
                                                onchange="document.querySelectorAll('.file-check').forEach(cb => cb.checked = this.checked)">
                                        </th>
                                        <th>
                                            @php
                                                $nameDir = ($sort === 'name' && $direction === 'asc') ? 'desc' : 'asc';
                                            @endphp
                                            <a href="{{ $request->fullUrlWithQuery(['sort' => 'name', 'direction' => $nameDir, 'file_page' => 1]) }}"
                                                class="text-decoration-none text-dark">
                                                Dateiname
                                                @if ($sort === 'name')
                                                    <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill"></i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            @php
                                                $sizeDir = ($sort === 'size' && $direction === 'asc') ? 'desc' : 'asc';
                                            @endphp
                                            <a href="{{ $request->fullUrlWithQuery(['sort' => 'size', 'direction' => $sizeDir, 'file_page' => 1]) }}"
                                                class="text-decoration-none text-dark">
                                                Größe
                                                @if ($sort === 'size')
                                                    <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill"></i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>Typ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($files as $file)
                                        <tr>
                                            <td>
                                                <input class="form-check-input file-check" type="checkbox"
                                                    name="files[]" value="{{ $file }}">
                                            </td>
                                            <td>{{ basename($file) }}</td>
                                            <td>
                                                @php
                                                    try { $size = Storage::disk($disk)->size($file); } catch (\Throwable) { $size = 0; }
                                                @endphp
                                                <span class="badge bg-secondary">
                                                    {{ $size ? Number::fileSize($size) : '-' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    {{ strtolower(pathinfo($file, PATHINFO_EXTENSION)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">--- Keine Dateien ---</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            {{ $files->links() }}

                            <div class="d-flex gap-2 align-items-center mt-2">
                                <input type="text" name="Betreff" class="form-control form-control-sm"
                                    placeholder="Bezeichnung...">
                                <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                                    <i class="bi bi-download me-1"></i>
                                    Importieren
                                </button>
                            </div>

                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

</x-popup-layout>
