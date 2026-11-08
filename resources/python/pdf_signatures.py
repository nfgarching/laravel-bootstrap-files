"""
Prüft alle Signaturen eines PDFs mit pyHanko und gibt das Ergebnis als JSON aus.

Aufruf (aus Nfgarching\\Files\\Services\\Inspection\\SignatureInspector):

    python pdf_signatures.py [--trust CERT ...] [--fetch] DATEI.pdf

--trust   zusätzliche Vertrauensanker (PEM oder DER), z. B. die Test-CA
--fetch   Sperrinformationen (OCSP/CRL) und fehlende Zwischenzertifikate
          aus dem Netz laden; sonst wird nur mit eingebetteten Daten geprüft

Die Bewertung ist bewusst in Einzelaspekte zerlegt (Integrität, Vertrauen,
Zeitstempel, Änderungen), damit die Anwendung kein irreführendes
Gesamt-„ungültig“ zeigt, wenn z. B. nur der Zeitstempeldienst unbekannt ist.

Ausgabe: {"ok": true, "signatures": [...], "dss": bool, ...}
         {"ok": false, "error": "..."} bei Fehlern
"""

import argparse
import hashlib
import json
import sys
from datetime import datetime

try:
    from asn1crypto import pem, x509
    from pyhanko.pdf_utils.reader import PdfFileReader
    from pyhanko.sign.validation import validate_pdf_signature, validate_pdf_timestamp
    from pyhanko_certvalidator import ValidationContext
except ImportError as exc:  # pragma: no cover - nur ohne pyHanko-Installation
    print(json.dumps({"ok": False, "notool": True, "error": f"pyHanko nicht importierbar: {exc}"}))
    sys.exit(0)

# DER-kodierte OIDs der QC-Statements (ETSI EN 319 412-5)
QC_OIDS = {
    "compliance": bytes.fromhex("060604008e460101"),  # 0.4.0.1862.1.1 QcCompliance
    "qscd": bytes.fromhex("060604008e460104"),        # 0.4.0.1862.1.4 QcSSCD
    "esign": bytes.fromhex("060704008e46010601"),     # QcType eSign
    "eseal": bytes.fromhex("060704008e46010602"),     # QcType eSeal
    "web": bytes.fromhex("060704008e46010603"),       # QcType Web
}
QC_STATEMENTS_OID = "1.3.6.1.5.5.7.1.3"


def iso(value):
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.isoformat()
    return str(value)


def enum_name(value):
    if value is None:
        return None
    return getattr(value, "name", str(value))


def load_trust(paths):
    certs, missing = [], []
    for path in paths:
        try:
            with open(path, "rb") as fh:
                data = fh.read()
        except OSError:
            missing.append(path)
            continue
        if pem.detect(data):
            for _, _, der in pem.unarmor(data, multiple=True):
                certs.append(x509.Certificate.load(der))
        else:
            certs.append(x509.Certificate.load(data))
    return certs, missing


def qc_info(cert):
    """QC-Statements: nur die Angabe im Zertifikat, keine Prüfung gegen die EU-Vertrauensliste."""
    for ext in cert["tbs_certificate"]["extensions"] or []:
        if ext["extn_id"].dotted == QC_STATEMENTS_OID:
            raw = ext["extn_value"].contents
            return {key: oid in raw for key, oid in QC_OIDS.items()}
    return None


def cert_info(cert):
    if cert is None:
        return None
    try:
        key_usage = sorted(cert.key_usage_value.native) if cert.key_usage_value else []
    except Exception:
        key_usage = []
    try:
        ext_key_usage = list(cert.extended_key_usage_value.native) if cert.extended_key_usage_value else []
    except Exception:
        ext_key_usage = []
    public_key = cert.public_key
    validity = cert["tbs_certificate"]["validity"]
    return {
        "subject": cert.subject.human_friendly,
        "common_name": cert.subject.native.get("common_name"),
        "organization": cert.subject.native.get("organization_name"),
        "issuer": cert.issuer.human_friendly,
        "serial": format(cert.serial_number, "x"),
        "not_before": iso(validity["not_before"].native),
        "not_after": iso(validity["not_after"].native),
        "sha256": hashlib.sha256(cert.dump()).hexdigest(),
        "key_algorithm": public_key.algorithm,
        "key_size": public_key.bit_size,
        "key_usage": key_usage,
        "ext_key_usage": ext_key_usage,
        "self_signed": cert.self_signed != "no",
        "qc": qc_info(cert),
    }


def path_info(status):
    path = getattr(status, "validation_path", None)
    if path is None:
        return None
    try:
        anchor = path.trust_anchor
        name = anchor.authority.name.native.get("common_name") or anchor.authority.name.human_friendly
    except Exception:
        name = None
    return {"trust_anchor": name, "length": getattr(path, "pkix_len", None)}


