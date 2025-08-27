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
SHEET_NAME  = "GE_Gruppenstruktur"
SHEET2_NAME = "Strukt. Ablage Behörde"

ROW_BAND, ROW_TITLE, ROW_CAPTION, ROW_HEADERS = 2, 3, 3, 5
DATA_START_ROW = 4

# Strip the first N wrapper parents on sheet 2 (these are included on sheet 1)
SKIP_PARENTS = 3

# Placeholders
ORG_NAME = "BA-PANKOW"
ADMIN_ACCOUNT = "T1_PL_extMA_DigitaleAkte_Fach_Admin_Role"

# ===== Styles =====
ORANGE = PatternFill("solid", fgColor="EA5B2B")
CYAN   = PatternFill("solid", fgColor="63D3FF")
PALEGR = PatternFill("solid", fgColor="D8F4D2")
BLUE   = PatternFill("solid", fgColor="2F78BD")
LIME   = PatternFill("solid", fgColor="CCFF66")
GREEN  = PatternFill("solid", fgColor="A9D08E")
YELLOW = PatternFill("solid", fgColor="FFF2CC")

WHITEB = Font(color="FFFFFF", bold=True)
BLACK  = Font(color="000000")
BLACKB = Font(color="000000", bold=True)
GRAY   = Font(color="808080")

LEFT   = Alignment(horizontal="left",  vertical="center", wrap_text=False)
CENTER = Alignment(horizontal="center",vertical="center", wrap_text=False)

BOX = Border(
    left=Side(style="thin", color="D0D0D0"),
    right=Side(style="thin", color="D0D0D0"),
    top=Side(style="thin", color="D0D0D0"),
    bottom=Side(style="thin", color="D0D0D0"),
)
BOX2 = Border(
    left=Side(style="thin", color="000000"),
    right=Side(style="thin", color="000000"),
    top=Side(style="thin", color="000000"),
    bottom=Side(style="thin", color="000000"),
)
THICK_BOTTOM = Side(style="thick", color="000000")
THICK_TOP    = Side(style="thick", color="000000")

# Permissions
PERM_HEADERS = [
    "Lesen", "Schreiben", "Administrieren", "Löschadministration", "Ablageadministration",
    "Aktenplanadministration", "Vorlagenadministration", "Aussonderung",
    "Postverteilung- zentral", "Postverteilung- dezentral", "Designkonfiguration",
]
PERM_SUFFIX = ["RO", "", "FA", "LA", "AA", "APA", "VA", "AUS", "POZ", "POD", "DK"]

LEFT_HEADERS = [h for h in PERM_HEADERS if h != "Schreiben"]
LEFT_SUFFIX  = [s for h, s in zip(PERM_HEADERS, PERM_SUFFIX) if h != "Schreiben"]

FORCE_BLUE = {"Org", "Org Name"}

def norm_token(s: str) -> str:
    """Normalize but KEEP dashes (SB-Thema stays SB-Thema)."""
    s = (s or "").strip()
    s = re.sub(r"[^\w\-]+", "_", s)  # allow letters, digits, underscore, dash
    s = re.sub(r"_+", "_", s).strip("_")
    return s

def tree_max_depth(nodes, level=0):
    if not nodes:
        return level - 1
    m = level
    for n in nodes:
        m = max(m, tree_max_depth(n.get("children") or [], level + 1))
    return m

