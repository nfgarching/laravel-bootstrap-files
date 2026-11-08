# OCR-Installation (Docker / Sail)

Die OCR-Verarbeitung von PDFs übernimmt der Job
[`ConversionOcr`](../packages/nfgarching/files/src/Services/ConversionOcr.php).
Er ruft externe System-Tools auf – diese müssen **im Container** installiert sein,
in dem der Queue-Worker läuft (`queue`-Service), nicht auf dem Host.

## Benötigte Tools

| Paket (Ubuntu)      | Zweck                                                        | Pflicht |
|---------------------|-------------------------------------------------------------|---------|
| `ocrmypdf`          | Haupt-Tool: legt eine Textebene über gescannte PDFs         | ✅ ja   |
| `tesseract-ocr`     | OCR-Engine, die ocrmypdf intern nutzt                       | ✅ ja   |
| `tesseract-ocr-deu` | Deutsches Sprachmodell (`ocrmypdf -l deu …`)                | ✅ ja   |
| `jbig2enc`          | JBIG2-Encoder für bessere (verlustfreie) Kompression        | ⭕ optional |

> **Hinweis:** Das Paket heißt `jbig2enc` (der Encoder), **nicht** `jbig2`.
> Es liegt im Ubuntu-Repo `universe` (standardmäßig aktiv). ocrmypdf funktioniert
> auch ohne – dann nur ohne die zusätzliche JBIG2-Kompression.

Zusätzlich wird `pdf2txt` (Fallback für PDFs mit digitaler Signatur bzw. bereits
vorhandenem Text) verwendet – siehe `ConversionOcr::checkText()`. Es gehört zum
Paket `python3-pdfminer`; installiere es bei Bedarf mit.

## Dauerhafte Installation (empfohlen)

Ein `apt install` im **laufenden** Container ist nach jedem `sail build` /
Container-Neustart wieder weg. Damit die Tools dauerhaft im Image
`sail-8.5/app` stecken (das alle drei Services – `akv.2026`, `queue`, `reverb` –
teilen), muss das Runtime-Image angepasst werden.

Der Build-Context darf **nicht** auf `vendor/laravel/sail/runtimes/8.5` zeigen,
weil `vendor/` von Composer überschrieben wird. Deshalb wird das Runtime ins
Projekt kopiert.

### 1. Runtime ins Projekt kopieren

Einmalig (bereits erledigt, hier zur Doku):

```bash
cp -r vendor/laravel/sail/runtimes/8.5 docker/8.5
```

Alternativ offiziell via Sail (kopiert alle Runtimes und passt die Compose-Datei an):

```bash
sail artisan sail:publish
```

### 2. Pakete ins Dockerfile eintragen

In [`docker/8.5/Dockerfile`](../docker/8.5/Dockerfile) im ersten
`apt-get install`-Block ergänzt:

```dockerfile
    && apt-get install -y ocrmypdf tesseract-ocr tesseract-ocr-deu jbig2enc \
```

### 3. Build-Context in der Compose-Datei umstellen

In [`compose.yaml`](../compose.yaml) beim Service `akv.2026`:

```yaml
build:
    context: './docker/8.5'   # statt './vendor/laravel/sail/runtimes/8.5'
```

Nur `akv.2026` hat einen `build:`-Block; `queue` und `reverb` referenzieren
`image: sail-8.5/app` und ziehen das neu gebaute Image automatisch.

### 4. Image neu bauen und Container starten

```bash
sail build --no-cache      # bzw. ./vendor/bin/sail build --no-cache
sail up -d
```

### 5. Installation prüfen

```bash
sail exec akv.2026 ocrmypdf --version
sail exec akv.2026 tesseract --list-langs   # muss 'deu' enthalten
sail exec queue     ocrmypdf --version       # Worker-Container gegenprüfen
```

## Schnelltest (temporär, nicht dauerhaft)

Nur zum Ausprobieren – überlebt keinen Rebuild:

```bash
sail root-shell
apt-get update && apt-get install -y ocrmypdf tesseract-ocr tesseract-ocr-deu jbig2enc
```

## Fehlerbild & Fehlersuche

### Ablage des Ergebnisses

`ConversionOcr` legt OCR-PDF und OCR-Text als Dateien im Verzeichnis `ocrs/` ab
und speichert in der Tabelle `files` **nur Links** (die frühere Tabelle
`file_props` gibt es nicht mehr):

| Spalte                      | Inhalt                                                          |
|-----------------------------|-----------------------------------------------------------------|
| `generated_conversions.ocr` | Pfad des durchsuchbaren OCR-PDFs, neben `preview`/`thumb`       |
| `custom_properties.ocr`     | `{type, disk, text, size, error, at}` – `text` = Pfad der Textdatei |

`type` ist `ocrtext` (OCR erkannt), `text` (PDF hatte schon Text), `digitext`
(signiertes PDF, Text per pdf2txt, kein OCR-PDF) oder einer der Fehlertypen unten.
Zugriff im Code über `File::ocr()`, `ocrText()`, `ocrTextPath()`, `ocrPdfPath()`,
`ocrFailed()`; `propsText()` liefert den Text samt Hinweis für Dokument und Detailansicht.

PDF-, Bild- und OCR-Konvertierung laufen parallel in der Queue und schreiben in
dieselben JSON-Spalten. Deshalb immer `File::mergeJson($spalte, [...])`
verwenden statt das ganze Array zu setzen – sonst gehen `preview`/`thumb` oder
`ocr` verloren.

### Fehler

`ConversionOcr` bricht bei fehlenden Tools **nicht** hart ab, sondern vermerkt
den Fehler in `custom_properties.ocr`, damit Upload und `NewFiles`-Event weiterlaufen:

| `type`   | Bedeutung                                                        |
|----------|------------------------------------------------------------------|
| `noocr`  | OCR-Tool nicht gefunden → Installation prüfen (dieses Dokument)  |
| `errocr` | Verarbeitungsfehler (Timeout, Quelldatei fehlt, ocrmypdf failed) |

Bei diesen Typen ist `text` leer und `error` enthält den (auf 1000 Zeichen
gekürzten) Fehlertext; der vollständige Text steht im Log.

**Logs:** Alle Meldungen gehen in den Log-Kanal `files` und beginnen mit der
File-ID im Format `#<id>`:

```bash
sail exec akv.2026 tail -f storage/logs/files*.log   # Pfad ggf. an Kanal-Config anpassen
```

**Häufige Ursachen für `noocr`:**

- Rebuild vergessen → Schritt 4 ausführen.
- Falscher Container geprüft: Der Job läuft im `queue`-Service (siehe
  `docs/Queue-und-Reverb.md`), nicht zwingend im Web-Container.
- `FILE_PROCESSING_QUEUE=noprocess` in der aktiven `.env` → dann läuft OCR gar
  nicht (bewusst deaktiviert). Für Verarbeitung `default` (Queue) oder `sync`
  setzen.

## Verwandte Dateien

- Job: `packages/nfgarching/files/src/Services/ConversionOcr.php`
- Dispatch-Kette: `packages/nfgarching/files/src/Jobs/ProcessFile.php`
- Config: `packages/nfgarching/files/config/files.php`
  (`ocr_conversion_service`, `file_processing_queue`)
- Queue-Setup: `docs/Queue-und-Reverb.md`
