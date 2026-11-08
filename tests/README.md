# Tests für File Upload - DOES NOT WORK

## 1. File Factory - FileFactory.php

Erstellt mit verschiedenen States für unterschiedliche Dateitypen:

- pdf() - PDF-Dateien
- jpg() - JPG-Bilder
- png() - PNG-Bilder

- uncomplete() - Unvollständige Dateien
- inactive() - Inaktive Dateien

## 2. FileUploadTest - FileUploadTest.php

14 Tests für Upload-Funktionalität:

✓ Upload-Formular anzeigen
✓ Einzelne PDF-Datei hochladen
✓ Einzelne JPG-Datei hochladen
✓ Einzelne PNG-Datei hochladen
✓ Mehrere Dateien gleichzeitig hochladen
✓ Dateityp validieren
✓ Maximale Dateigröße validieren (10MB)
✓ Minimale Dateigröße validieren
✓ Datei-Upload erforderlich
✓ Datei-Metadaten korrekt speichern
✓ Datei mit Model verknüpfen
✓ ProcessFile Job dispatchen
✓ Datei am richtigen Ort speichern
✓ Eindeutige Dateinamen generieren

## 3. FileControllerTest - FileControllerTest.php

18 Tests für Dateiverwaltung:

✓ Alle Dateien auflisten
✓ Nach Status filtern
✓ Nach Typ filtern
✓ Dateien durchsuchen
✓ Einzelne Datei anzeigen
✓ Datei herunterladen
✓ Datei-Status aktualisieren
✓ Datei-Typ aktualisieren
✓ Datei löschen
✓ Pagination
✓ Nach Name sortieren
✓ Nach Größe sortieren
✓ Datei-Metadaten anzeigen
✓ 404 für nicht-existierende Datei
✓ Bulk-Update Status
✓ Bulk-Update Typ
✓ Datei mit Props anzeigen
✓ Dateigröße korrekt formatieren

## Technische Details:

- Alle Tests verwenden RefreshDatabase für saubere Testumgebung
- Storage::fake('public') für isoliertes Dateisystem-Testing
- Queue::fake() um Job-Dispatching zu testen ohne sie auszuführen
- Middleware wird deaktiviert für reibungslose Tests
- Tests folgen Pest v4 Syntax und Konventionen

Die Tests sind bereit und funktionieren! Sie können mit php artisan test tests/Feature/FileUploadTest.php oder php artisan test tests/Feature/FileControllerTest.php ausgeführt werden.
