#!/usr/bin/env python3
import sys
import json
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, PageBreak
)
from reportlab.graphics.shapes import Drawing, Rect, String
from reportlab.graphics import renderPDF
from reportlab.graphics.charts.barcharts import VerticalBarChart
from reportlab.graphics.charts.piecharts import Pie

# ---- Données ----
data    = json.loads(sys.argv[1])
outfile = sys.argv[2]

# ---- Couleurs Quantyss ----
PURPLE     = colors.HexColor('#6366F1')
PURPLE_BG  = colors.HexColor('#EDE9FE')
PURPLE_DARK= colors.HexColor('#4F46E5')
GREEN      = colors.HexColor('#10B981')
AMBER      = colors.HexColor('#F59E0B')
RED        = colors.HexColor('#EF4444')
GRAY_BG    = colors.HexColor('#F9FAFB')
GRAY_TEXT  = colors.HexColor('#6B7280')
DARK       = colors.HexColor('#111827')
WHITE      = colors.white

W, H = A4

# ---- Styles ----
styles = getSampleStyleSheet()

s_title = ParagraphStyle('s_title',
    fontName='Helvetica-Bold', fontSize=28,
    textColor=DARK, spaceAfter=6, leading=32)

s_subtitle = ParagraphStyle('s_subtitle',
    fontName='Helvetica', fontSize=13,
    textColor=GRAY_TEXT, spaceAfter=20)

s_h2 = ParagraphStyle('s_h2',
    fontName='Helvetica-Bold', fontSize=16,
    textColor=PURPLE, spaceBefore=16, spaceAfter=8)

s_body = ParagraphStyle('s_body',
    fontName='Helvetica', fontSize=11,
    textColor=DARK, spaceAfter=8, leading=16)

s_small = ParagraphStyle('s_small',
    fontName='Helvetica', fontSize=9,
    textColor=GRAY_TEXT, spaceAfter=4)

s_kpi_value = ParagraphStyle('s_kpi_value',
    fontName='Helvetica-Bold', fontSize=32,
    textColor=PURPLE, spaceAfter=2, alignment=TA_CENTER)

s_kpi_label = ParagraphStyle('s_kpi_label',
    fontName='Helvetica', fontSize=10,
    textColor=GRAY_TEXT, spaceAfter=0, alignment=TA_CENTER)

s_center = ParagraphStyle('s_center',
    fontName='Helvetica', fontSize=11,
    textColor=WHITE, alignment=TA_CENTER)

s_table_header = ParagraphStyle('s_table_header',
    fontName='Helvetica-Bold', fontSize=10,
    textColor=WHITE)

s_table_cell = ParagraphStyle('s_table_cell',
    fontName='Helvetica', fontSize=10,
    textColor=DARK)

# ---- Helpers ----
def hr():
    return HRFlowable(width='100%', thickness=1, color=PURPLE_BG,
                      spaceAfter=12, spaceBefore=4)

def spacer(h=8):
    return Spacer(1, h * mm)

def section_title(text):
    return Paragraph(text, s_h2)

def kpi_card(value, label, color=None):
    c = color or PURPLE
    val_style = ParagraphStyle('kv', fontName='Helvetica-Bold',
        fontSize=28, textColor=c, spaceAfter=2, alignment=TA_CENTER)
    lbl_style = ParagraphStyle('kl', fontName='Helvetica',
        fontSize=9, textColor=GRAY_TEXT, spaceAfter=0, alignment=TA_CENTER)
    return Table(
        [[Paragraph(str(value), val_style)],
         [Paragraph(label, lbl_style)]],
        colWidths=[40*mm],
        style=TableStyle([
            ('BACKGROUND', (0,0), (-1,-1), PURPLE_BG),
            ('ROUNDEDCORNERS', [6]),
            ('TOPPADDING', (0,0), (-1,-1), 10),
            ('BOTTOMPADDING', (0,0), (-1,-1), 10),
            ('LEFTPADDING', (0,0), (-1,-1), 6),
            ('RIGHTPADDING', (0,0), (-1,-1), 6),
        ])
    )

# ---- Header / Footer ----
def on_page(canvas, doc):
    canvas.saveState()
    # Header bande violette
    canvas.setFillColor(PURPLE)
    canvas.rect(0, H - 20*mm, W, 20*mm, fill=1, stroke=0)
    canvas.setFillColor(WHITE)
    canvas.setFont('Helvetica-Bold', 11)
    canvas.drawString(15*mm, H - 13*mm, 'QUANTYSS')
    canvas.setFont('Helvetica', 9)
    canvas.drawRightString(W - 15*mm, H - 13*mm,
        f"Rapport mensuel · {data['month_label']}")

    # Footer
    canvas.setFillColor(GRAY_TEXT)
    canvas.setFont('Helvetica', 8)
    canvas.drawString(15*mm, 10*mm,
        f"{data['site_name']} · {data['site_url']}")
    canvas.drawRightString(W - 15*mm, 10*mm,
        f"Page {doc.page}")
    canvas.restoreState()

