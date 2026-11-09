# Archivierung: PDF/A, Siegel und Zeitstempel

Jedes hochgeladene PDF erhält nach der OCR-Verarbeitung eine **Archivfassung**:

```
Original (unverändert) → OCR → PDF/A-2b + Optimierung → Validierung → Siegel + Zeitstempel
                         ocrmypdf                        veraPDF      pyHanko (PAdES, RFC 3161)
```

- Die SHA-256-Prüfsumme des **Originals** wird gespeichert. Standardmäßig ersetzt die
  geprüfte und gesiegelte Archivfassung das Original; nur bei **„Original Dokument“**
  bleibt das Original bitgenau Hauptdatei (siehe unten).
- Die Archivfassung liegt unter `…/archive/JJJJ-MM/archive-<datei>.pdf`
  (`generated_conversions.archive`).
- Das Ergebnis jedes Schritts steht in `custom_properties.archive`.
- **Bereits signierte PDFs** (beA-Eingänge, Bescheide, signierte Verträge) werden
  nicht verändert – jede Änderung würde ihre Signatur zerstören. Sie erhalten den
  Status `skipped` / `signed_input`.
- Die **Signatur ist immer der letzte Schritt.** Jede spätere Änderung am PDF
  würde sie ungültig machen.

## Ablauf im Code

| Baustein | Aufgabe |
|---|---|
| `Jobs\ProcessFile` | startet für PDFs die Kette `ConversionOcr → ArchivePdf` (`Bus::chain`) |
| `Services\ConversionOcr` | OCR, Ausgabe direkt als PDF/A (`--output-type`, `--optimize`) |
| `Jobs\ArchivePdf` | steuert die Archivierung, legt die Archivfassung ab |
| `Services\Archive\PdfInspector` | erkennt Signaturen (`/ByteRange`) und PDF/A-Kennung, bildet Prüfsummen |
| `Services\Archive\PdfaConverter` | PDF/A-Umwandlung mit ocrmypdf, falls OCR kein PDF/A geliefert hat |
| `Services\Archive\PdfaValidator` | Konformitätsprüfung mit veraPDF |
| `Services\Archive\PdfSigner` | PAdES-Siegel und Zeitstempel mit pyHanko |

Fehlt ein Programm, bricht die Verarbeitung nicht ab: Der Schritt erhält den
Status `notool`, der Rest läuft weiter. Wurde weder PDF/A noch eine Signatur
erzeugt, wird keine Archivfassung abgelegt (`status: error`).

### Ergebnis in `custom_properties.archive`

```json
{
  "status": "archived",
  "disk": "data",
  "path": "/2026/archive/2026-11/archive-AbC123.pdf",
  "sha256": "…",
  "source_sha256": "…",
  "pdfa":       { "status": "existing" },
  "validation": { "status": "compliant", "flavour": "2b" },
  "signature":  { "status": "signed", "subject": "… Testsiegel", "field": "Archivsiegel",
                  "pades": true, "timestamp": "https://freetsa.org/tsr" },
  "at": "2026-11-08T13:12:18+01:00"
}
```

Mögliche Status der Schritte:

| Schritt | Status |
|---|---|
| `pdfa` | `existing` (OCR hat PDF/A geliefert), `converted`, `notool`, `error`, `disabled` |
| `validation` | `compliant`, `noncompliant` (+ `failed_rules`), `skipped`, `notool`, `error`, `disabled` |
| `signature` | `signed`, `nocert`, `notool`, `error`, `disabled` |

Im Model: `$file->archive()`, `$file->archivePdfPath()`, `$file->isArchiveSigned()`.

## Archivfassung ersetzt das Original (außer „Original Dokument“)

Bei Upload und Import wird standardmäßig die Archivfassung übernommen: Sie ist
meist die bessere Datei (PDF/A, durchsuchbar, meist kleiner, gesiegelt). Nach
erfolgreicher Archivierung wird

1. die Archivfassung zur Hauptdatei (`file`, `hash`, `size`),
2. das Original in den Papierkorb verschoben (`…/trash/JJJJ-MM/`),
3. das Original nach **90 Tagen** (`FILES_TRASH_DAYS`) von `files:prune-trash`
   endgültig gelöscht.

