from fastapi import FastAPI, Request
from fastapi.responses import StreamingResponse
import io
from openpyxl import Workbook
from openpyxl.styles import PatternFill, Font, Alignment, Border, Side
from openpyxl.utils import get_column_letter

app = FastAPI()

# ===== Styles =====
ORANGE = PatternFill("solid", fgColor="EA5B2B")
BLUE   = PatternFill("solid", fgColor="2F78BD")   # parents / forced org labels
LIME   = PatternFill("solid", fgColor="CCFF66")   # roles (Ltg/Allg/PoEing/SB/SB-Thema*)
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

# ===== Rights columns =====
PERM_HEADERS = [
    "Lesen","Administrieren","Löschadministration","Ablageadministration",
    "Aktenplanadministration","Vorlagenadministration","Aussonderung",
    "Postverteilung- zentral","Postverteilung- dezentral","Designkonfiguration",
]
PERM_SUFFIX = ["RO","FA","LA","AA","APA","VA","AUS","POZ","POD","DK"]

# ===== Rules =====
ROLE_LIME  = {"Ltg","Allg","PoEing","SB"}         # plus SB-Thema*
FORCE_BLUE = {"Org", "Org Name"}                  # force blue on these labels
GROUPS_FROM_LEVEL = 3                             # start groups one level deeper than Org
SHEET_NAME = "GE_Gruppenstruktur"

# Rows (match your placement)
ROW_BAND    = 2   # eDir
ROW_TITLE   = 3   # "Struktur Gruppen mit Schreibzugriff" at A3
ROW_CAPTION = 3   # "auf Knoten ..." over rights
ROW_HEADERS = 5   # rights column headers
DATA_START_ROW = 4  # tree rows start here

# ---------- helpers ----------
def is_role(name: str) -> bool:
    n = (name or "").strip()
    return n in ROLE_LIME or n.startswith("SB-Thema")

def tree_max_depth(nodes, level=0):
    """Max depth (0=root level)."""
    if not nodes:
        return level - 1
    m = level
    for n in nodes:
        m = max(m, tree_max_depth(n.get("children") or [], level + 1))
    return m

def create_sheet(last_name_col: int, perm_cols: int):
    """
    Columns:
      1..last_name_col-1 : connector columns (│/├/└)
      last_name_col      : name column (deepest name sits here)
      last_name_col+1    : spacer (blank)
      last_name_col+2..  : rights
    """
    spacer_col = last_name_col + 1
    perm_start = spacer_col + 1
    perm_end   = perm_start + perm_cols - 1

    wb = Workbook()
    ws = wb.active
    ws.title = SHEET_NAME

    # widths: compact connectors, wide name, fixed rights
    for c in range(1, last_name_col):
        ws.column_dimensions[get_column_letter(c)].width = 4
    ws.column_dimensions[get_column_letter(last_name_col)].width = 26
    ws.column_dimensions[get_column_letter(spacer_col)].width = 2
    for c in range(perm_start, perm_end + 1):
        ws.column_dimensions[get_column_letter(c)].width = 22

    # header row heights
    ws.row_dimensions[ROW_BAND].height = 24
    ws.row_dimensions[ROW_TITLE].height = 20
    ws.row_dimensions[ROW_HEADERS-1].height = 18  # row 4 visual gap under titles

    # Row 2: eDir band — merge ONLY over spacer+rights (like your screenshot)
    ws.merge_cells(start_row=ROW_BAND, start_column=spacer_col, end_row=ROW_BAND, end_column=perm_end)
    c = ws.cell(row=ROW_BAND, column=spacer_col, value="eDir")
    c.fill = ORANGE; c.font = WHITEB; c.alignment = CENTER; c.border = BOX

    # Row 3: A3 left title
    ws.cell(row=ROW_TITLE, column=1, value="Struktur Gruppen mit Schreibzugriff").font = BLACKB

    # Row 3: caption merged over rights block
    ws.merge_cells(start_row=ROW_CAPTION, start_column=perm_start, end_row=ROW_CAPTION, end_column=perm_end)
    t = ws.cell(row=ROW_CAPTION, column=perm_start, value="auf Knoten zusätzlich anzulegende Gruppen")
    t.font = BLACKB; t.alignment = CENTER

    # Row 5: rights headers
    for j, hdr in enumerate(PERM_HEADERS, start=perm_start):
        h = ws.cell(row=ROW_HEADERS, column=j, value=hdr)
        h.font = BLACKB; h.alignment = CENTER; h.border = BOX

    return wb, ws, spacer_col, perm_start, perm_end