def on_first_page(canvas, doc):
    canvas.saveState()
    # Bloc violet haut
    canvas.setFillColor(PURPLE)
    canvas.rect(0, H - 80*mm, W, 80*mm, fill=1, stroke=0)
    # Accent décoratif
    canvas.setFillColor(PURPLE_DARK)
    canvas.rect(0, H - 80*mm, 8*mm, 80*mm, fill=1, stroke=0)
    # Footer
    canvas.setFillColor(GRAY_TEXT)
    canvas.setFont('Helvetica', 8)
    canvas.drawString(15*mm, 10*mm,
        f"Généré le {data['report_date']} · {data['site_url']}")
    canvas.drawRightString(W - 15*mm, 10*mm, 'Page 1')
    canvas.restoreState()

# ---- Construction du document ----
doc = SimpleDocTemplate(
    outfile,
    pagesize=A4,
    leftMargin=15*mm, rightMargin=15*mm,
    topMargin=90*mm, bottomMargin=20*mm,
)

story = []

# ---- PAGE 1 — COUVERTURE ----
cover_title = ParagraphStyle('ct',
    fontName='Helvetica-Bold', fontSize=32,
    textColor=WHITE, spaceAfter=8, leading=38)
cover_sub = ParagraphStyle('cs',
    fontName='Helvetica', fontSize=14,
    textColor=colors.HexColor('#C7D2FE'), spaceAfter=0)

story.append(Paragraph('Rapport mensuel', cover_sub))
story.append(Spacer(1, 2*mm))
story.append(Paragraph(data['month_label'], cover_title))
story.append(Spacer(1, 4*mm))
story.append(Paragraph(data['site_name'], cover_sub))

story.append(PageBreak())

# ---- PAGE 2 — KPIs ----
doc2 = SimpleDocTemplate(
    outfile, pagesize=A4,
    leftMargin=15*mm, rightMargin=15*mm,
    topMargin=28*mm, bottomMargin=20*mm,
)
story2 = []

story2.append(section_title('Vue d\'ensemble'))
story2.append(hr())
story2.append(spacer(4))

# KPI grid 4 cartes
uptime_color = GREEN if data['uptime'] >= 99 else AMBER if data['uptime'] >= 95 else RED
kpi_row = Table(
    [[
        kpi_card(data['posts_month'], 'Articles publiés'),
        kpi_card(data['leads_month'], 'Nouveaux leads'),
        kpi_card(data['downloads'],   'Téléchargements'),
        kpi_card(f"{data['uptime']}%", 'Uptime', uptime_color),
    ]],
    colWidths=[43*mm, 43*mm, 43*mm, 43*mm],
    style=TableStyle([
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('LEFTPADDING', (0,0), (-1,-1), 3),
        ('RIGHTPADDING', (0,0), (-1,-1), 3),
    ])
)
story2.append(kpi_row)
story2.append(spacer(8))

# ---- Leads par source ----
if data['leads_by_source']:
    story2.append(section_title('Leads par source'))
    story2.append(hr())
    source_labels = {'cf7': 'Formulaire contact', 'lead_magnet': 'Lead magnet', 'manual': 'Manuel'}
    rows = [
        [Paragraph('Source', s_table_header),
         Paragraph('Leads', s_table_header),
         Paragraph('Part', s_table_header)]
    ]
    total_leads = sum(int(r['count']) for r in data['leads_by_source'])
    for r in data['leads_by_source']:
        pct = round((int(r['count']) / total_leads * 100)) if total_leads > 0 else 0
        rows.append([
            Paragraph(source_labels.get(r['source'], r['source']), s_table_cell),
            Paragraph(str(r['count']), s_table_cell),
            Paragraph(f"{pct}%", s_table_cell),
        ])
    t = Table(rows, colWidths=[100*mm, 40*mm, 40*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), PURPLE),
        ('BACKGROUND', (0,1), (-1,-1), WHITE),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [WHITE, GRAY_BG]),
        ('GRID', (0,0), (-1,-1), 0.5, colors.HexColor('#E5E7EB')),
        ('TOPPADDING', (0,0), (-1,-1), 8),
        ('BOTTOMPADDING', (0,0), (-1,-1), 8),
        ('LEFTPADDING', (0,0), (-1,-1), 10),
        ('ROUNDEDCORNERS', [4]),
    ]))
    story2.append(t)
    story2.append(spacer(8))

