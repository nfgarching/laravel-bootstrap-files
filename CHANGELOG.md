# Changelog

Alle wesentlichen Änderungen an diesem Package werden in dieser Datei dokumentiert.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Added

- Archivierung aller PDFs: PDF/A-2b + Optimierung, Validierung mit veraPDF, PAdES-Siegel mit RFC-3161-Zeitstempel (pyHanko); Job `ArchivePdf` nach OCR, bereits signierte PDFs bleiben unverändert (`docs/Archivierung.md`).
- Befehle `files:make-test-certificate` (Test-CA + Siegelzertifikat) und `files:archive` (nachträgliche Archivierung, `--check`).
- Model: `archive()`, `archivePdfPath()`, `isArchiveSigned()`.
- PDF-Prüfung im Tab „PDF“ der Detailseite: Eigenschaften (poppler), Signaturen mit Einzelbewertung (pyHanko), PDF/A mit verletzten Regeln (veraPDF), Prüfsummenvergleich; Job `InspectPdf` nach `ArchivePdf`, Knopf „Neu prüfen“, Befehl `files:inspect`, Model `inspection()` (`docs/PDF-Pruefung.md`).
- „Eigenes Dokument“: geprüfte und gesiegelte Archivfassung ersetzt das Original, Original 90 Tage im Papierkorb (`FILES_TRASH_DAYS`), Wiederherstellen per Schalter; Vorbelegung nach Quelle (App, Import-Disk, Erzeugerprogramm); Schalter in Upload, Import und Detailansicht; Befehl `files:prune-trash` (täglich eingeplant).
- Erstveröffentlichung als `laravel-bootstrap-files` (übernommen aus dem internen Package `nfgarching/files`).

### Changed

- Schalter „Eigenes Dokument“ heißt jetzt „Original Dokument“ mit umgekehrter Logik: Standardmäßig ersetzt bei Upload und Import die Archivfassung das Original; „Original Dokument“ behält es. API `isOriginalDocument()`/`setOriginalDocument()`/`detectOriginalDocument()`, Option `'original'` (`'own'` wird umgekehrt noch angenommen), `custom_properties.original_document`, Config `files.archive.original.*`, Env `FILES_ORIGINAL_DISKS`/`FILES_ORIGINAL_PRODUCERS`, `FILES_ARCHIVE_REPLACE` Standard `archive`.
- OCR erzeugt ausdrücklich PDF/A (`FILES_PDFA_OUTPUT_TYPE`) mit Optimierung (`FILES_PDFA_OPTIMIZE`).
- Abhängigkeit `nfgarching/core` in `require` eingetragen, PHP-Mindestversion an Core angeglichen (^8.4); Chatbot nur noch als `suggest`.
- S3-Endpoints der Zusatz-Disks über `S3_ENDPOINT` / `S3_BACKUP_ENDPOINT` konfigurierbar.

### Fixed

- Scan-Eingang (`/disk-inbox`): Standard-Disk und -Pfad werden aus `files.scan_inbox` gelesen (bisher aus dem nicht vorhandenen Schlüssel `path.scan_inbox`).
- `importFromDisk()`: Prüfsumme (`hash`) wird von der Ziel-Disk gelesen statt von der Quell-Disk (ergab bisher nur die MD5 des Pfades).
- `importFromDisk()`: nicht vorhandene Spalte `file_type` wird nicht mehr in den Datensatz geschrieben.
- Temp-Dateien: `tempFileClean()` löscht nur noch die eigenen Dateien statt `/tmp/tmp*` (traf parallel laufende Jobs).

### Removed

- Veraltete Sicherungsdateien (`*.bak`, `*.old`, `_deprecated/`, `src/XUtils/`, `testing/`).
- Test-Uploads `upload-java` und `upload-test` (Routen, Controller, Views) sowie Test-Route `greeting_from_files`.
