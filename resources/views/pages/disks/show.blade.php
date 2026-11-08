<x-app-layout>

    <x-slot name="header">
        <div class="row">
            <div class="col-8">
                <div class="btn-group text-truncate" role="group">

                    <x-button.link :href="route('disks.index', $request->all())" type="outline-secondary" tooltip="Zurück zur Liste">
                        <i class="bi bi-arrow-left-circle"></i>
                    </x-button.link>

                    <a class="btn btn-primary" href="{{ url('/disks/' . $disk) }}">Home</a>

                    @if ($updir)
                        <a class="btn btn-light" href="?path={{ $updir }}">{{ basename($updir) }}</a>
                    @endif

                    @if ($root)
                        <button class="btn btn-secondary">{{ basename($root) }}</button>
                    @endif

                    @forelse ($dirs as $dir)
                        <a class="btn btn-light" href="?path={{ $dir }}">{{ basename($dir) }}</a>
                    @empty
                    @endforelse
                </div>
            </div>
            <div class="col-4 d-flex justify-content-end" role="group">

                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    Disk: {{ $disk }}
                </button>
                <ul class="dropdown-menu">
                    @forelse (config('filesystems.disks') as $diskName => $diskItem)
                        @php
                            // dump($diskItem);
                            if ($diskName == $disk) {
                                $active = 'active';
                            } else {
                                $active = '';
                            }
                        @endphp
                        <li>
                            <a class="dropdown-item {{ $active }}" href="{{ url('/disks/' . $diskName) }}">
                                {{ $diskName }}
                                <span class="badge bg-secondary float-end">
                                    {{ $diskItem['driver'] }}
                                </span>
                            </a>
                        </li>
                    @empty
                        <li><a class="dropdown-item" href="#">Action</a></li>
                        <li><a class="dropdown-item" href="#">Another action</a></li>
                        <li><a class="dropdown-item" href="#">Something else here</a></li>
                    @endforelse
                </ul>

                {{ $dirs->links() }}
            </div>
        </div>
        <hr>
    </x-slot>

    <x-card>

        <x-slot name="headerLeft">
            <x-table.filter>
                @php $perPage = (int) $request->get('per_page', 25); @endphp
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        {{ $perPage }} pro Seite
                    </button>
                    <ul class="dropdown-menu">
                        <li><span class="dropdown-item-text">Anzahl der Dateien:</span></li>
                        <li><hr class="dropdown-divider"></li>
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

                @if ($mimeTypes)
                    <div class="dropdown">
                        <button class="btn btn-outline-{{ $mimeFilter ? 'primary' : 'secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
            </x-table.filter>
        </x-slot>

        <x-slot name="headerRight">
            <div class="btn-group">
                <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    Filter
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <button class="dropdown-item" type="submit" name="switchTo" value="filter1">
                            Dateien ohne Verknüfung
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" type="submit" name="switchTo" value="filter2">
                            Große Dateien
                        </button>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    </li>
                    <li>
                        <button class="dropdown-item disabled" type="submit" name="switchTo" value="filterX">
                            ...
                        </button>
                    </li>
                </ul>
            </div>
        </x-slot>

        <x-form :action="route('disks.store')">

            @php
                $colspan = 5;
                $token = rand();
                $toggleCollapse = false;
                $toggleChecks = true;
                $toggleRadios = false;
                $i = 0;
            @endphp

            <x-table :acc_id="$token" class="table-hover table-responsive table-sm table-striped" :$colspan
                :$toggleCollapse :$toggleChecks :$toggleRadios>

                @forelse ($files as $key => $file)
                    @if ($loop->first)
                        <x-slot:header>
                            <th></th>
                            <x-table.head.sort field="name" label="Dateiname" />
                            <th nowrap>Mimetype</th>
                            <x-table.head.sort field="size" label="Dateigröße" />
                        </x-slot:header>
                    @endif

                    @php
                        // dump($key, $file);
                    @endphp

                    <x-table.row :acc_id="$token" :$toggleCollapse :$toggleChecks :$toggleRadios :check_value="$file">

                        <td nowrap width="5%">
                            <x-storage.link :disk="$disk" :file="$file" />
                        </td>
                        <td width="70%">
                            {{ basename($file) }}
                        </td>
                        <td width="10%">
                            <span class="badge bg-secondary">
                                {{ Str::limit(Storage::disk($disk)->mimeType($file), 14) }}
                            </span>
                        </td>
                        <td align="right" width="10%">
                            @if (config("filesystems.disks.$disk.driver") != 'ftp' && config("filesystems.disks.$disk.driver") != 'sftp')
                                <span class="badge bg-secondary">
                                    {{ Number::fileSize(Storage::disk($disk)->size($file)) }}
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    -
                                </span>
                            @endif
                        </td>
                    </x-table.row>

                @empty
                    <tr>
                        <td colspan="3">--- No Files ---</td>
                    </tr>
                @endforelse

                <x-slot:footer>
                    <hr>

                    <div class="btn-group">
                        <div class="input-group">
                            <button class="btn btn-light" onclick="return confirm('Auswahl ändern?')" type="submit"
                                value="change" name="switchTo">
                                Ändern
                            </button>
                            {{-- <x-table.filter.select field="state" table="{{ $files[0] ? $files[0]->getTable() : '' }}" /> --}}
                            {{-- <x-table.filter.select field="type" table="{{ $files[0] ? $files[0]->getTable() : '' }}" /> --}}
                            {{-- <x-table.filter.select field="typ" table="{{ $files[0] ? $files[0]->getTable() : '' }}" /> --}}
                        </div>
                    </div>

                    <button class="btn btn-danger" onclick="return confirm('Löschen ausführen?')" type="submit"
                        name="switchTo" value="deleteCheck">
                        Löschen
                    </button>

                    <button class="btn btn-warning" type="submit" name="switchTo" value="testingCheck">
                        Testing
                    </button>

                    <x-form.submit />

                    <button class="btn btn-danger disabled" onclick="return confirm('Löschen ausführen?')"
                        type="submit" name="switchTo" value="deleteCheck">
                        Löschen
                    </button>

                    <button class="btn btn-warning disabled" type="submit" name="switchTo" value="testingCheck">
                        Testing
                    </button>


                    <div class="btn-group">
                        <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <button class="dropdown-item" type="submit" name="switchTo" value="importCheck">
                                    Auswahl importieren
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item disabled" type="submit" name="switchTo"
                                    value="importPage">
                                    Alles importieren
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item disabled" type="submit" name="switchTo"
                                    value="importAll">
                                    inkl. Unterordner
                                </button>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <button class="dropdown-item" type="submit" name="switchTo" value="linkToCheck">
                                    Link zur DB hinzufügen
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item disabled" type="submit" name="switchTo"
                                    value="linkToPage">
                                    Alles hinzufügen
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item disabled" type="submit" name="switchTo"
                                    value="linkToAll">
                                    inkl. Unterordner
                                </button>
                            </li>
                        </ul>
                        <button class="btn btn-success" type="submit" name="switchTo" value="importCheck">
                            Auswahl importieren
                        </button>
                    </div>


                    <x-form.submit />

                    <button class="btn btn-info disabled" type="submit" value="testCheck" name="switchTo">Klick
                        mich</button>

                </x-slot:footer>

            </x-table>

            {{-- <x-table.check /> --}}

        </x-form>

        <x-slot name="footer">
            {{ $files->links() }}
        </x-slot>

    </x-card>


    <x-counter startTime="{{ $startTime }}" />

    @pushOnce('scripts')
        <script>
            function btnDiskTest(event) {
                console.log('func disk.index: btnDiskShowFile');
                console.log(event.target.dataset);
                console.log('press-metaKey:', event.metaKey);
                if (event.ctrlKey) {
                    console.log('ctrl-key');
                    if (event.ctrlLeft) {
                        console.log('ctrl-left');
                    } else {
                        console.log('ctrl-right');
                    }
                } else if (event.shiftKey) {
                    console.log('shift-key');
                    if (event.shiftLeft) {
                        console.log('shift-left');
                    } else {
                        console.log('shift-right');
                    }
                } else if (event.altKey) {
                    console.log('alt-key');
                    if (event.altLeft) {
                        console.log('alt-left');
                    } else {
                        console.log('alt-right');
                    }
                } else {
                    console.log('press-key:', event);
                }
            }

            console.log('init btnDiskShowFile');

            function btnDiskShowFile(e) {
                console.log('click disk.index: btnDiskShowFile');
                console.log(e.target.dataset);
                console.log(e.target.dataset.disk); // Muss angezeigt werden!!!
                console.log(e.target.dataset.file); // Muss angezeigt werden!!!
                var host = window.location.host;
                var showload = 'http://' + host + '/download?disk=' +
                    e.target.dataset.disk + '&file=' + e.target.dataset.file;
                var showfile = 'http://' + host + '/showFile?disk=' +
                    e.target.dataset.disk + '&file=' + e.target.dataset.file;
                if (!e.target.dataset.file) {
                    // --- HINWEIS!!! --- //
                    /* Is absolutely normal that you don't get the dataset: 
                    /* you can click out of the cell, or in a element into this.
                    /* (vgl. https://stackoverflow.com/questions/70195994/event-target-dataset-returning-undefined-javascript)
                    // --- LÖSUNG!!! --- //
                    /*Innerhalb eines Button in allen Elemente (span i usw.) dataset aufnehmen! */
                    console.log('Fehler');
                    return false;
                }
                if (e.ctrlKey) {
                    window.open(showload, '_blank');
                } else if (e.shiftKey) {
                    window.open(showload, 'Attachment',
                        'height=1280,width=800,toolbar=0,location=0,directories=0,dependent=1,status=0,titlebar=0,menubar=0,status=no,scrollbars=0,resizable=0'
                    ).focus();
                } else if (e.altKey) {
                    console.log('alt-key');
                } else {
                    location.href = showfile;
                }
            }
        </script>
    @endPushOnce

</x-app-layout>
