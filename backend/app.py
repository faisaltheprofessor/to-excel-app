from fastapi import FastAPI, Request
from fastapi.responses import StreamingResponse
import io, re
from openpyxl import Workbook
from openpyxl.styles import PatternFill, Font, Alignment, Border, Side
from openpyxl.utils import get_column_letter

app = FastAPI()

# ===== Config =====
PREFIX = "203_"
GROUPS_FROM_LEVEL = 3
SHEET_NAME = "GE_Gruppenstruktur"

ROW_BAND, ROW_TITLE, ROW_CAPTION, ROW_HEADERS = 2, 3, 3, 5
DATA_START_ROW = 4

# ===== Styles =====
ORANGE = PatternFill("solid", fgColor="EA5B2B")
CYAN   = PatternFill("solid", fgColor="63D3FF")
PALEGR = PatternFill("solid", fgColor="D8F4D2")
BLUE   = PatternFill("solid", fgColor="2F78BD")
LIME   = PatternFill("solid", fgColor="CCFF66")
WHITEB = Font(color="FFFFFF", bold=True)
BLACK  = Font(color="000000")
BLACKB = Font(color="000000", bold=True)
GRAY   = Font(color="808080")
LEFT   = Alignment(horizontal="left",  vertical="center")
CENTER = Alignment(horizontal="center",vertical="center")
BOX = Border(
    left=Side(style="thin", color="D0D0D0"),
    right=Side(style="thin", color="D0D0D0"),
    top=Side(style="thin", color="D0D0D0"),
    bottom=Side(style="thin", color="D0D0D0"),
)

PERM_HEADERS = [
    "Lesen", "Schreiben", "Administrieren", "Löschadministration",
    "Ablageadministration", "Aktenplanadministration", "Vorlagenadministration",
    "Aussonderung", "Postverteilung- zentral", "Postverteilung- dezentral", "Designkonfiguration",
]
PERM_SUFFIX = ["RO", "", "FA", "LA", "AA", "APA", "VA", "AUS", "POZ", "POD", "DK"]

# Force certain labels to container styling even if leaf
FORCE_BLUE = {"Org", "Org Name"}

def norm_token(s: str) -> str:
    s = (s or "").strip()
    s = re.sub(r"[^\w]+","_",s)
    s = re.sub(r"_+","_",s).strip("_")
    return s

def tree_max_depth(nodes, level=0):
    if not nodes:
        return level-1
    m = level
    for n in nodes:
        m = max(m, tree_max_depth(n.get("children") or [], level+1))
    return m

