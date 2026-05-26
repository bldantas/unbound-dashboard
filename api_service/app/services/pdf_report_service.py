"""
Geração de PDFs pra relatórios LGPD e admin audit.

Usa reportlab platypus (Tables + Paragraphs) — saída A4 retrato com
header/footer, tabela paginada automaticamente. Pra grandes volumes
(>10k linhas) preferir CSV (que já existe).
"""

from __future__ import annotations

import io
import json
from datetime import datetime
from typing import Any

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

_STYLES = getSampleStyleSheet()
_TITLE = ParagraphStyle("Title", parent=_STYLES["Title"], fontSize=18, spaceAfter=8)
_SUBTITLE = ParagraphStyle("Subtitle", parent=_STYLES["Normal"], fontSize=10, textColor=colors.HexColor("#475569"), spaceAfter=12)
_H2 = ParagraphStyle("H2", parent=_STYLES["Heading2"], fontSize=12, spaceBefore=10, spaceAfter=4)
_SMALL = ParagraphStyle("Small", parent=_STYLES["Normal"], fontSize=8, textColor=colors.HexColor("#64748b"))
_CELL = ParagraphStyle("Cell", parent=_STYLES["Normal"], fontSize=8, leading=10)


def _header_footer(canvas, doc) -> None:
    canvas.saveState()
    canvas.setFont("Helvetica-Bold", 9)
    canvas.setFillColor(colors.HexColor("#1e293b"))
    canvas.drawString(15 * mm, A4[1] - 12 * mm, "Unbound Dashboard")
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(colors.HexColor("#64748b"))
    canvas.drawRightString(A4[0] - 15 * mm, A4[1] - 12 * mm,
                            datetime.now().strftime("Gerado: %Y-%m-%d %H:%M"))
    canvas.line(15 * mm, A4[1] - 14 * mm, A4[0] - 15 * mm, A4[1] - 14 * mm)
    canvas.setFont("Helvetica", 8)
    canvas.drawRightString(A4[0] - 15 * mm, 10 * mm, f"Página {doc.page}")
    canvas.restoreState()


def _build_doc(title: str) -> tuple[SimpleDocTemplate, io.BytesIO]:
    buf = io.BytesIO()
    doc = SimpleDocTemplate(
        buf, pagesize=A4,
        title=title, author="Unbound Dashboard",
        leftMargin=15 * mm, rightMargin=15 * mm,
        topMargin=20 * mm, bottomMargin=15 * mm,
    )
    return doc, buf


# ---------- LGPD report PDF ----------

def lgpd_report_pdf(report: dict[str, Any]) -> bytes:
    title = f"Relatório LGPD — {report.get('client_ip', '?')}"
    doc, buf = _build_doc(title)

    story = [
        Paragraph(title, _TITLE),
        Paragraph(
            f"Queries DNS feitas pelo cliente <b>{report.get('client_ip')}</b> "
            f"nas últimas <b>{report.get('hours', '?')}h</b>. "
            f"Total: <b>{report.get('total', 0)}</b> registros"
            f"{' (truncado pelo limite)' if report.get('truncated') else ''}.",
            _SUBTITLE,
        ),
        Paragraph(
            "Este relatório atende ao Art. 18 da LGPD (acesso a dados pessoais). "
            "Cada geração foi registrada na trilha de auditoria (admin_audit).",
            _SMALL,
        ),
        Spacer(1, 6),
    ]

    items = report.get("items", [])
    if not items:
        story.append(Paragraph("<i>Nenhum registro encontrado no período.</i>", _STYLES["Normal"]))
    else:
        data = [["Timestamp", "Tipo", "Domínio", "Ação"]]
        for it in items:
            ts = it.get("timestamp") or 0
            iso = datetime.fromtimestamp(ts).strftime("%Y-%m-%d %H:%M:%S") if ts else ""
            data.append([
                Paragraph(iso, _CELL),
                Paragraph(str(it.get("query_type") or ""), _CELL),
                Paragraph(str(it.get("domain") or ""), _CELL),
                Paragraph(str(it.get("action") or ""), _CELL),
            ])
        t = Table(data, colWidths=[38 * mm, 18 * mm, 90 * mm, 30 * mm], repeatRows=1)
        t.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#1e293b")),
            ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
            ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
            ("FONTSIZE", (0, 0), (-1, 0), 9),
            ("BOTTOMPADDING", (0, 0), (-1, 0), 6),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f8fafc")]),
            ("GRID", (0, 0), (-1, -1), 0.25, colors.HexColor("#e2e8f0")),
        ]))
        story.append(t)

    doc.build(story, onFirstPage=_header_footer, onLaterPages=_header_footer)
    return buf.getvalue()


# ---------- Admin audit PDF ----------

def admin_audit_pdf(items: list[dict[str, Any]], filters: dict[str, Any]) -> bytes:
    title = "Relatório de Auditoria Administrativa"
    doc, buf = _build_doc(title)

    filter_desc_parts = []
    if filters.get("category"):
        filter_desc_parts.append(f"categoria=<b>{filters['category']}</b>")
    if filters.get("action_prefix"):
        filter_desc_parts.append(f"action~<b>{filters['action_prefix']}*</b>")
    if filters.get("from_ts"):
        filter_desc_parts.append(f"a partir de <b>{datetime.fromtimestamp(filters['from_ts']).strftime('%Y-%m-%d %H:%M')}</b>")
    filter_desc = "; ".join(filter_desc_parts) or "sem filtros"

    story = [
        Paragraph(title, _TITLE),
        Paragraph(f"Filtros: {filter_desc}. Total: <b>{len(items)}</b> entradas.", _SUBTITLE),
        Spacer(1, 4),
    ]
    if not items:
        story.append(Paragraph("<i>Nenhuma entrada para os filtros.</i>", _STYLES["Normal"]))
    else:
        data = [["Quando", "Ator", "IP", "Categoria", "Ação", "Alvo"]]
        for it in items:
            when = (it.get("created_at") or "").replace("T", " ")[:19]
            tgt = ""
            if it.get("target_type") or it.get("target_id"):
                tgt = f"{it.get('target_type') or ''}/{it.get('target_id') or ''}"
            data.append([
                Paragraph(when, _CELL),
                Paragraph(str(it.get("actor_username") or "?"), _CELL),
                Paragraph(str(it.get("actor_ip") or "—"), _CELL),
                Paragraph(str(it.get("category") or ""), _CELL),
                Paragraph(str(it.get("action") or ""), _CELL),
                Paragraph(tgt, _CELL),
            ])
        t = Table(
            data,
            colWidths=[32 * mm, 22 * mm, 22 * mm, 22 * mm, 48 * mm, 30 * mm],
            repeatRows=1,
        )
        t.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#1e293b")),
            ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
            ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
            ("FONTSIZE", (0, 0), (-1, 0), 9),
            ("BOTTOMPADDING", (0, 0), (-1, 0), 6),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f8fafc")]),
            ("GRID", (0, 0), (-1, -1), 0.25, colors.HexColor("#e2e8f0")),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ]))
        story.append(t)

    doc.build(story, onFirstPage=_header_footer, onLaterPages=_header_footer)
    return buf.getvalue()