Ersetzt wird nur, wenn die Archivfassung **validiert** (`compliant`) **und
gesiegelt** (`signed`) ist. Ist ein PDF als **„Original Dokument“**
gekennzeichnet, bleibt das Original die Hauptdatei. Das empfiehlt sich für
elektronisch empfangene Dokumente Dritter: Nur das Original zeigt bitgenau, was
eingegangen ist; außerdem entfernt PDF/A eingebettete Dateien und JavaScript.
Das beA-Package importiert seine Dateien deshalb immer als Original Dokument;
bereits signierte PDFs werden ohnehin nicht archiviert und damit nie ersetzt.

Nach jedem Austausch der Hauptdatei (Ersetzen wie Wiederherstellen) feuert
das Event `Nfgarching\Files\Events\FileReplaced` (`file`, `oldPath`,
`oldHash`, `reason` = `archive`|`restore`). Anwendungen, die Pfad oder Hash
selbst gespeichert haben, ziehen ihn dort nach – in AkV
`App\Listeners\RecieveFileReplaced` für `Dokumente.Attachment`.

### Vorbelegung des Schalters „Original Dokument“ nach Quelle

Ohne Treffer ist der Schalter aus, die Archivfassung wird übernommen.

| Quelle | Vorbelegung |
|---|---|
| Von der App übergeben | ausdrücklich: `File::storeUpload($upload, ['original' => true])` bzw. `File::importFromDisk($disk, $path, ['original' => true])` |
| Mail-Anhang (mails-Package) | nach Absender (`Nfgarching\Mails\Services\OwnSender`): eigener Absender → aus (Archivfassung), externer Absender → an (Original). In AkV: Adressen aus `config/kanzlei.php` (`email`, `email_ausschluss`) und der Bearbeiter (`App\Mail\KanzleiAdressen`); ohne App-Resolver `MAIL_OWN_SENDERS` |
| Import aus einem Ordner | an, wenn die Disk in `FILES_ORIGINAL_DISKS` steht (z. B. ein Eingang für Fremddokumente) |
| Upload | an, wenn das Erzeugerprogramm laut PDF-Metadaten einen Eintrag aus `FILES_ORIGINAL_PRODUCERS` enthält |

```dotenv
FILES_ORIGINAL_DISKS=fremd_inbox
FILES_ORIGINAL_PRODUCERS=
```

Die frühere Option `'own' => bool` wird noch angenommen und umgekehrt
ausgewertet (`'own' => false` = Original Dokument).

Das erkannte Erzeugerprogramm steht in `custom_properties.original_document.producer`
und wird beim Upload als Hinweis am Schalter sowie in der Detailansicht
angezeigt – so lässt sich ablesen, welche Einträge für `FILES_ORIGINAL_PRODUCERS`
passen. Metadaten sind nur ein Hinweis.

### Schalter

- **Upload:** pro PDF ein Schalter „Original Dokument“.
- **Import:** ein Schalter „Original Dokumente“ für die ausgewählten Dateien.
- **Detailansicht** (Tab „Home“, Karte „Archiv“): jederzeit umschaltbar.

Wird der Schalter ausgeschaltet, nachdem die Archivfassung schon fertig ist,
wird sofort ersetzt. Wird er eingeschaltet, solange das Original noch im
Papierkorb liegt, wird das Original zurückgeholt; die Archivfassung bleibt
unter `generated_conversions.archive` erhalten. Nach der endgültigen Löschung
ist kein Zurückholen mehr möglich.

### Papierkorb bereinigen

`files:prune-trash` wird vom Package täglich um 03:17 Uhr eingeplant
(`FILES_TRASH_SCHEDULE=false` schaltet das ab). Dafür muss der Laravel-
Scheduler laufen (`php artisan schedule:work` bzw. Cron mit `schedule:run`).

```bash
sail artisan files:prune-trash --dry-run   # anzeigen, was gelöscht würde
sail artisan files:prune-trash             # Frist aus FILES_TRASH_DAYS
```

### Angaben in `custom_properties`

```json
"original_document": { "value": false, "source": "none", "producer": "Writer / LibreOffice 24.2" },
"archive": {
  "status": "archived", "replaced": true, "...": "…",
  "original": {
    "disk": "data", "path": "/2026/2026-11/123-AbC123.pdf",
    "trash_path": "/2026/trash/2026-11/123-AbC123.pdf",
    "sha256": "…", "size": 482113,
    "trashed_at": "2026-11-08T22:30:00+01:00", "purge_after": "2027-02-06"
  }
}
```