# ---------- SHEET 1 ----------
def create_sheet(last_name_col:int, perm1_cols:int, perm2_cols:int, max_depth:int):
    spacer1 = last_name_col + 1
    perm1_s = spacer1 + 1
    perm1_e = perm1_s + perm1_cols - 1
    spacer2 = perm1_e + 1
    perm2_s = spacer2 + 1
    perm2_e = perm2_s + perm2_cols - 1
    spacer3 = perm2_e + 1
    flat_col = spacer3 + 1
    spacer4 = flat_col + 1
    tree_base = spacer4 + 1
    tree_end  = tree_base + max_depth

    wb = Workbook(); ws = wb.active; ws.title = SHEET_NAME

    for c in range(1, last_name_col):
        ws.column_dimensions[get_column_letter(c)].width = 4
    ws.column_dimensions[get_column_letter(last_name_col)].width = 26
    for c in [spacer1, spacer2, spacer3, spacer4]:
        ws.column_dimensions[get_column_letter(c)].width = 2
    for c in list(range(perm1_s, perm1_e+1)) + list(range(perm2_s, perm2_e+1)):
        ws.column_dimensions[get_column_letter(c)].width = 22
    ws.column_dimensions[get_column_letter(flat_col)].width = 26
    for c in range(tree_base, tree_end+1):
        ws.column_dimensions[get_column_letter(c)].width = 6

    for r,h in [(ROW_BAND,24),(ROW_TITLE,20),(ROW_HEADERS-1,18)]:
        ws.row_dimensions[r].height = h

    # Header bands
    ws.merge_cells(start_row=ROW_BAND, start_column=perm1_s, end_row=ROW_BAND, end_column=perm1_e)
    c1 = ws.cell(row=ROW_BAND, column=perm1_s, value="eDir")
    c1.fill = ORANGE; c1.font = WHITEB; c1.alignment = CENTER; c1.border = BOX

    chip = ws.cell(row=ROW_BAND, column=perm2_s, value="eDir")
    chip.fill = ORANGE; chip.font = WHITEB; chip.alignment = CENTER; chip.border = BOX

    ws.merge_cells(start_row=ROW_BAND, start_column=perm2_s+1, end_row=ROW_BAND, end_column=perm2_e)
    c2 = ws.cell(row=ROW_BAND, column=perm2_s+1, value="AD(DFSW)")
    c2.fill = CYAN; c2.font = BLACKB; c2.alignment = CENTER; c2.border = BOX

    ws.cell(row=ROW_TITLE, column=1, value="Struktur Gruppen mit Schreibzugriff").font = BLACKB
    ws.merge_cells(start_row=ROW_CAPTION, start_column=perm1_s, end_row=ROW_CAPTION, end_column=perm1_e)
    ws.cell(row=ROW_CAPTION, column=perm1_s, value="auf Knoten zusätzlich anzulegende Gruppen").font = BLACKB

    for j,hdr in enumerate(LEFT_HEADERS, start=perm1_s):
        hc = ws.cell(row=ROW_HEADERS, column=j, value=hdr)
        hc.font = BLACKB; hc.alignment = CENTER; hc.border = BOX
    for j,hdr in enumerate(PERM_HEADERS, start=perm2_s):
        hc = ws.cell(row=ROW_HEADERS, column=j, value=hdr)
        hc.font = BLACKB; hc.alignment = CENTER; hc.border = BOX

    ws.merge_cells(start_row=ROW_BAND, start_column=flat_col, end_row=ROW_BAND, end_column=tree_end)
    ab = ws.cell(row=ROW_BAND, column=flat_col, value="nscale strukturierte Ablage")
    ab.fill = PALEGR; ab.font = BLACKB; ab.alignment = LEFT; ab.border = BOX

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
    # no ASCII tree art in the sheet
    return

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

def is_ablg(s: str) -> bool:
    return (s or "").strip().lower() == "ablgoe"

def is_poeing(s: str) -> bool:
    return (s or "").strip().lower() == "poeing"

def write_rows(ws, nodes, row, level, last_stack, cols, lineage_names, lineage_apps):
    name_col = level + 1
    for i, node in enumerate(nodes):
        name = (node.get("name") or "").strip()
        appn = (node.get("appName") or name).strip()
        children = node.get("children") or []
        if not name and not children:
            continue
        is_last = (i == len(nodes) - 1)

        if is_ablg(appn) or is_ablg(name):
            row += 1

        draw_connectors(ws, row, level, last_stack+[is_last], base_col=1)
        nc = ws.cell(row=row, column=name_col, value=name if name else "(unnamed)")
        style_name_cell(nc, name, bool(children))

        # filler until left spacer
        for dc in range(name_col+1, cols["spacer1"]):
            d = ws.cell(row=row, column=dc, value="-")
            d.alignment = CENTER; d.border = BOX

        for sc in [cols["spacer1"], cols["spacer2"], cols["spacer3"], cols["spacer4"]]:
            ws.cell(row=row, column=sc, value="")

        # LEFT (no "Schreiben")
        if level >= GROUPS_FROM_LEVEL and name:
            for j, suf in enumerate(LEFT_SUFFIX, start=cols["perm1_s"]):
                val = f"{name}-{suf}" if suf else name
                g = ws.cell(row=row, column=j, value=val)
                g.alignment = LEFT; g.border = BOX

        # RIGHT canonical groups (Sheet 2 must mirror this base)
        if level >= GROUPS_FROM_LEVEL and name:
            tokens = lineage_names + [norm_token(name)]
            start = min(GROUPS_FROM_LEVEL, len(tokens) - 1)
            key = PREFIX + "_".join(tokens[start:])
            for j, suf in enumerate(PERM_SUFFIX, start=cols["perm2_s"]):
                val = f"{key}-{suf}" if suf else key
                g = ws.cell(row=row, column=j, value=val)
                g.alignment = LEFT; g.border = BOX

        flat_label = appn if appn else (name if name else "(unnamed)")
        fc = ws.cell(row=row, column=cols["flat_col"], value=flat_label)
        style_cell_like_node(fc, flat_label, bool(children))

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

        if is_ablg(appn) or is_ablg(name):
            row += 1

    return row

