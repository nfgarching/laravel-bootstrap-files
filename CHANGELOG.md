# Changelog

Alle wesentlichen Änderungen an diesem Package werden in dieser Datei dokumentiert.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Added

- Erstveröffentlichung als `laravel-bootstrap-files` (übernommen aus dem internen Package `nfgarching/files`).

### Changed

- Abhängigkeit `nfgarching/core` in `require` eingetragen, PHP-Mindestversion an Core angeglichen (^8.4); Chatbot nur noch als `suggest`.
- S3-Endpoints der Zusatz-Disks über `S3_ENDPOINT` / `S3_BACKUP_ENDPOINT` konfigurierbar.

### Removed

- Veraltete Sicherungsdateien (`*.bak`, `*.old`, `_deprecated/`, `src/XUtils/`, `testing/`).
- Test-Uploads `upload-java` und `upload-test` (Routen, Controller, Views) sowie Test-Route `greeting_from_files`.
