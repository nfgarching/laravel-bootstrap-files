# Archivierung: PDF/A, Siegel und Zeitstempel

Jedes hochgeladene PDF erhält nach der OCR-Verarbeitung eine **Archivfassung**:

```
Original (unverändert) → OCR → PDF/A-2b + Optimierung → Validierung → Siegel + Zeitstempel
                         ocrmypdf                        veraPDF      pyHanko (PAdES, RFC 3161)
```

- Das **Original** bleibt bitgenau erhalten; seine SHA-256-Prüfsumme wird gespeichert.
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
PKCS#12-Datei verwenden und einen qualifizierten Zeitstempeldienst eintragen.
Es ändert sich nur die `.env`.

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

## Vorhandene PDFs nachträglich archivieren

```bash
sail artisan files:archive --all          # alle PDFs ohne Archivfassung (Queue)
sail artisan files:archive 12 13 --sync   # einzelne Dateien sofort
sail artisan files:archive --all --force  # alle erneut
```

## Signatur prüfen

```bash
pyhanko sign validate --pretty-print --trust storage/app/private/files-signing/test-ca.crt --trust-replace archive.pdf
```