def timestamp_info(ts):
    if ts is None:
        return None
    return {
        "time": iso(ts.timestamp),
        "intact": bool(ts.intact),
        "valid": bool(ts.valid),
        "trusted": bool(ts.trusted),
        "tsa": cert_info(ts.signing_cert),
        "trust_problem": enum_name(ts.trust_problem_indic),
    }


def widget_info(sig):
    """Sichtbar ist ein Signaturfeld mit einem Rechteck größer null."""
    rect = sig.sig_field.get("/Rect")
    try:
        x1, y1, x2, y2 = (float(v) for v in rect)
        return {"visible": abs(x2 - x1) > 0 and abs(y2 - y1) > 0}
    except Exception:
        return {"visible": False}


def signature_entry(sig, reader, vc):
    obj = sig.sig_object
    sub_filter = obj.get("/SubFilter")
    entry = {
        "field": sig.field_name,
        "type": "timestamp" if sig.sig_object_type == "/DocTimeStamp" else "signature",
        "sub_filter": str(sub_filter) if sub_filter is not None else None,
        "pades": str(sub_filter) == "/ETSI.CAdES.detached",
        "reason": str(obj["/Reason"]) if "/Reason" in obj else None,
        "location": str(obj["/Location"]) if "/Location" in obj else None,
        "contact": str(obj["/ContactInfo"]) if "/ContactInfo" in obj else None,
        "name": str(obj["/Name"]) if "/Name" in obj else None,
        "byte_range": [int(v) for v in obj["/ByteRange"]],
        "certification": enum_name(sig.docmdp_level),
        **widget_info(sig),
    }

    try:
        if entry["type"] == "timestamp":
            status = validate_pdf_timestamp(sig, validation_context=vc)
            ts = None
        else:
            status = validate_pdf_signature(sig, signer_validation_context=vc, ts_validation_context=vc)
            ts = status.timestamp_validity
    except Exception as exc:
        entry["error"] = f"{type(exc).__name__}: {exc}"
        entry["signer"] = cert_info(sig.signer_cert)
        return entry

    entry.update({
        "intact": bool(status.intact),
        "valid": bool(status.valid),
        "trusted": bool(status.trusted),
        "revoked": bool(getattr(status, "revoked", False)),
        "trust_problem": enum_name(status.trust_problem_indic),
        "bottom_line": bool(status.bottom_line),
        "md_algorithm": status.md_algorithm,
        "mechanism": status.pkcs7_signature_mechanism,
        "signer": cert_info(status.signing_cert),
        "path": path_info(status),
        "coverage": enum_name(getattr(status, "coverage", None)),
        "modification_level": enum_name(getattr(status, "modification_level", None)),
        "docmdp_ok": getattr(status, "docmdp_ok", None),
        "signer_reported_time": iso(getattr(status, "signer_reported_dt", None)),
        "timestamp": timestamp_info(ts) if entry["type"] == "signature" else {
            "time": iso(getattr(status, "timestamp", None)),
            "intact": bool(status.intact),
            "valid": bool(status.valid),
            "trusted": bool(status.trusted),
            "tsa": cert_info(status.signing_cert),
            "trust_problem": enum_name(status.trust_problem_indic),
        },
    })
    return entry


def pades_level(signatures, has_dss):
    """Grobe Einordnung des PAdES-Levels der ersten (ältesten) Signatur."""
    sigs = [s for s in signatures if s["type"] == "signature" and s.get("pades")]
    if not sigs:
        return None
    first = sigs[0]
    level = "B-T" if first.get("timestamp") else "B-B"
    if level == "B-T" and has_dss:
        level = "B-LT"
        if any(s["type"] == "timestamp" for s in signatures[signatures.index(first) + 1:]):
            level = "B-LTA"
    return level


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf")
    parser.add_argument("--trust", action="append", default=[])
    parser.add_argument("--fetch", action="store_true")
    args = parser.parse_args()

    trust, missing = load_trust(args.trust)

    try:
        with open(args.pdf, "rb") as fh:
            reader = PdfFileReader(fh, strict=False)
            vc = ValidationContext(
                extra_trust_roots=trust,
                allow_fetching=args.fetch,
                revocation_mode="soft-fail",
            )
            signatures = [signature_entry(sig, reader, vc) for sig in reader.embedded_signatures]
            has_dss = "/DSS" in reader.root
    except Exception as exc:
        print(json.dumps({"ok": False, "error": f"{type(exc).__name__}: {exc}"}))
        return

    print(json.dumps({
        "ok": True,
        "signatures": signatures,
        "dss": has_dss,
        "pades_level": pades_level(signatures, has_dss),
        "trust_missing": missing,
        "fetch": args.fetch,
    }, ensure_ascii=False))


if __name__ == "__main__":
    main()