def create_sheet(last_name_col:int, perm_cols:int, max_depth:int):
    """
    last_name_col = max_depth + 1 for left tree label column.
    On the right, we allocate a tree area from tree_base .. tree_end (tree_base+max_depth)
    so we can draw connectors and indent labels by level using appName.
    """
    spacer1 = last_name_col + 1
    perm1_s = spacer1 + 1; perm1_e = perm1_s + perm_cols - 1
    spacer2 = perm1_e + 1
    perm2_s = spacer2 + 1; perm2_e = perm2_s + perm_cols - 1
    spacer3 = perm2_e + 1
    flat_col = spacer3 + 1
    spacer4 = flat_col + 1

    # right tree: base column where connectors start; label at base+level
    tree_base = spacer4 + 1
    tree_end  = tree_base + max_depth  # inclusive

    wb = Workbook(); ws = wb.active; ws.title = SHEET_NAME

    # widths (initial; will auto-fit later)
    for c in range(1, last_name_col):
        ws.column_dimensions[get_column_letter(c)].width = 4
    ws.column_dimensions[get_column_letter(last_name_col)].width = 26
    for c in [spacer1, spacer2, spacer3, spacer4]:
        ws.column_dimensions[get_column_letter(c)].width = 2
    for c in list(range(perm1_s, perm1_e+1)) + list(range(perm2_s, perm2_e+1)):
        ws.column_dimensions[get_column_letter(c)].width = 22
    ws.column_dimensions[get_column_letter(flat_col)].width = 26

    # right tree area: narrow for connectors, autosize will expand label cols as needed
    for c in range(tree_base, tree_end+1):
        ws.column_dimensions[get_column_letter(c)].width = 6

    for r,h in [(ROW_BAND,24),(ROW_TITLE,20),(ROW_HEADERS-1,18)]:
        ws.row_dimensions[r].height = h

    # ==== Header bands ====
    # Left permissions band (eDir)
    ws.merge_cells(start_row=ROW_BAND, start_column=perm1_s, end_row=ROW_BAND, end_column=perm1_e)
    c1 = ws.cell(row=ROW_BAND, column=perm1_s, value="eDir")
    c1.fill = ORANGE; c1.font = WHITEB; c1.alignment = CENTER; c1.border = BOX

    # Right band: orange eDir chip one column before AD band
    chip = ws.cell(row=ROW_BAND, column=perm2_s, value="eDir")
    chip.fill = ORANGE; chip.font = WHITEB; chip.alignment = CENTER; chip.border = BOX

    # AD(DFSW) band shifted right by 1
    ws.merge_cells(start_row=ROW_BAND, start_column=perm2_s+1, end_row=ROW_BAND, end_column=perm2_e)
    c2 = ws.cell(row=ROW_BAND, column=perm2_s+1, value="AD(DFSW)")
    c2.fill = CYAN; c2.font = BLACKB; c2.alignment = CENTER; c2.border = BOX

    ws.cell(row=ROW_TITLE, column=1, value="Struktur Gruppen mit Schreibzugriff").font = BLACKB
    ws.merge_cells(start_row=ROW_CAPTION, start_column=perm1_s, end_row=ROW_CAPTION, end_column=perm1_e)
    ws.cell(row=ROW_CAPTION, column=perm1_s, value="auf Knoten zusätzlich anzulegende Gruppen").font = BLACKB

    # Column headers for both permission blocks
    for j,hdr in enumerate(PERM_HEADERS, start=perm1_s):
        hc = ws.cell(row=ROW_HEADERS, column=j, value=hdr)
        hc.font = BLACKB; hc.alignment = CENTER; hc.border = BOX
    for j,hdr in enumerate(PERM_HEADERS, start=perm2_s):
        hc = ws.cell(row=ROW_HEADERS, column=j, value=hdr)
        hc.font = BLACKB; hc.alignment = CENTER; hc.border = BOX

    # Right area band covering Liste + right tree
    ws.merge_cells(start_row=ROW_BAND, start_column=flat_col, end_row=ROW_BAND, end_column=tree_end)
    ab = ws.cell(row=ROW_BAND, column=flat_col, value="nscale strukturierte Ablage")
    ab.fill = PALEGR; ab.font = BLACKB; ab.alignment = LEFT; ab.border = BOX

    # Subheaders for list/tree
    ws.cell(row=ROW_HEADERS-1, column=flat_col, value="Liste").font = BLACKB
    ws.merge_cells(start_row=ROW_HEADERS-1, start_column=tree_base, end_row=ROW_HEADERS-1, end_column=tree_end)
    bh = ws.cell(row=ROW_HEADERS-1, column=tree_base, value="Baum")
    bh.font = BLACKB; bh.alignment = LEFT

    return wb, ws, {
        "spacer1": spacer1, "perm1_s": perm1_s, "perm1_e": perm1_e,
        "spacer2": spacer2, "perm2_s": perm2_s, "perm2_e": perm2_e,
        "spacer3": spacer3, "flat_col": flat_col, "spacer4": spacer4,
        "tree_base": tree_base, "tree_end": tree_end, "max_depth": max_depth
    }

def draw_connectors(ws, row, level, last_stack, base_col=1):
    """Draw ASCII connectors starting from base_col up to base_col+level-1, then
    the caller places the label at base_col+level (if they want)."""
    if level <= 0:
        return
    for depth in range(1, level+1):
        col = base_col + depth - 1
        if depth == level:
            ch = "└" if last_stack[depth-1] else "├"
        else:
            ch = "│" if not last_stack[depth-1] else ""
        if ch:
            cc = ws.cell(row=row, column=col, value=ch)
            cc.font = GRAY; cc.alignment = LEFT

def style_cell_like_node(cell, label:str, is_container:bool):
    cell.alignment = LEFT; cell.border = BOX
    if is_container or label in FORCE_BLUE:
        cell.fill = BLUE; cell.font = WHITEB
    else:
        cell.fill = LIME; cell.font = BLACKB

def style_name_cell(cell, node_name:str, has_children:bool):
    cell.alignment = LEFT; cell.border = BOX
    if has_children or node_name in FORCE_BLUE:
        cell.fill = BLUE; cell.font = WHITEB
    else:
        cell.fill = LIME; cell.font = BLACKB

