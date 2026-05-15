"""Testes do services/updater.py — sem dep de rede (httpx mockado)."""

from __future__ import annotations

import json
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, patch

import pytest


def test_parse_semver():
    from app.services.updater import _parse_semver

    assert _parse_semver("v2.16.3") == (2, 16, 3)
    assert _parse_semver("2.16.3") == (2, 16, 3)
    assert _parse_semver("v0.0.1") == (0, 0, 1)
    assert _parse_semver("invalid") is None
    assert _parse_semver("v2.16") is None
    assert _parse_semver("v2.16.3-beta") is None


def test_is_newer():
    from app.services.updater import _is_newer

    assert _is_newer("2.16.4", "2.16.3") is True
    assert _is_newer("2.17.0", "2.16.3") is True
    assert _is_newer("3.0.0", "2.99.99") is True
    assert _is_newer("2.16.3", "2.16.3") is False
    assert _is_newer("2.16.2", "2.16.3") is False
    assert _is_newer("invalid", "2.16.3") is False


def test_is_major_bump():
    from app.services.updater import _is_major_bump

    assert _is_major_bump("3.0.0", "2.16.3") is True
    assert _is_major_bump("2.17.0", "2.16.3") is False
    assert _is_major_bump("2.16.4", "2.16.3") is False
    assert _is_major_bump("10.0.0", "9.99.99") is True


def test_verify_sha256_good(tmp_path):
    from app.services.updater import _verify_sha256

    f = tmp_path / "data.bin"
    f.write_bytes(b"hello world")
    # echo -n "hello world" | sha256sum
    expected = "b94d27b9934d3e08a52e52d7da7dabfac484efe37a5380ee9088f7ace2efcde9"
    sha = tmp_path / "data.bin.sha256"
    sha.write_text(f"{expected}  data.bin\n")
    assert _verify_sha256(f, sha) is True


def test_verify_sha256_mismatch(tmp_path):
    from app.services.updater import _verify_sha256

    f = tmp_path / "data.bin"
    f.write_bytes(b"hello world")
    sha = tmp_path / "data.bin.sha256"
    sha.write_text("0000000000000000000000000000000000000000000000000000000000000000  data.bin\n")
    assert _verify_sha256(f, sha) is False


def test_verify_sha256_missing_files(tmp_path):
    from app.services.updater import _verify_sha256

    f = tmp_path / "missing.bin"
    sha = tmp_path / "missing.bin.sha256"
    assert _verify_sha256(f, sha) is False


def test_find_assets_complete():
    from app.services.updater import _find_assets

    release = {
        "assets": [
            {"name": "unbound-dashboard-update-v2.16.3-123.tar.gz", "browser_download_url": "http://x/tar"},
            {"name": "unbound-dashboard-update-v2.16.3-123.tar.gz.sha256", "browser_download_url": "http://x/sha"},
        ]
    }
    tar, sha = _find_assets(release)
    assert tar is not None and tar["name"].endswith(".tar.gz")
    assert sha is not None and sha["name"].endswith(".sha256")


def test_find_assets_incomplete():
    from app.services.updater import _find_assets

    # Só tarball, sem sha
    release = {"assets": [{"name": "x.tar.gz", "browser_download_url": "http://x"}]}
    tar, sha = _find_assets(release)
    assert tar is None and sha is None


def test_infer_status_success(tmp_path):
    from app.services.updater import _infer_status_from_log

    log = tmp_path / "u.log"
    log.write_text("blah\n[OK] some step\n╔════╗\n║   Update concluído                             ║\n╚════╝\n")
    assert _infer_status_from_log(log) == "succeeded"


def test_infer_status_rolled_back(tmp_path):
    from app.services.updater import _infer_status_from_log

    log = tmp_path / "u.log"
    log.write_text("erro X\n  ROLLBACK CONCLUÍDO — sistema voltou à versão anterior\n")
    assert _infer_status_from_log(log) == "rolled_back"


def test_infer_status_rollback_failed(tmp_path):
    from app.services.updater import _infer_status_from_log

    log = tmp_path / "u.log"
    log.write_text("erro grave\nROLLBACK FAILED — estado inconsistente\n")
    assert _infer_status_from_log(log) == "rollback_failed"


def test_infer_status_default_failed(tmp_path):
    from app.services.updater import _infer_status_from_log

    log = tmp_path / "u.log"
    log.write_text("começou\nfez algumas coisas\nparou no meio\n")
    assert _infer_status_from_log(log) == "failed"


@pytest.mark.asyncio
async def test_check_for_updates_github_off(monkeypatch):
    """check retorna current + error quando GitHub indisponível e cache vazio."""
    from app.services import updater

    async def _raise(*_a, **_kw):
        raise updater.GitHubUnavailable("simulated")

    monkeypatch.setattr(updater, "fetch_latest_release", _raise)
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.16.3")

    result = await updater.check_for_updates()
    assert result["current"] == "2.16.3"
    assert result["has_update"] is False
    assert "simulated" in result["error"]