`source`: `user` (Schalter), `app`, `disk`, `producer`, `none`. Nach dem
Zurückholen steht in `original` `restored_at`, nach dem Löschen `purged_at`.
Altbestand mit dem früheren Schlüssel `own` („Eigenes Dokument“) wird gelesen:
Eine ausdrückliche Wahl (`source` `user`/`app`) gilt umgekehrt, sonst der Standard.

Im Model: `isOriginalDocument()`, `setOriginalDocument(bool)`, `originalDocument()`, `isOriginalReplaced()`,
`hasTrashedOriginal()`, `replaceOriginalWithArchive()`, `restoreOriginal()`.

## Benötigte Programme

Die Programme müssen in dem Container installiert sein, in dem der
**Queue-Worker** läuft.

| Programm | Zweck | Installation |
|---|---|---|
| `ocrmypdf` (mit Ghostscript) | OCR, PDF/A, Optimierung | `apt-get install -y ocrmypdf tesseract-ocr-deu jbig2enc pngquant` |
| `pyhanko` | Siegel + Zeitstempel | `pip install --break-system-packages pyhanko-cli` |
| `verapdf` | PDF/A-Validierung | Java + veraPDF-Installer (siehe unten) |

In aktuellen pyHanko-Versionen liegt das Kommandozeilenprogramm im separaten Paket
`pyhanko-cli`; `pip install pyhanko` allein liefert kein `pyhanko`-Kommando.

Ergänzung für das Dockerfile aus [OCR-Installation.md](OCR-Installation.md):

```dockerfile
RUN apt-get update \
    && apt-get install -y ocrmypdf tesseract-ocr tesseract-ocr-deu jbig2enc pngquant \
                          python3-pip default-jre-headless unzip \
    && pip install --break-system-packages pyhanko-cli \
    && curl -fsSL https://software.verapdf.org/releases/verapdf-installer.zip -o /tmp/verapdf.zip \
    && unzip -q /tmp/verapdf.zip -d /tmp/verapdf \
    && printf '<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n<AutomatedInstallation langpack="eng">\n<com.izforge.izpack.panels.htmlhello.HTMLHelloPanel id="welcome"/>\n<com.izforge.izpack.panels.target.TargetPanel id="install_dir"><installpath>/opt/verapdf</installpath></com.izforge.izpack.panels.target.TargetPanel>\n<com.izforge.izpack.panels.packs.PacksPanel id="sdk_pack_select"><pack index="0" name="veraPDF GUI" selected="false"/><pack index="1" name="veraPDF Mac and *nix Scripts" selected="true"/><pack index="2" name="veraPDF Validation model" selected="true"/><pack index="3" name="veraPDF Documentation" selected="false"/><pack index="4" name="veraPDF Sample Plugins" selected="false"/></com.izforge.izpack.panels.packs.PacksPanel>\n<com.izforge.izpack.panels.install.InstallPanel id="install"/>\n<com.izforge.izpack.panels.finish.FinishPanel id="finish"/>\n</AutomatedInstallation>\n' > /tmp/verapdf/auto-install.xml \
    && /tmp/verapdf/verapdf-greenfield-*/verapdf-install /tmp/verapdf/auto-install.xml \
    && ln -s /opt/verapdf/verapdf /usr/local/bin/verapdf \
    && rm -rf /tmp/verapdf /tmp/verapdf.zip
```

> Der veraPDF-Abschnitt folgt der automatischen Installation laut veraPDF-
> Dokumentation, ist aber noch nicht im eigenen Image erprobt. Bei Abweichungen
> die aktuelle Anleitung unter <https://docs.verapdf.org/install/> heranziehen.

Prüfen:

```bash
sail artisan files:archive --check
```

## Zertifikat

### Test: selbst erzeugtes Zertifikat

```bash
sail artisan files:make-test-certificate --org="Muster Kanzlei"
```

Der Befehl erzeugt eine Test-CA und ein damit ausgestelltes Siegelzertifikat
(`keyUsage: digitalSignature, nonRepudiation`) unter
`storage/app/private/files-signing/` und gibt die `.env`-Zeilen aus:

```dotenv
FILES_SIGN_CERT=app/private/files-signing/test-seal.p12
FILES_SIGN_PASSWORD=…
FILES_TSA_URL=https://freetsa.org/tsr
```