def autosize_columns(ws, min_w=10, max_w=120):
    for col in ws.columns:
        col = list(col)
        letter = get_column_letter(col[0].column)
        max_len = 0
        for cell in col:
            val = "" if cell.value is None else str(cell.value)
            max_len = max(max_len, len(val))
        ws.column_dimensions[letter].width = max(min_w, min(max_w, max_len + 2))

# ---------- SHEET 2 helpers ----------
def strip_prefix_levels(nodes, n):
    cur = nodes
    for _ in range(n):
        if not cur:
            return []
        first = cur[0]
        cur = first.get("children") or []
    return cur

def allowed_types_for(label, typ):
    lab = (label or "").lower()
    if typ == "Posteingang" or lab.startswith("pe_") or "poeing" in lab:
        return ""
    return "GOV_FILE" if typ == "Aktenablage" else ""

def break_inheritance_from_node(node, desc_text):
    v = node.get("unterbrechen")
    if isinstance(v, str) and v.strip().lower() in {"wahr", "true", "ja"}:
        return "WAHR"
    if v is True:
        return "WAHR"
    if "ohne leitungszugriff" in (desc_text or "").lower():
        return "WAHR"
    return ""

# Use the SAME schema base as Sheet 1 RIGHT (use names lineage; wrappers already stripped)
def build_group_key_from_names(path_names):
    tokens = [norm_token(x) for x in path_names if (x or "").strip()]
    return PREFIX + "_".join(tokens) if tokens else ""

def set_row_top_thick(ws, row, col_start=1, col_end=11):
    for c in range(col_start, col_end + 1):
        cell = ws.cell(row=row, column=c)
        b = cell.border
        cell.border = Border(
            left=b.left or Side(style="thin", color="000000"),
            right=b.right or Side(style="thin", color="000000"),
            top=THICK_TOP,
            bottom=b.bottom or Side(style="thin", color="000000"),
        )

def set_row_bottom_thick(ws, row, col_start=1, col_end=11):
    for c in range(col_start, col_end + 1):
        cell = ws.cell(row=row, column=c)
        b = cell.border
        cell.border = Border(
            left=b.left or Side(style="thin", color="000000"),
            right=b.right or Side(style="thin", color="000000"),
            top=b.top or Side(style="thin", color="000000"),
            bottom=THICK_BOTTOM,
        )

def set_group_bottom_thick(ws, row_last, col_start=1, col_end=11):
    set_row_bottom_thick(ws, row_last, col_start, col_end)

def make_addr(group_key: str, suffix: str = "") -> str:
    """Return '<group[-suffix]>@ORG_NAME' (no admin; we'll append admin ONCE later)."""
    key = f"{group_key}-{suffix}" if suffix else group_key
    return f"{key}@{ORG_NAME}"

def write_group_recursive(ws, node, r, path_names, path_apps, poeings):
    """
    Sheet 2 writer:
      - Column A uses appName as display.
      - Permission cells G..K use the SAME base key as Sheet 1 RIGHT (names lineage with PREFIX).
      - LA column lists LA groups for ALL ancestors up to current *once*, and the admin account
        is appended only ONCE at the very end of the semicolon-joined string in each permission cell.
      - For PoKorb creation we collect app-paths at Pe_* nodes.
    """
    name = (node.get("name") or "").strip()
    appn = (node.get("appName") or name).strip()
    kids = node.get("children") or []
    desc = (node.get("description") or "").strip()

    label = appn if appn else (name if name else "(unnamed)")  # appName label
    is_parent = bool(kids)
    parent_raw = path_apps[-1] if path_apps else ""

    display_a = label
    parent_display = parent_raw
    typ = "Hierarchieelement" if is_parent else "Aktenablage"

    # Permission base from NAMES (SHEET 1 RIGHT schema)
    full_names_path = path_names + [name]
    perm_key = build_group_key_from_names(full_names_path)

    # Build ancestor keys (for LA)
    ancestor_keys = [build_group_key_from_names(full_names_path[:i]) for i in range(1, len(full_names_path)+1)]

    # A..F
    values = [display_a, desc, parent_display, typ,
              break_inheritance_from_node(node, desc),
              allowed_types_for(label, "Posteingang" if is_poeing(label) else typ)]
    for j, v in enumerate(values, start=1):
        c = ws.cell(row=r, column=j, value=v)
        c.alignment = LEFT
        c.border = BOX2
    ws.cell(row=r, column=1).fill = YELLOW

    # ---- Permissions G..K (append ADMIN_ACCOUNT only ONCE at the end) ----
    g_list = [make_addr(perm_key, "RO")]
    h_list = [make_addr(perm_key, "")]
    i_list = [make_addr(perm_key, "FA")]
    j_list = [make_addr(k, "LA") for k in ancestor_keys]   # all ancestors for Löschadministration
    k_list = [make_addr(perm_key, "AA")]

    cells_lists = [g_list, h_list, i_list, j_list, k_list]
    for offset, items in enumerate(cells_lists, start=7):
        # join addresses and add admin ONCE at the end
        cell_val = ";".join(items) + f";{ADMIN_ACCOUNT}"
        c = ws.cell(row=r, column=offset, value=cell_val)
        c.alignment = LEFT
        c.border = BOX2

    # Emphasis
    ws.cell(row=r, column=1).font = (BLACKB if is_parent else BLACK)
    for j in range(2, 12):
        ws.cell(row=r, column=j).font = BLACK
    if is_parent:
        set_row_top_thick(ws, r, 1, 11)

    current_row = r

    # Remember Pe_* locations for PoKorb creation: store the *app* path (ancestors' app names)
    if is_poeing(name) or is_poeing(appn) or label.lower().startswith("pe_"):
        poeings.append({"path": list(path_apps)})

    # Recurse
    for child in kids:
        current_row += 1
        current_row, _ = write_group_recursive(
            ws,
            child,
            current_row,
            path_names = full_names_path,
            path_apps  = path_apps + [label],
            poeings    = poeings
        )

    if is_parent:
        set_group_bottom_thick(ws, current_row, 1, 11)

    return current_row, poeings