def draw_connectors(ws, row, level, last_stack):
    """
    Persist vertical lines (│) through all ancestor levels where the branch is still open,
    and draw ├/└ at the current level.
    """
    if level == 0:
        return
    for depth in range(1, level + 1):
        col = depth
        if depth == level:
            ch = "└" if last_stack[depth - 1] else "├"
        else:
            ch = "│" if not last_stack[depth - 1] else ""
        if ch:
            cc = ws.cell(row=row, column=col, value=ch)
            cc.font = GRAY
            cc.alignment = LEFT

def style_name_cell(cell, name, has_children):
    cell.alignment = LEFT
    cell.border = BOX
    n = (name or "").strip()
    if has_children or n in FORCE_BLUE:
        cell.fill = BLUE; cell.font = WHITEB
    elif is_role(n):
        cell.fill = LIME; cell.font = BLACKB
    else:
        cell.font = BLACK

def write_rows(ws, nodes, row, level, last_stack, spacer_col, perm_start):
    """
    level is 0-based. The name for this level sits in column (level+1).
    """
    name_col = level + 1
    for i, node in enumerate(nodes):
        raw = node.get("name", "")
        name = (raw or "").strip()
        children = node.get("children") or []
        # Skip truly empty entries with no children
        if not name and not children:
            continue

        is_last = (i == len(nodes) - 1)

        # connectors
        draw_connectors(ws, row, level, last_stack + [is_last])

        # name
        nc = ws.cell(row=row, column=name_col, value=name if name else "(unnamed)")
        style_name_cell(nc, name, bool(children))

        # dashes: from after name up to spacer-1
        for dc in range(name_col + 1, spacer_col):
            d = ws.cell(row=row, column=dc, value="-")
            d.alignment = CENTER; d.border = BOX

        # spacer (blank)
        ws.cell(row=row, column=spacer_col, value="")

        # rights only from configured depth
        if level >= GROUPS_FROM_LEVEL and name:
            for j, suf in enumerate(PERM_SUFFIX, start=perm_start):
                g = ws.cell(row=row, column=j, value=f"{name}-{suf}")
                g.alignment = LEFT; g.border = BOX

        row += 1
        if children:
            row = write_rows(ws, children, row, level + 1, last_stack + [is_last],
                             spacer_col, perm_start)
    return row


# ---------- endpoint ----------
@app.post("/generate-excel")
async def generate_excel(request: Request):
    data = await request.json()
    tree = data.get("tree")
    if not tree:
        return {"error": "Missing 'tree' in JSON body"}

    # Compute deepest level to position spacer+rights after the structure
    max_depth = max(0, tree_max_depth(tree))   # 0 for roots
    last_name_col = max_depth + 1              # names use columns 1..last_name_col

    wb, ws, spacer_col, perm_start, perm_end = create_sheet(last_name_col, len(PERM_HEADERS))

    # Tree rows begin at row 4 like in your file
    write_rows(ws, tree, DATA_START_ROW, level=0, last_stack=[],
               spacer_col=spacer_col, perm_start=perm_start)

    buf = io.BytesIO()
    wb.save(buf); buf.seek(0)
    return StreamingResponse(
        buf,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": "attachment; filename=tree.xlsx"}
    )
