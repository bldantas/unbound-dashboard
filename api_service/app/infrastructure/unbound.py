"""Client do unbound-control — coleta stats e parseia output key=value."""

from __future__ import annotations

from app.core.config import settings
from app.infrastructure import shell


def _parse_stats(output: str) -> dict[str, float]:
    """
    Parseia saída do `unbound-control stats_noreset`. Cada linha é `key=value`.
    Valores numéricos viram float (preservar precisão pra time/latency).
    """
    result: dict[str, float] = {}
    for line in output.splitlines():
        if "=" not in line:
            continue
        key, sep, value = line.partition("=")
        key = key.strip()
        value = value.strip()
        if not key:
            continue
        try:
            result[key] = float(value)
        except ValueError:
            # Valor não-numérico (raro em stats); ignora
            continue
    return result


async def stats_single(conf_path: str | None = None) -> dict[str, float]:
    """
    Roda `unbound-control [-c CONF] stats_noreset` e retorna dict parseado.
    Sem multicore — usado pra single instance OU para uma das instâncias quando
    em multicore (caller agrega).
    """
    args = []
    if conf_path:
        args.extend(["-c", conf_path])
    args.append("stats_noreset")
    output = await shell.run(settings.unbound_control, *args, timeout_s=10.0)
    return _parse_stats(output)


async def stats_aggregated(instances: int = 1) -> dict[str, float]:
    """
    Roda `unbound-control` em todas as instâncias (`unbound01.conf` ... `unbound{N}.conf`)
    e agrega. Para counters: soma. Para time/avg/median/uptime: média OU max.

    Política de agregação:
      - chaves contendo `.time.up` ou `.time.elapsed`: max (uptime mais alto)
      - chaves matching `.time.(avg|median)$`: média entre instâncias
      - resto: soma
    """
    if instances <= 1:
        return await stats_single()

    aggregated: dict[str, float] = {}
    for i in range(1, instances + 1):
        conf = f"/etc/unbound/unbound{i:02d}.conf"
        instance_stats = await stats_single(conf)
        for key, value in instance_stats.items():
            if key not in aggregated:
                aggregated[key] = value
            elif "time.up" in key or "time.elapsed" in key:
                aggregated[key] = max(aggregated[key], value)
            else:
                aggregated[key] += value

    # Tempo médio (avg/median) precisa ser dividido pelas instâncias
    for key in list(aggregated.keys()):
        if key.endswith((".time.avg", ".time.median")):
            aggregated[key] = aggregated[key] / instances

    return aggregated