@pytest.mark.asyncio
async def test_check_for_updates_has_update(monkeypatch):
    from app.services import updater

    fake_release = {
        "tag_name": "v2.17.0",
        "name": "v2.17.0",
        "body": "release notes",
        "published_at": "2026-05-13T00:00:00Z",
        "html_url": "http://example.com/release",
        "assets": [],
    }

    async def _fake(force_refresh=False):
        return fake_release

    monkeypatch.setattr(updater, "fetch_latest_release", _fake)
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.16.3")

    result = await updater.check_for_updates()
    assert result["has_update"] is True
    assert result["latest"] == "2.17.0"
    assert result["is_major_bump"] is False


@pytest.mark.asyncio
async def test_check_for_updates_major_bump(monkeypatch):
    from app.services import updater

    fake_release = {
        "tag_name": "v3.0.0",
        "name": "v3.0.0",
        "body": "breaking changes",
        "published_at": "",
        "html_url": "",
        "assets": [],
    }

    async def _fake(force_refresh=False):
        return fake_release

    monkeypatch.setattr(updater, "fetch_latest_release", _fake)
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.16.3")

    result = await updater.check_for_updates()
    assert result["has_update"] is True
    assert result["is_major_bump"] is True


@pytest.mark.asyncio
async def test_apply_blocks_major_bump_without_ack(monkeypatch):
    from app.services import updater

    async def _fake_fetch(force_refresh=False):
        return {
            "tag_name": "v3.0.0",
            "name": "v3.0.0",
            "body": "",
            "published_at": "",
            "html_url": "",
            "assets": [
                {"name": "unbound-dashboard-update-v3.0.0-x.tar.gz", "browser_download_url": "http://x/tar"},
                {"name": "unbound-dashboard-update-v3.0.0-x.tar.gz.sha256", "browser_download_url": "http://x/sha"},
            ],
        }

    async def _fake_acquire(job_id):
        return True

    async def _fake_release():
        pass

    monkeypatch.setattr(updater, "fetch_latest_release", _fake_fetch)
    monkeypatch.setattr(updater, "acquire_lock", _fake_acquire)
    monkeypatch.setattr(updater, "release_lock", _fake_release)
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.16.3")

    with pytest.raises(updater.MissingBreakingAck):
        await updater.apply_update("3.0.0", acknowledge_breaking=False)


@pytest.mark.asyncio
async def test_apply_blocks_version_mismatch(monkeypatch):
    """Anti-replay: caller pediu v2.17.0 mas última no GitHub é v2.16.5 → reject."""
    from app.services import updater

    async def _fake_fetch(force_refresh=False):
        return {
            "tag_name": "v2.16.5",
            "name": "v2.16.5",
            "body": "",
            "published_at": "",
            "html_url": "",
            "assets": [],
        }

    async def _fake_acquire(job_id):
        return True

    async def _fake_release():
        pass

    monkeypatch.setattr(updater, "fetch_latest_release", _fake_fetch)
    monkeypatch.setattr(updater, "acquire_lock", _fake_acquire)
    monkeypatch.setattr(updater, "release_lock", _fake_release)
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.16.3")

    with pytest.raises(updater.VersionMismatch):
        await updater.apply_update("2.17.0")


@pytest.mark.asyncio
async def test_apply_blocks_when_locked(monkeypatch):
    from app.services import updater

    async def _fake_acquire(job_id):
        return False  # lock falhou — outro update rodando

    async def _fake_running():
        return "abc123"

    monkeypatch.setattr(updater, "acquire_lock", _fake_acquire)
    monkeypatch.setattr(updater, "get_running_job_id", _fake_running)

    with pytest.raises(updater.UpdateLocked):
        await updater.apply_update("2.17.0")


# ============================================================
# SSE log endpoint — validação de job_id
# ============================================================


def test_sse_format():
    """Cada linha do data vira `data: <line>\\n`, termina com \\n\\n."""
    from app.routers.updates import _sse

    out = _sse("hello\nworld", event="msg")
    text = out.decode()
    assert text.startswith("event: msg\n")
    assert "data: hello\n" in text
    assert "data: world\n" in text
    assert text.endswith("\n\n")


def test_sse_single_line_no_event():
    from app.routers.updates import _sse

    out = _sse("ok").decode()
    assert "event:" not in out
    assert out == "data: ok\n\n"


def test_resolve_final_status_with_marker(tmp_path, monkeypatch):
    """Marker no log tem precedência sobre VERSION check."""
    from app.services import updater

    log = tmp_path / "u.log"
    log.write_text("Update concluído\n")
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.17.0")
    assert updater._resolve_final_status(log, "2.17.9", marker_found=True) == "succeeded"


