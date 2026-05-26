"""
DoH Inbound service — visibilidade e gestão do cert TLS do servidor DoH/DoT.

Unbound expõe DoH na porta `https-port` (default 8443) e DoT na `tls-port`
(853). Ambos usam o mesmo cert configurado por `tls-service-key/pem` em
`general.conf`.

Esta camada é só leitura + geração de self-signed (não toca em portas ou
binds — isso é gerenciado por includes que o user controla manualmente).
"""

from __future__ import annotations

import asyncio
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import structlog
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID

log = structlog.get_logger(__name__)

CERT_PATH = Path("/etc/unbound/certs/dashboard.crt")
KEY_PATH = Path("/etc/unbound/certs/dashboard.key")
GENERAL_CONF = Path("/etc/unbound/includes/general.conf")


def _parse_general_conf() -> dict[str, str]:
    """Lê general.conf e extrai pares chave: valor relevantes pra DoH/DoT."""
    out = {"tls_port": "853", "https_port": "8443", "tls_cert_path": str(CERT_PATH), "tls_key_path": str(KEY_PATH)}
    if not GENERAL_CONF.exists():
        return out
    txt = GENERAL_CONF.read_text(encoding="utf-8", errors="replace")
    for line in txt.splitlines():
        line = line.strip()
        if line.startswith("#") or ":" not in line:
            continue
        k, _, v = line.partition(":")
        k = k.strip()
        v = v.strip().strip('"')
        if k == "tls-port":
            out["tls_port"] = v
        elif k == "https-port":
            out["https_port"] = v
        elif k == "tls-service-pem":
            out["tls_cert_path"] = v
        elif k == "tls-service-key":
            out["tls_key_path"] = v
    return out


def _read_cert_info(path: Path) -> dict[str, Any]:
    if not path.exists():
        return {"present": False, "path": str(path)}
    try:
        pem = path.read_bytes()
        cert = x509.load_pem_x509_certificate(pem)
    except Exception as exc:  # noqa: BLE001
        return {"present": True, "path": str(path), "parse_error": str(exc)}

    subject = cert.subject.rfc4514_string()
    issuer = cert.issuer.rfc4514_string()
    not_before = cert.not_valid_before_utc if hasattr(cert, "not_valid_before_utc") else cert.not_valid_before.replace(tzinfo=timezone.utc)
    not_after = cert.not_valid_after_utc if hasattr(cert, "not_valid_after_utc") else cert.not_valid_after.replace(tzinfo=timezone.utc)
    now = datetime.now(timezone.utc)
    days_left = (not_after - now).days

    san: list[str] = []
    try:
        ext = cert.extensions.get_extension_for_class(x509.SubjectAlternativeName)
        san = [str(n.value) for n in ext.value]
    except x509.ExtensionNotFound:
        pass

    fp = cert.fingerprint(hashes.SHA256()).hex(":").upper()
    # quebra a cada 2 chars: AA:BB:...
    fp = ":".join(fp[i:i+2] for i in range(0, len(fp), 2)) if ":" not in fp else fp

    return {
        "present": True,
        "path": str(path),
        "subject": subject,
        "issuer": issuer,
        "not_before": not_before.isoformat(),
        "not_after": not_after.isoformat(),
        "days_left": days_left,
        "expired": days_left < 0,
        "expiring_soon": 0 <= days_left <= 30,
        "san": san,
        "fingerprint_sha256": fp,
        "self_signed": subject == issuer,
    }


async def info() -> dict[str, Any]:
    """Snapshot completo: portas + cert info + URLs sugeridas."""
    conf = _parse_general_conf()
    cert_info = _read_cert_info(Path(conf["tls_cert_path"]))
    key_path = Path(conf["tls_key_path"])
    return {
        "ports": {"tls": int(conf["tls_port"]), "https": int(conf["https_port"])},
        "paths": {"cert": conf["tls_cert_path"], "key": conf["tls_key_path"]},
        "cert": cert_info,
        "key_present": key_path.exists(),
        "key_mode": oct(key_path.stat().st_mode & 0o777) if key_path.exists() else None,
        "doh_path": "/dns-query",  # RFC 8484 default — não há config no Unbound pra mudar
    }