def add_second_sheet(wb: Workbook, tree):
    ws = wb.create_sheet(title=SHEET2_NAME)

    headers = [
        "Bezeichnung strukturierte Ablage",
        "Beschreibung",
        "Eltern strukturierte Ablage",
        "Art",
        "Vererbung aus übergeordnetem Element unterbrechen",
        "Erlaubte Aktentypen",
        "Lesen", "Schreiben", "Administrieren", "Löschadministration", "Ablageadministration"
    ]
    for col_idx, h in enumerate(headers, start=1):
        c = ws.cell(row=1, column=col_idx, value=h)
        c.font = BLACKB; c.alignment = LEFT; c.border = BOX2

    # Green header band G..K
    for col_idx in range(7, 12):
        ws.cell(row=1, column=col_idx).fill = GREEN

    # Work on tree without first 3 wrapper levels (sheet 1 includes them)
    working_nodes = strip_prefix_levels(tree, SKIP_PARENTS)

    r = 2
    all_poeings = []

    for top in working_nodes:
        r, poe = write_group_recursive(ws, top, r, path_names=[], path_apps=[], poeings=[])
        all_poeings.extend(poe)
        r += 1  # spacer between top-level groups

    # === PoKorb rows ===
    # EXACT rule: PoKorb_<AppNameOfParentOfAblgOE>; parent column = Pe_<AppNameOfParentOfAblgOE>
    # Col G..K for PoKorb remain empty (no perms) — requirement unchanged.
    for pe in all_poeings:
        parent_app = pe["path"][-2] if len(pe["path"]) >= 2 else ""   # AppName of parent of AblgOE
        label  = f"PoKorb_{parent_app}" if parent_app else "PoKorb"
        parent = f"Pe_{parent_app}"     if parent_app else "Pe"

        values = [
            label,
            f"Postkorb {parent_app}" if parent_app else "Postkorb",
            parent,
            "Posteingang",
            "",
            "",  # Erlaubte Aktentypen = empty for PoKorb
        ]
        for j, v in enumerate(values, start=1):
            c = ws.cell(row=r, column=j, value=v)
            c.alignment = LEFT; c.border = BOX2
        ws.cell(row=r, column=1).fill = YELLOW
        for j in range(7, 12):
            c = ws.cell(row=r, column=j, value="")
            c.alignment = LEFT; c.border = BOX2
        ws.cell(row=r, column=1).font = BLACK
        for j in range(2, 12):
            ws.cell(row=r, column=j).font = BLACK
        r += 1

    ws.freeze_panes = "A2"
    autosize_columns(ws, min_w=10, max_w=120)
    return ws

# ---------- API ----------
@app.post("/generate-excel")
async def generate_excel(request: Request):
    data = await request.json()
    tree = data.get("tree")
    if not tree:
        return {"error": "Missing 'tree' in JSON body"}

    max_depth = max(0, tree_max_depth(tree))
    last_name_col = max_depth + 1

    wb, ws, cols = create_sheet(
        last_name_col,
        perm1_cols=len(LEFT_HEADERS),
        perm2_cols=len(PERM_HEADERS),
        max_depth=max_depth
    )

    write_rows(ws, tree, DATA_START_ROW, level=0, last_stack=[],
               cols=cols, lineage_names=[], lineage_apps=[])

    autosize_columns(ws, min_w=8, max_w=60)

    add_second_sheet(wb, tree)

    buf = io.BytesIO()
    wb.save(buf)
    buf.seek(0)
    return StreamingResponse(
        buf,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": "attachment; filename=tree.xlsx"}
    )
