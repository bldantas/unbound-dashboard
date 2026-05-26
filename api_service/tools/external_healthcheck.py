#!/usr/bin/env python3
"""
External healthcheck — script standalone pra rodar EM OUTRA MÁQUINA.

Faz queries DNS contra o servidor Unbound monitorado e POSTa o resultado
no endpoint /api/v1/external-health/report do dashboard. Permite ver
"de fora" se o DNS está respondendo.

Deps: só stdlib (dnspython opcional pra DNS queries; sem ele cai pra
socket cru). httpx ou requests pra POST — usa stdlib `urllib.request`
pra zero deps.

Uso:
    python3 external_healthcheck.py \\
        --dns-server 192.168.1.10:53 \\
        --query-name google.com \\
        --probe-source monitor-aws-east \\
        --api-url https://dashboard.example.com \\
        --api-token <X-Api-Token>

Crontab sugerido (1x/min):
    * * * * * /usr/bin/python3 /opt/external_healthcheck.py --dns-server ... 2>&1 | logger -t ext-hc
"""

from __future__ import annotations

import argparse
import datetime
import json
import socket
import struct
import sys
import time
import urllib.request


def dns_query_a(server_ip: str, server_port: int, name: str, timeout: float = 5.0) -> tuple[bool, int, bool, str]:
    """Query DNS A record direta via socket UDP. Retorna (success, latency_ms,
    response_correct, error).

    response_correct = recebeu pelo menos 1 ANSWER e RCODE=NOERROR (0).
    """
    # Monta DNS query manualmente (zero deps)
    tid = struct.pack(">H", 0x1234)
    flags = struct.pack(">H", 0x0100)  # RD=1
    counts = struct.pack(">HHHH", 1, 0, 0, 0)
    qname = b""
    for label in name.encode().split(b"."):
        qname += bytes([len(label)]) + label
    qname += b"\x00"
    qtype_qclass = struct.pack(">HH", 1, 1)  # A, IN
    pkt = tid + flags + counts + qname + qtype_qclass

    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.settimeout(timeout)
    started = time.time()
    try:
        sock.sendto(pkt, (server_ip, server_port))
        data, _ = sock.recvfrom(4096)
        latency_ms = int((time.time() - started) * 1000)
        # Parse RCODE (último nibble do byte 3) + answer count (bytes 6-7)
        rcode = data[3] & 0x0F
        ancount = struct.unpack(">H", data[6:8])[0]
        correct = rcode == 0 and ancount > 0
        return True, latency_ms, correct, ""
    except socket.timeout:
        return False, int(timeout * 1000), False, "timeout"
    except Exception as exc:
        return False, int((time.time() - started) * 1000), False, f"{type(exc).__name__}: {exc}"
    finally:
        sock.close()


def post_report(api_url: str, api_token: str, payload: dict, timeout: float = 10.0) -> tuple[bool, str]:
    body = json.dumps(payload).encode()
    req = urllib.request.Request(
        api_url.rstrip("/") + "/api/v1/external-health/report",
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "X-Api-Token": api_token,
            "Authorization": f"Bearer {api_token}",  # tenta as duas formas
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return 200 <= resp.status < 300, resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        return False, f"HTTP {exc.code}: {exc.read().decode('utf-8', errors='replace')[:200]}"
    except Exception as exc:  # noqa: BLE001
        return False, f"{type(exc).__name__}: {exc}"


def main() -> int:
    p = argparse.ArgumentParser(description="External DNS healthcheck (probes p/ dashboard)")
    p.add_argument("--dns-server", required=True, help="IP:porta do Unbound (ex: 192.168.1.10:53)")
    p.add_argument("--query-name", default="google.com", help="Nome a resolver (default google.com)")
    p.add_argument("--probe-source", required=True, help="Identificador do monitor (ex: monitor-aws-east)")
    p.add_argument("--api-url", required=True, help="Base URL do dashboard (ex: https://dashboard.example.com)")
    p.add_argument("--api-token", required=True, help="X-Api-Token autenticado pra POST")
    p.add_argument("--target-host", default="", help="Hostname do servidor monitorado (informativo)")
    p.add_argument("--timeout", type=float, default=5.0, help="Timeout DNS em segundos")
    p.add_argument("--quiet", action="store_true", help="Sem stdout em sucesso")
    args = p.parse_args()

    host, _, port = args.dns_server.partition(":")
    port = int(port or "53")

    success, latency_ms, correct, error = dns_query_a(
        host, port, args.query_name, timeout=args.timeout,
    )

    payload = {
        "probe_source": args.probe_source,
        "target_host": args.target_host or args.dns_server,
        "query_name": args.query_name,
        "success": success,
        "latency_ms": latency_ms,
        "response_correct": correct,
        "error": error or None,
        "probed_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
    }

    ok, resp = post_report(args.api_url, args.api_token, payload)
    if not args.quiet or not (success and ok):
        sys.stderr.write(
            f"[{datetime.datetime.now().isoformat(timespec='seconds')}] "
            f"dns={success} latency={latency_ms}ms correct={correct} "
            f"err={error!r} report_ok={ok}\n"
        )
    return 0 if (success and ok) else 1


if __name__ == "__main__":
    sys.exit(main())
