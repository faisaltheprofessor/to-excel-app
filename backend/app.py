from fastapi import FastAPI, Request
from fastapi.responses import StreamingResponse
import io, re
from openpyxl import Workbook
from openpyxl.styles import PatternFill, Font, Alignment, Border, Side
from openpyxl.utils import get_column_letter

app = FastAPI()

# ===== Config =====
PREFIX = "203_"                   # <-- change freely
GROUPS_FROM_LEVEL = 3             # start one level deeper than Org

# ===== Styles =====
ORANGE = PatternFill("solid", fgColor="EA5B2B")
CYAN   = PatternFill("solid", fgColor="63D3FF")
BLUE   = PatternFill("solid", fgColor="2F78BD")
LIME   = PatternFill("solid", fgColor="CCFF66")
WHITEB = Font(color="FFFFFF", bold=True)
BLACK  = Font(color="000000")
BLACKB = Font(color="000000", bold=True)
GRAY   = Font(color="808080")
LEFT   = Alignment(horizontal="left",  vertical="center")
CENTER = Alignment(horizontal="center",vertical="center")
BOX = Border(*(Side(style="thin", color="D0D0D0") for _ in range(4)))

# ===== Rights =====
PERM_HEADERS = [
    "Lesen","Administrieren","Löschadministration","Ablageadministration",
    "Aktenplanadministration","Vorlagenadministration","Aussonderung",
    "Postverteilung- zentral","Postverteilung- dezentral","Designkonfiguration",
]
PERM_SUFFIX = ["RO","FA","LA","AA","APA","VA","AUS","POZ","POD","DK"]

# ===== Rules =====
ROLE_LIME  = {"Ltg","Allg","PoEing","SB"}  # + SB-Thema*
FORCE_BLUE = {"Org", "Org Name"}
ROW_BAND, ROW_TITLE, ROW_CAPTION, ROW_HEADERS = 2, 3, 3, 5
DATA_START_ROW = 4
SHEET_NAME = "GE_Gruppenstruktur"

def is_role(s):
    s=(s or "").strip(); return s in ROLE_LIME or s.startswith("SB-Thema")
def norm_token(s):
    s=(s or "").strip()
    s=re.sub(r"[^\w]+","_",s); s=re.sub(r"_+","_",s).strip("_")
    return s
def tree_max_depth(nodes, level=0):
    if not nodes: return level-1
    m=level
    for n in nodes: m=max(m, tree_max_depth(n.get("children") or [], level+1))
    return m

def create_sheet(last_name_col, perm_cols):
    spacer1 = last_name_col + 1
    perm1_s = spacer1 + 1; perm1_e = perm1_s + perm_cols - 1
    spacer2 = perm1_e + 1
    perm2_s = spacer2 + 1; perm2_e = perm2_s + perm_cols - 1

    wb = Workbook(); ws = wb.active; ws.title = SHEET_NAME
    for c in range(1, last_name_col): ws.column_dimensions[get_column_letter(c)].width = 4
    ws.column_dimensions[get_column_letter(last_name_col)].width = 26
    ws.column_dimensions[get_column_letter(spacer1)].width = 2
    ws.column_dimensions[get_column_letter(spacer2)].width = 2
    for c in list(range(perm1_s, perm1_e+1))+list(range(perm2_s, perm2_e+1)):
        ws.column_dimensions[get_column_letter(c)].width = 22
    for r,h in [(ROW_BAND,24),(ROW_TITLE,20),(ROW_HEADERS-1,18)]: ws.row_dimensions[r].height=h

    # bands
    ws.merge_cells(start_row=ROW_BAND, start_column=perm1_s, end_row=ROW_BAND, end_column=perm1_e)
    c1=ws.cell(row=ROW_BAND, column=perm1_s, value="eDir"); c1.fill=ORANGE; c1.font=WHITEB; c1.alignment=CENTER; c1.border=BOX
    ws.merge_cells(start_row=ROW_BAND, start_column=perm2_s, end_row=ROW_BAND, end_column=perm2_e)
    c2=ws.cell(row=ROW_BAND, column=perm2_s, value="eDir (Pfadgruppen)"); c2.fill=CYAN; c2.font=BLACKB; c2.alignment=CENTER; c2.border=BOX

    ws.cell(row=ROW_TITLE, column=1, value="Struktur Gruppen mit Schreibzugriff").font = BLACKB
    ws.merge_cells(start_row=ROW_CAPTION, start_column=perm1_s, end_row=ROW_CAPTION, end_column=perm1_e)
    ws.cell(row=ROW_CAPTION, column=perm1_s, value="auf Knoten zusätzlich anzulegende Gruppen").font = BLACKB
    ws.merge_cells(start_row=ROW_CAPTION, start_column=perm2_s, end_row=ROW_CAPTION, end_column=perm2_e)
    ws.cell(row=ROW_CAPTION, column=perm2_s, value=f"Pfadbasierte Gruppen ({PREFIX}<pfad>)").font = BLACKB

    for j,hdr in enumerate(PERM_HEADERS, start=perm1_s):
        hc=ws.cell(row=ROW_HEADERS, column=j, value=hdr); hc.font=BLACKB; hc.alignment=CENTER; hc.border=BOX
    for j,hdr in enumerate(PERM_HEADERS, start=perm2_s):
        hc=ws.cell(row=ROW_HEADERS, column=j, value=hdr); hc.font=BLACKB; hc.alignment=CENTER; hc.border=BOX

    return wb, ws, {"spacer1":spacer1,"perm1_s":perm1_s,"perm1_e":perm1_e,"spacer2":spacer2,"perm2_s":perm2_s,"perm2_e":perm2_e}