async def _run(cmd: list[str]) -> tuple[int, str, str]:
    proc = await asyncio.create_subprocess_exec(
        *cmd,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    out, err = await proc.communicate()
    return proc.returncode or 0, out.decode("utf-8", errors="replace"), err.decode("utf-8", errors="replace")


async def generate_self_signed(common_name: str, days: int = 365, restart: bool = False) -> dict[str, Any]:
    """Gera novo par RSA 2048 + self-signed cert e instala no path padrão.

    Sandbox systemd pode bloquear write em /etc/unbound — então geramos em
    /var/www/html/unbound-dashboard/src/data/tmp/ e copiamos via `sudo cp`,
    mesmo padrão de dns_security_service.apply().

    Se `restart=True`, faz systemctl restart unbound pra recarregar TLS.
    Sem restart, o Unbound continua usando o cert antigo até o próximo reload.
    """
    # Validação leve
    cn = (common_name or "").strip()
    if not cn or len(cn) > 255 or not re.match(r"^[A-Za-z0-9.\-_*]+$", cn):
        return {"ok": False, "error": "common_name inválido"}
    days = max(7, min(3650, int(days)))

    # Geração
    private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    subject = issuer = x509.Name([
        x509.NameAttribute(NameOID.COMMON_NAME, cn),
        x509.NameAttribute(NameOID.ORGANIZATION_NAME, "Unbound Dashboard"),
    ])
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder()
        .subject_name(subject)
        .issuer_name(issuer)
        .public_key(private_key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now)
        .not_valid_after(now.replace(microsecond=0) + __import__("datetime").timedelta(days=days))
        .add_extension(x509.SubjectAlternativeName([x509.DNSName(cn)]), critical=False)
        .add_extension(x509.BasicConstraints(ca=False, path_length=None), critical=True)
        .sign(private_key, hashes.SHA256())
    )

    tmp_dir = Path("/var/www/html/unbound-dashboard/src/data/tmp")
    tmp_dir.mkdir(parents=True, exist_ok=True)
    tmp_crt = tmp_dir / "doh_dashboard.crt"
    tmp_key = tmp_dir / "doh_dashboard.key"

    tmp_crt.write_bytes(cert.public_bytes(serialization.Encoding.PEM))
    tmp_key.write_bytes(
        private_key.private_bytes(
            encoding=serialization.Encoding.PEM,
            format=serialization.PrivateFormat.TraditionalOpenSSL,
            encryption_algorithm=serialization.NoEncryption(),
        )
    )
    tmp_crt.chmod(0o644)
    tmp_key.chmod(0o600)

    # sudo cp pros paths reais
    rc1, _, e1 = await _run(["sudo", "/usr/bin/cp", str(tmp_crt), str(CERT_PATH)])
    if rc1 != 0:
        return {"ok": False, "stage": "cp_cert", "error": e1}
    rc2, _, e2 = await _run(["sudo", "/usr/bin/cp", str(tmp_key), str(KEY_PATH)])
    if rc2 != 0:
        return {"ok": False, "stage": "cp_key", "error": e2}
    # Ajusta dono pra unbound — best-effort (pode falhar se sudoers não autorizar)
    await _run(["sudo", "/bin/chown", "unbound:unbound", str(KEY_PATH)])
    await _run(["sudo", "/bin/chmod", "640", str(KEY_PATH)])

    restart_result = None
    if restart:
        rc3, _, e3 = await _run(["sudo", "/usr/bin/systemctl", "restart", "unbound"])
        restart_result = {"rc": rc3, "error": e3 if rc3 != 0 else None}

    log.info("doh_inbound.gen_cert.ok", cn=cn, days=days, restarted=restart)
    return {
        "ok": True,
        "common_name": cn,
        "days_valid": days,
        "cert_path": str(CERT_PATH),
        "key_path": str(KEY_PATH),
        "restart": restart_result,
    }
