# PDF-Prüfung: Eigenschaften, Signaturen, PDF/A, Prüfsummen

Der Tab **„PDF“** der Datei-Detailseite (`/files/{id}`) zeigt für das **Original** und
die **Archivfassung** (siehe [Archivierung.md](Archivierung.md)):

| Bereich | Inhalt | Werkzeug |
|---|---|---|
| Signaturen | je Signatur Unterzeichner, Zertifikat, Zeitstempel, Umfang, Bewertung | pyHanko (Bibliothek) |
| PDF/A | Kennung laut XMP, Prüfung mit veraPDF samt verletzten Regeln | veraPDF |
| Prüfsumme | SHA-256, Vergleich mit dem Wert bei der Archivierung | PHP |
| Eigenschaften | Metadaten, Seiten, Format, Textebene, Bilder (dpi), Schriften, Anhänge, ZUGFeRD, Formular, JavaScript, Verschlüsselung | poppler (`pdfinfo`, `pdffonts`, `pdfimages`, `pdfdetach`, `pdftotext`) |

Das Ergebnis steht in `custom_properties.inspection` (`$file->inspection()`):

```json
{
  "at": "2026-11-08T18:50:00+01:00",
  "trust": ["test-ca.crt"],
  "variants": {
    "original": { "status": "ok", "disk": "data", "path": "…",
                  "integrity": { "sha256": "…", "expected": "…", "ok": true },
                  "properties": { … }, "signatures": { … }, "pdfa": { … } },
    "archive":  { … }
  }
}
```

## Wann wird geprüft?

- automatisch nach der Archivierung (`ProcessFile` → `ConversionOcr` → `ArchivePdf` → `InspectPdf`),
  abschaltbar mit `FILES_INSPECT_ENABLED=false`
- per Knopf **„Neu prüfen“** im Tab (läuft sofort, einige Sekunden)
- nachträglich: `php artisan files:inspect --all` (nur PDFs ohne Ergebnis),
  `files:inspect 12 13 --sync`, `files:inspect --all --force`

Nach einer Änderung der Vertrauensanker mit `--all --force` neu prüfen. Das
gespeicherte Ergebnis gibt den Stand zum Prüfzeitpunkt wieder; Sperrungen von
Zertifikaten danach zeigt erst eine neue Prüfung.

## Bewertung der Signaturen

pyHanko fasst die Prüfung zu einem Gesamturteil („bottom line“) zusammen. Das
meldet schon „ungültig“, wenn nur der Zeitstempeldienst unbekannt ist – so
z. B. beim eigenen Archivsiegel mit FreeTSA. Der Tab zeigt daher je Signatur
einzelne Ampeln (`Services\Inspection\SignatureVerdict`):

| Ampel | grün | gelb | rot |
|---|---|---|---|
| Integrität | kryptographisch gültig, Inhalt unverändert | – | Inhalt verändert / Signaturwert falsch |
| Umfang | ganze Datei oder danach nur Prüfdaten ergänzt | danach Formular ausgefüllt, Kommentare | danach inhaltlich geändert |
| Vertrauen | Kette bis zu einem Vertrauensanker | keine Kette, Sperrstatus unklar | Zertifikat gesperrt |
| Zeitstempel | gültig, Dienst vertrauenswürdig | gültig, Dienst unbekannt | ungültig |
| Qualifikation | QES / qualifiziertes Siegel laut Zertifikat | – | – |

**Qualifikation** beruht nur auf den QC-Statements im Zertifikat (ETSI EN
319 412-5). Ein Abgleich mit der EU-Vertrauensliste (EUTL) findet nicht statt;
für eine belastbare eIDAS-Aussage das PDF mit EU-DSS
(<https://ec.europa.eu/digital-building-blocks/DSS/webapp-demo/validation>) prüfen.

Das **PAdES-Level** (B-B, B-T, B-LT, B-LTA) ist geschätzt: Zeitstempel → B-T,
zusätzlich eingebettete Prüfdaten (DSS) → B-LT, danach ein Dokumentzeitstempel → B-LTA.

**beA-Eingänge** tragen die Signatur oft als separate `.p7s`-Datei. Diese werden
hier nicht geprüft – nur im PDF eingebettete Signaturen.

## Vertrauensanker

`FILES_INSPECT_TRUST` – kommagetrennte Zertifikate (PEM/DER), relativ zu
`storage/` oder absolut. Standard: `app/private/files-signing/test-ca.crt`.
Nicht lesbare Dateien werden übergangen. Zusätzlich gilt der
Zertifikatsspeicher des Systems.

Damit der FreeTSA-Zeitstempel grün wird, das FreeTSA-Wurzelzertifikat ergänzen:

```bash
curl -o storage/app/private/files-signing/freetsa-cacert.pem https://freetsa.org/files/cacert.pem
```

```dotenv
FILES_INSPECT_TRUST=app/private/files-signing/test-ca.crt,app/private/files-signing/freetsa-cacert.pem
```

## Konfiguration

`config/files.php` unter `inspect`:

| `.env` | Standard | Bedeutung |
|---|---|---|
| `FILES_INSPECT_ENABLED` | `true` | nach der Archivierung automatisch prüfen |
| `FILES_INSPECT_PYTHON` | – | Python mit pyHanko; leer = Interpreter aus der Shebang-Zeile von `FILES_PYHANKO_BINARY` (pipx/venv), sonst `python3` |
| `FILES_INSPECT_TRUST` | Test-CA | Vertrauensanker, siehe oben |
| `FILES_INSPECT_FETCH` | `true` | Sperrinformationen (OCSP/CRL) und Zwischenzertifikate aus dem Netz laden; `false` = nur eingebettete Daten |

## Programme

Wie für die Archivierung im Container des Queue-Workers **und** des Webservers
(„Neu prüfen“ läuft im Request):

- `poppler-utils` (`apt-get install -y poppler-utils`)
- pyHanko als Bibliothek – bei `pip install pyhanko-cli` enthalten
- veraPDF (nur für PDFs mit PDF/A-Kennung)

`php artisan files:archive --check` prüft auch poppler, die pyHanko-Bibliothek
und die Vertrauensanker.

## Bausteine

| Klasse | Aufgabe |
|---|---|
| `Jobs\InspectPdf` | Job, ruft `PdfInspection` |
| `Services\Inspection\PdfInspection` | prüft Original und Archivfassung, speichert `inspection` |
| `Services\Inspection\PdfProperties` | poppler-Ausgaben → Array |
| `Services\Inspection\SignatureInspector` | ruft `resources/python/pdf_signatures.py` (JSON) |
| `Services\Inspection\SignatureVerdict` | Ampeln je Signatur |
| `Services\Archive\PdfaValidator` | veraPDF, liefert jetzt auch `rules` (Klausel, Beschreibung, Meldung; max. 50) |
| `resources/views/pages/files/show/pdf.blade.php` | Tab „PDF“ |

## Eigene Dokumente

Ist das Original durch die Archivfassung ersetzt (siehe
[Archivierung.md](Archivierung.md#eigene-dokumente-archivfassung-ersetzt-das-original)),
prüft der Tab „PDF“ als „Original“ die Datei im Papierkorb (mit Hinweis auf das
Löschdatum) bzw. zeigt nach Fristablauf, dass das Original gelöscht ist. Nach
dem Umschalten des Schalters „Eigenes Dokument“ wird automatisch neu geprüft.