Der Privatschlüssel der Test-CA wird nicht aufbewahrt. Prüfprogramme (Adobe
Acrobat, EU-DSS) zeigen die Signatur als „Aussteller unbekannt“, bis
`test-ca.crt` dort als vertrauenswürdig importiert wird.

### Echtbetrieb

Für den Echtbetrieb ein **Siegelzertifikat** (fortgeschrittenes oder
qualifiziertes elektronisches Siegel) eines Vertrauensdiensteanbieters als
PKCS#12-Datei verwenden und gegebenenfalls einen qualifizierten
Zeitstempeldienst eintragen (siehe unten). Für das Siegel ändert sich nur die
`.env`; ein Zeitstempeldienst mit Anmeldung erfordert eine Code-Änderung.

### Zeitstempeldienst

Stand 11/2026, Recherche ohne Gewähr – vor einer Entscheidung neu prüfen.

**Derzeit:** FreeTSA (`https://freetsa.org/tsr`). Der Dienst ist kostenlos, ohne
Anmeldung und RFC-3161-konform, aber **nicht eIDAS-qualifiziert**. Die
PDF-Prüfung zeigt ihn nur dann als vertrauenswürdig an, wenn sein
Wurzelzertifikat als Vertrauensanker eingetragen ist (siehe
[PDF-Pruefung.md](PDF-Pruefung.md#vertrauensanker)).

**Gibt es einen kostenlosen qualifizierten Zeitstempeldienst? Nein.**

- Die freien RFC-3161-Dienste (FreeTSA, Open TSA, `time.certum.pl` u. a.) sind
  nicht qualifiziert. Ein Abgleich von neun freien Diensten mit der
  EU-Vertrauensliste (10/2026) fand keinen qualifizierten; teils betreiben
  dieselben Firmen kostenpflichtig auch qualifizierte Dienste.
- Qualifizierte Anbieter (z. B. AlfaTrust, GlobalTrust, qtsa.eu) bieten höchstens
  Testkontingente (AlfaTrust: 50 Zeitstempel), danach kostenpflichtig, meist mit
  Konto/Zugangsdaten. Preise sind nicht recherchiert.

**Rechtliche Wirkung (Art. 41 eIDAS-VO):**

- Ein einfacher Zeitstempel darf nicht allein deshalb als Beweis abgelehnt
  werden, weil er elektronisch ist (Abs. 1) – er unterliegt der freien
  Beweiswürdigung.
- Nur der **qualifizierte** Zeitstempel hat EU-weit die Vermutung, dass Datum und
  Uhrzeit richtig sind und die Daten seitdem unverändert sind (Abs. 2).

**Bewertung:** Für das eigene Archivsiegel genügt FreeTSA in der Regel. Es
belegt, dass die Archivfassung seit dem Zeitpunkt unverändert ist; die GoBD
schreiben keinen qualifizierten Zeitstempel vor. Ein qualifizierter Dienst
lohnt sich erst, wenn das Datum ohne weiteren Nachweis anerkannt werden soll
(Gericht, Gegenseite) oder für eine Beweiswerterhaltung nach BSI TR-ESOR.

**Wechsel auf einen anderen Dienst:**

1. Status in der EU-Vertrauensliste prüfen: <https://eidas.ec.europa.eu/efda/trust-services/browse/eidas/tls>
   – Diensttyp „Qualified time stamp“.
2. Anmeldung klären: `PdfSigner` übergibt nur `--timestamp-url` an die
   pyHanko-CLI (`FILES_TSA_URL`). Verlangt der Dienst Benutzer/Passwort oder ein
   Client-Zertifikat, muss `PdfSigner` erweitert werden (pyHanko kann das über
   eine Konfigurationsdatei bzw. `HTTPTimeStamper`) – Zugangsdaten nur über
   `.env`.
3. Wurzelzertifikat des Dienstes in `FILES_INSPECT_TRUST` eintragen, sofern es
   nicht im Zertifikatsspeicher des Systems liegt.
4. Bereits archivierte Dateien behalten ihren FreeTSA-Zeitstempel; eine
   Nachsiegelung (Dokumentzeitstempel, PAdES B-LTA) gibt es bisher nicht.

Quellen:
[Abgleich freier TSA mit der EU-Vertrauensliste](https://dev.to/lizhuojunx86/i-checked-nine-free-rfc-3161-endpoints-against-the-eu-trusted-list-none-is-qualified-five-of-the-djc),
[Open TSA](https://open-tsa.eu/kostenloser-rfc-3161-zeitstempel-dienst-europa/),
[Sealdoc: RFC-3161-Dienste für die EU](https://www.sealdoc.eu/blog/best-rfc3161-timestamp-services-eu),
[Provlyn: eIDAS Qualified Timestamp](https://www.provlyn.com/blog/eidas-qualified-timestamp),
[GlobalTrust Qualified Timestamp](https://globaltrust.eu/en/globaltrust-qualified-timestamp-qualified-time-stamp-according-to-eidas-vo-for-audit-proof-document-management/),
[qtsa.eu](https://qtsa.eu/).

Ein **beA-Softwarezertifikat ist dafür ungeeignet**: Es dient der Anmeldung am
beA und müsste samt PIN dauerhaft auf dem Server liegen – wer den Server
übernimmt, hätte Zugriff auf das Anwaltspostfach. Zudem ist es nach seiner
Schlüsselverwendung nicht für Dokumentsignaturen vorgesehen.

Das Zertifikat gehört **nie** ins Repository. Das Passwort wird pyHanko über
stdin übergeben und erscheint weder in der Prozessliste noch im Log.

## Konfiguration

Alle Werte in `config/files.php` unter `archive`:

| `.env` | Standard | Bedeutung |
|---|---|---|
| `FILES_ARCHIVE_ENABLED` | `true` | Archivierung insgesamt |
| `FILES_ARCHIVE_QUEUE` | – | eigene Queue für `ArchivePdf` |
| `FILES_PDFA_ENABLED` | `true` | PDF/A-Umwandlung |
| `FILES_PDFA_OUTPUT_TYPE` | `pdfa-2` | `pdfa-1`, `pdfa-2`, `pdfa-3` (gilt auch für die OCR) |
| `FILES_PDFA_OPTIMIZE` | `1` | 0–3; Stufe 2/3 benötigt `jbig2enc` und `pngquant` |
| `FILES_PDFA_VALIDATE` | `true` | Validierung mit veraPDF |
| `FILES_PDFA_FLAVOUR` | `2b` | veraPDF-Profil, passend zu `FILES_PDFA_OUTPUT_TYPE` |
| `FILES_SIGN_ENABLED` | `true` | Siegel |
| `FILES_SIGN_CERT` | – | PKCS#12-Datei, relativ zu `storage/` oder absolut |
| `FILES_SIGN_PASSWORD` | – | Passwort der PKCS#12-Datei |
| `FILES_TSA_URL` | – | RFC-3161-Zeitstempeldienst; leer = ohne Zeitstempel |
| `FILES_SIGN_FIELD` | `Archivsiegel` | Name des (unsichtbaren) Signaturfelds |
| `FILES_SIGN_REASON` | `Archivierung` | Grund in der Signatur |
| `FILES_SIGN_LOCATION` / `FILES_SIGN_CONTACT` | – | optionale Angaben |
| `FILES_SIGN_LTV` | `false` | Sperrinformationen einbetten (nur mit öffentlicher PKI) |
| `FILES_ARCHIVE_REPLACE` | `archive` | Original ersetzen: `never`, `archive` (außer „Original Dokument“), `always` |
| `FILES_ARCHIVE_REPLACE_REQUIRE_VALID` | `true` | nur ersetzen, wenn veraPDF `compliant` meldet |
| `FILES_ORIGINAL_DISKS` | – | Import-Disks, deren Dateien als „Original Dokument“ vorbelegt werden |
| `FILES_ORIGINAL_PRODUCERS` | – | Erzeugerprogramme (Teilstrings), die als „Original Dokument“ vorbelegt werden |
| `FILES_TRASH_DAYS` | `90` | Aufbewahrung ersetzter Originale im Papierkorb |
| `FILES_TRASH_SCHEDULE` | `true` | `files:prune-trash` täglich einplanen |

## Vorhandene PDFs nachträglich archivieren

```bash
sail artisan files:archive --all          # alle PDFs ohne Archivfassung (Queue)
sail artisan files:archive 12 13 --sync   # einzelne Dateien sofort
sail artisan files:archive --all --force  # alle erneut
```

## Signatur prüfen

Im Tab „PDF“ der Datei-Detailseite – siehe [PDF-Pruefung.md](PDF-Pruefung.md). Auf der Kommandozeile:

```bash
pyhanko sign validate --pretty-print --trust storage/app/private/files-signing/test-ca.crt --trust-replace archive.pdf
```