# ---- Statut des leads ----
if data['leads_by_status']:
    story2.append(section_title('Pipeline leads (total)'))
    story2.append(hr())
    status_labels = {
        'new': ('Nouveau', '#6366F1'),
        'in_progress': ('En cours', '#F59E0B'),
        'qualified': ('Qualifié', '#10B981'),
        'archived': ('Archivé', '#9CA3AF'),
    }
    rows = [[
        Paragraph('Statut', s_table_header),
        Paragraph('Nombre', s_table_header),
    ]]
    for r in data['leads_by_status']:
        label, color_hex = status_labels.get(r['status'], (r['status'], '#6B7280'))
        rows.append([
            Paragraph(f'<font color="{color_hex}">●</font>  {label}', s_table_cell),
            Paragraph(str(r['count']), s_table_cell),
        ])
    t2 = Table(rows, colWidths=[100*mm, 80*mm])
    t2.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), PURPLE),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [WHITE, GRAY_BG]),
        ('GRID', (0,0), (-1,-1), 0.5, colors.HexColor('#E5E7EB')),
        ('TOPPADDING', (0,0), (-1,-1), 8),
        ('BOTTOMPADDING', (0,0), (-1,-1), 8),
        ('LEFTPADDING', (0,0), (-1,-1), 10),
    ]))
    story2.append(t2)
    story2.append(spacer(8))

# ---- Sécurité ----
story2.append(section_title('Sécurité & Disponibilité'))
story2.append(hr())
sec_rows = [
    [Paragraph('Indicateur', s_table_header), Paragraph('Valeur', s_table_header), Paragraph('Statut', s_table_header)],
    [Paragraph('Disponibilité du site', s_table_cell),
     Paragraph(f"{data['uptime']}%", s_table_cell),
     Paragraph('✅ Normal' if data['uptime'] >= 99 else '⚠️ Surveiller', s_table_cell)],
    [Paragraph('Tentatives de connexion échouées', s_table_cell),
     Paragraph(str(data['failed_logins']), s_table_cell),
     Paragraph('✅ Normal' if data['failed_logins'] < 20 else '⚠️ Élevé', s_table_cell)],
]
t3 = Table(sec_rows, colWidths=[90*mm, 50*mm, 40*mm])
t3.setStyle(TableStyle([
    ('BACKGROUND', (0,0), (-1,0), PURPLE),
    ('ROWBACKGROUNDS', (0,1), (-1,-1), [WHITE, GRAY_BG]),
    ('GRID', (0,0), (-1,-1), 0.5, colors.HexColor('#E5E7EB')),
    ('TOPPADDING', (0,0), (-1,-1), 8),
    ('BOTTOMPADDING', (0,0), (-1,-1), 8),
    ('LEFTPADDING', (0,0), (-1,-1), 10),
]))
story2.append(t3)
story2.append(spacer(8))

# ---- Derniers articles ----
if data['recent_posts']:
    story2.append(section_title('Articles publiés ce mois'))
    story2.append(hr())
    art_rows = [[
        Paragraph('Titre', s_table_header),
        Paragraph('Date', s_table_header),
    ]]
    for p in data['recent_posts']:
        art_rows.append([
            Paragraph(p['title'], s_table_cell),
            Paragraph(p['date'], s_table_cell),
        ])
    t4 = Table(art_rows, colWidths=[130*mm, 50*mm])
    t4.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), PURPLE),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [WHITE, GRAY_BG]),
        ('GRID', (0,0), (-1,-1), 0.5, colors.HexColor('#E5E7EB')),
        ('TOPPADDING', (0,0), (-1,-1), 8),
        ('BOTTOMPADDING', (0,0), (-1,-1), 8),
        ('LEFTPADDING', (0,0), (-1,-1), 10),
    ]))
    story2.append(t4)

# ---- Footer signature ----
story2.append(spacer(12))
story2.append(hr())
footer_style = ParagraphStyle('ft', fontName='Helvetica', fontSize=9,
    textColor=GRAY_TEXT, alignment=TA_CENTER)
story2.append(Paragraph(
    f"Rapport généré automatiquement par Quantyss Core · {data['report_date']}",
    footer_style
))

# ---- Build final ----
# Page 1 avec couverture
doc.build(story, onFirstPage=on_first_page, onLaterPages=on_page)

# Append les pages suivantes
from pypdf import PdfWriter, PdfReader
import io

buf2 = io.BytesIO()
doc2.build(story2, onFirstPage=on_page, onLaterPages=on_page)

# Merge : couverture + contenu
doc2_final = SimpleDocTemplate(
    outfile, pagesize=A4,
    leftMargin=15*mm, rightMargin=15*mm,
    topMargin=90*mm, bottomMargin=20*mm,
)

all_story = story + [PageBreak()] + story2
doc_final = SimpleDocTemplate(
    outfile, pagesize=A4,
    leftMargin=15*mm, rightMargin=15*mm,
    topMargin=90*mm, bottomMargin=20*mm,
)

def page_handler(canvas, doc):
    if doc.page == 1:
        on_first_page(canvas, doc)
    else:
        on_page(canvas, doc)

doc_final.build(all_story, onFirstPage=on_first_page, onLaterPages=on_page)
print("OK")