def draw_connectors(ws,row,level,last_stack):
    if level==0: return
    for depth in range(1, level+1):
        col=depth
        ch = ("└" if last_stack[depth-1] else "├") if depth==level else ("│" if not last_stack[depth-1] else "")
        if ch:
            cc=ws.cell(row=row,column=col,value=ch); cc.font=GRAY; cc.alignment=LEFT

def style_name_cell(cell,name,has_children):
    cell.alignment=LEFT; cell.border=BOX
    n=(name or "").strip()
    if has_children or n in FORCE_BLUE: cell.fill=BLUE; cell.font=WHITEB
    elif is_role(n): cell.fill=LIME; cell.font=BLACKB
    else: cell.font=BLACK

def write_rows(ws, nodes, row, level, last_stack, coords, lineage):
    name_col = level + 1
    for i,node in enumerate(nodes):
        raw=node.get("name",""); name=(raw or "").strip()
        children=node.get("children") or []
        if not name and not children: continue
        is_last = (i==len(nodes)-1)

        draw_connectors(ws,row,level,last_stack+[is_last])
        nc=ws.cell(row=row, column=name_col, value=name if name else "(unnamed)")
        style_name_cell(nc, name, bool(children))

        for dc in range(name_col+1, coords["spacer1"]):
            d=ws.cell(row=row, column=dc, value="-"); d.alignment=CENTER; d.border=BOX
        ws.cell(row=row, column=coords["spacer1"], value="")
        ws.cell(row=row, column=coords["spacer2"], value="")

        # Block 1: name-based
        if level>=GROUPS_FROM_LEVEL and name:
            for j,suf in enumerate(PERM_SUFFIX, start=coords["perm1_s"]):
                g=ws.cell(row=row, column=j, value=f"{name}-{suf}"); g.alignment=LEFT; g.border=BOX

        # Block 2: path-based (grow from current outward)
        # lineage tokens are from root..parent. Include current:
        tokens = lineage + [norm_token(name)]
        if level>=GROUPS_FROM_LEVEL and name:
            # slice starting at GROUPS_FROM_LEVEL -> for level==L: [current], for level L+1: [parent,current], etc.
            start = min(GROUPS_FROM_LEVEL, len(tokens)-1)
            path_tokens = tokens[start:]
            key = PREFIX + "_".join([t for t in path_tokens if t])
            for j,suf in enumerate(PERM_SUFFIX, start=coords["perm2_s"]):
                g=ws.cell(row=row, column=j, value=f"{key}-{suf}"); g.alignment=LEFT; g.border=BOX

        row += 1
        if children:
            row = write_rows(ws, children, row, level+1, last_stack+[is_last], coords, lineage + [norm_token(name)])
    return row

@app.post("/generate-excel")
async def generate_excel(request: Request):
    data = await request.json()
    tree = data.get("tree")
    if not tree: return {"error":"Missing 'tree' in JSON body"}

    max_depth = max(0, tree_max_depth(tree))
    last_name_col = max_depth + 1
    wb, ws, coords = create_sheet(last_name_col, len(PERM_HEADERS))
    write_rows(ws, tree, DATA_START_ROW, level=0, last_stack=[], coords=coords, lineage=[])

    buf=io.BytesIO(); wb.save(buf); buf.seek(0)
    return StreamingResponse(buf,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition":"attachment; filename=tree.xlsx"})