def test_resolve_final_status_version_match_no_marker(tmp_path, monkeypatch):
    """Sem marker, mas VERSION bate → succeeded (log truncou mas update aplicou)."""
    from app.services import updater

    log = tmp_path / "u.log"
    log.write_text("[..] coisas\n[OK] Apache conf atualizado\n")  # log truncado
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.17.9")
    assert updater._resolve_final_status(log, "2.17.9", marker_found=False) == "succeeded"


def test_resolve_final_status_version_mismatch_no_marker(tmp_path, monkeypatch):
    """Sem marker E VERSION não bate → failed."""
    from app.services import updater

    log = tmp_path / "u.log"
    log.write_text("[..] coisas\n[OK] Apache conf atualizado\n")
    monkeypatch.setattr(updater, "_read_local_version", lambda: "2.17.0")  # ainda antiga
    assert updater._resolve_final_status(log, "2.17.9", marker_found=False) == "failed"


def test_validate_job_id():
    """Aceita 12 chars hex, rejeita resto (anti path-traversal)."""
    from fastapi import HTTPException

    from app.routers.updates import _validate_job_id

    # Valid
    _validate_job_id("abc123def456")
    _validate_job_id("0123456789ab")

    # Invalid
    with pytest.raises(HTTPException) as exc:
        _validate_job_id("short")
    assert exc.value.status_code == 400

    with pytest.raises(HTTPException):
        _validate_job_id("../../etc/pa")  # 12 chars mas com / e .

    with pytest.raises(HTTPException):
        _validate_job_id("UPPER_NOT_OK")

    with pytest.raises(HTTPException):
        _validate_job_id("0123456789abcdef")  # 16 chars


# ============================================================
# Histórico de backups + restore
# ============================================================


def test_list_backups_empty(monkeypatch, tmp_path):
    from app.services import updater

    monkeypatch.setattr(updater, "BACKUP_DIR", tmp_path)
    assert updater.list_backups() == []


def test_list_backups_sorted_recent_first(monkeypatch, tmp_path):
    from app.services import updater

    monkeypatch.setattr(updater, "BACKUP_DIR", tmp_path)

    for ts in ("20260513_120000", "20260514_100000", "20260514_080000"):
        (tmp_path / f"dashboard-{ts}.tar.gz").write_bytes(b"x" * 100)

    items = updater.list_backups()
    assert [i["timestamp"] for i in items] == [
        "20260514_100000",  # mais recente primeiro
        "20260514_080000",
        "20260513_120000",
    ]


def test_list_backups_includes_metadata(monkeypatch, tmp_path):
    from app.services import updater

    monkeypatch.setattr(updater, "BACKUP_DIR", tmp_path)

    ts = "20260514_120000"
    (tmp_path / f"dashboard-{ts}.tar.gz").write_bytes(b"y" * 500)
    (tmp_path / f"duckdb-{ts}.duckdb").write_bytes(b"z" * 1000)
    (tmp_path / f"api-v1.env-{ts}").write_text("JWT_SECRET=x\n")

    items = updater.list_backups()
    assert len(items) == 1
    item = items[0]
    assert item["timestamp"] == ts
    assert item["size_bytes"] == 500
    assert item["has_duckdb"] is True
    assert item["has_env"] is True
    assert item["duckdb_size_bytes"] == 1000


def test_list_backups_limit(monkeypatch, tmp_path):
    from app.services import updater

    monkeypatch.setattr(updater, "BACKUP_DIR", tmp_path)

    for i in range(20):
        ts = f"20260514_{i:02d}0000"
        (tmp_path / f"dashboard-{ts}.tar.gz").write_bytes(b"x")

    items = updater.list_backups(limit=5)
    assert len(items) == 5


def test_list_backups_ignores_malformed_names(monkeypatch, tmp_path):
    from app.services import updater

    monkeypatch.setattr(updater, "BACKUP_DIR", tmp_path)

    (tmp_path / "dashboard-20260514_120000.tar.gz").write_bytes(b"x")
    (tmp_path / "dashboard-bad-format.tar.gz").write_bytes(b"x")
    (tmp_path / "random-file.tar.gz").write_bytes(b"x")
    (tmp_path / "pre-restore-20260514_120000.tar.gz").write_bytes(b"x")  # snapshot do restore-backup, não conta

    items = updater.list_backups()
    assert len(items) == 1
    assert items[0]["timestamp"] == "20260514_120000"


@pytest.mark.asyncio
async def test_restore_backup_invalid_timestamp(monkeypatch):
    from app.services import updater

    with pytest.raises(updater.InvalidTimestamp):
        await updater.restore_backup("not-a-timestamp")

    with pytest.raises(updater.InvalidTimestamp):
        await updater.restore_backup("../../etc/passwd")


@pytest.mark.asyncio
async def test_restore_backup_not_found(monkeypatch, tmp_path):
    from app.services import updater

    monkeypatch.setattr(updater, "BACKUP_DIR", tmp_path)
    with pytest.raises(updater.BackupNotFound):
        await updater.restore_backup("20260101_000000")