def write_rows(ws, nodes, row, level, last_stack, cols, lineage_names, lineage_apps):
    name_col = level + 1
    for i,node in enumerate(nodes):
        name = (node.get("name") or "").strip()
        appn = (node.get("appName") or name).strip()
        children = node.get("children") or []
        if not name and not children:
            continue
        is_last = (i == len(nodes) - 1)

        # ----- Blank row before every AblgOE node -----
        if appn.lower() == "ablgOE".lower() or name.lower() == "ablgOE".lower():
            row += 1  # spacer row

        # LEFT tree (by 'name')
        draw_connectors(ws, row, level, last_stack+[is_last], base_col=1)
        nc = ws.cell(row=row, column=name_col, value=name if name else "(unnamed)")
        style_name_cell(nc, name, bool(children))

        # Dashes until spacer1
        for dc in range(name_col+1, cols["spacer1"]):
            d = ws.cell(row=row, column=dc, value="-")
            d.alignment = CENTER; d.border = BOX

        # Clear spacer columns
        for sc in [cols["spacer1"], cols["spacer2"], cols["spacer3"], cols["spacer4"]]:
            ws.cell(row=row, column=sc, value="")

        # Perm groups (left eDir)
        if level >= GROUPS_FROM_LEVEL and name:
            for j,suf in enumerate(PERM_SUFFIX, start=cols["perm1_s"]):
                val = f"{name}-{suf}" if suf else name
                g = ws.cell(row=row, column=j, value=val)
                g.alignment = LEFT; g.border = BOX

        # Perm groups (right AD/eDir lineage)
        if level >= GROUPS_FROM_LEVEL and name:
            tokens = lineage_names + [norm_token(name)]
            start = min(GROUPS_FROM_LEVEL, len(tokens)-1)
            key = PREFIX + "_".join(tokens[start:])
            for j,suf in enumerate(PERM_SUFFIX, start=cols["perm2_s"]):
                val = f"{key}-{suf}" if suf else key
                g = ws.cell(row=row, column=j, value=val)
                g.alignment = LEFT; g.border = BOX

        # "Liste" column — flat appName
        flat_label = appn if appn else (name if name else "(unnamed)")
        fc = ws.cell(row=row, column=cols["flat_col"], value=flat_label)
        style_cell_like_node(fc, flat_label, bool(children))

        # RIGHT tree (by 'appName'): connectors + indented label at tree_base+level
        draw_connectors(ws, row, level, last_stack+[is_last], base_col=cols["tree_base"])
        r_name_col = cols["tree_base"] + level
        tv_label = appn if appn else (name if name else "(unnamed)")
        tv = ws.cell(row=row, column=r_name_col, value=tv_label)
        style_cell_like_node(tv, tv_label, bool(children))

        row += 1
        if children:
            row = write_rows(
                ws, children, row, level+1, last_stack+[is_last], cols,
                lineage_names + [norm_token(name)],
                lineage_apps  + [appn]
            )
    return row

def autosize_columns(ws):
    # Best-effort auto-fit, capped
    for col_cells in ws.columns:
        first = next((c for c in col_cells if c is not None), None)
        if not first:
            continue
        letter = get_column_letter(first.column)
        max_len = 0
        for cell in col_cells:
            val = cell.value
            if val is None:
                continue
            try:
                ln = len(str(val))
            except Exception:
                ln = 0
            if ln > max_len:
                max_len = ln
        ws.column_dimensions[letter].width = max(2, min(60, max_len + 1))

@app.post("/generate-excel")
async def generate_excel(request: Request):
    data = await request.json()
    tree = data.get("tree")
    if not tree:
        return {"error":"Missing 'tree' in JSON body"}

    max_depth = max(0, tree_max_depth(tree))
    last_name_col = max_depth + 1

    wb, ws, cols = create_sheet(last_name_col, len(PERM_HEADERS), max_depth)

    write_rows(ws, tree, DATA_START_ROW, level=0, last_stack=[],
               cols=cols, lineage_names=[], lineage_apps=[])

    autosize_columns(ws)

    buf = io.BytesIO()
    wb.save(buf)
    buf.seek(0)
    return StreamingResponse(
        buf,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition":"attachment; filename=tree.xlsx"}
    )
