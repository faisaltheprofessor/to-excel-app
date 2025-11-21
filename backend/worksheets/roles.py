from openpyxl import Workbook
from openpyxl.styles import Border, PatternFill
from config import SHEET3_NAME, SKIP_PARENTS, ROLE_HEADER_TEXT
from styles import BLUE, PALEGR, GRID_GRAY, THICK, THICK_BOTTOM, BOX, LEFT, BLACKB, GRAYB, WHITEB
from utils import strip_prefix_levels, autosize_columns, gray_out_row_full

DISABLED_BLUE = PatternFill("solid", fgColor="9FB7D9")


def fill_row(ws, row, start_col, end_col, fill):
    for c in range(start_col, end_col + 1):
        ws.cell(row=row, column=c).fill = fill


def roles_sheet_header(ws, roles_count: int):
    ws.cell(row=2, column=1).value = "Ablagen / Hierarchieelemente"
    ws.cell(row=2, column=1).font = BLACKB
    ws.cell(row=2, column=1).alignment = LEFT

    ws.cell(row=2, column=3).value = "Beschreibung"
    ws.cell(row=2, column=3).font = BLACKB
    ws.cell(row=2, column=3).alignment = LEFT

    col = 4
    for k in range(roles_count):
        ws.merge_cells(start_row=1, start_column=col, end_row=1, end_column=col + 2)
        ws.cell(row=1, column=col).value = f"{ROLE_HEADER_TEXT} {k+1}"
        ws.cell(row=1, column=col).font = BLACKB
        ws.cell(row=1, column=col).alignment = LEFT

        ws.cell(row=2, column=col).value = "Lesen"
        ws.cell(row=2, column=col).font = BLACKB
        ws.cell(row=2, column=col).alignment = LEFT

        ws.cell(row=2, column=col + 1).value = "Schreiben"
        ws.cell(row=2, column=col + 1).font = BLACKB
        ws.cell(row=2, column=col + 1).alignment = LEFT

        ws.cell(row=2, column=col + 2).value = "LA"
        ws.cell(row=2, column=col + 2).font = BLACKB
        ws.cell(row=2, column=col + 2).alignment = LEFT

        col += 3

    from openpyxl.utils import get_column_letter
    ws.column_dimensions[get_column_letter(1)].width = 38
    ws.column_dimensions[get_column_letter(2)].width = 2
    ws.column_dimensions[get_column_letter(3)].width = 56
    for c in range(4, 4 + roles_count * 3):
        ws.column_dimensions[get_column_letter(c)].width = 10


def apply_role_vertical_borders(ws, roles_count: int, end_row: int):
    total_cols = 3 + roles_count * 3

    for k in range(roles_count):
        base = 4 + k * 3
        mid = base + 1
        last = base + 2

        for r in range(1, end_row + 1):
            ws.cell(row=r, column=base).border = Border(
                left=ws.cell(row=r, column=base).border.left,
                right=GRID_GRAY,
                top=ws.cell(row=r, column=base).border.top,
                bottom=ws.cell(row=r, column=base).border.bottom,
            )
            ws.cell(row=r, column=mid).border = Border(
                left=ws.cell(row=r, column=mid).border.left,
                right=GRID_GRAY,
                top=ws.cell(row=r, column=mid).border.top,
                bottom=ws.cell(row=r, column=mid).border.bottom,
            )
            ws.cell(row=r, column=last).border = Border(
                left=ws.cell(row=r, column=last).border.left,
                right=THICK,
                top=ws.cell(row=r, column=last).border.top,
                bottom=ws.cell(row=r, column=last).border.bottom,
            )

    for r in range(1, end_row + 1):
        ws.cell(row=r, column=4).border = Border(
            left=THICK,
            right=ws.cell(row=r, column=4).border.right,
            top=ws.cell(row=r, column=4).border.top,
            bottom=ws.cell(row=r, column=4).border.bottom,
        )

    for r in range(1, end_row + 1):
        ws.cell(row=r, column=2).border = Border(
            left=GRID_GRAY,
            right=ws.cell(row=r, column=2).border.right,
            top=ws.cell(row=r, column=2).border.top,
            bottom=ws.cell(row=r, column=2).border.bottom,
        )

    for r in range(1, end_row + 1):
        ws.cell(row=r, column=3).border = Border(
            left=GRID_GRAY,
            right=ws.cell(row=r, column=3).border.right,
            top=ws.cell(row=r, column=3).border.top,
            bottom=ws.cell(row=r, column=3).border.bottom,
        )

    for r in range(1, end_row + 1):
        ws.cell(row=r, column=total_cols).border = Border(
            left=ws.cell(row=r, column=total_cols).border.left,
            right=THICK,
            top=ws.cell(row=r, column=total_cols).border.top,
            bottom=ws.cell(row=r, column=total_cols).border.bottom,
        )


def apply_gray_horizontal_grid(ws, start_row: int, end_row: int, total_cols: int):
    for c in range(1, total_cols + 1):
        ws.cell(row=2, column=c).border = Border(
            left=ws.cell(row=2, column=c).border.left,
            right=ws.cell(row=2, column=c).border.right,
            top=ws.cell(row=2, column=c).border.top,
            bottom=GRID_GRAY,
        )
    for r in range(start_row, end_row + 1):
        for c in range(1, total_cols + 1):
            ws.cell(row=r, column=c).border = Border(
                left=ws.cell(row=r, column=c).border.left,
                right=ws.cell(row=r, column=c).border.right,
                top=GRID_GRAY,
                bottom=ws.cell(row=r, column=c).border.bottom,
            )


def apply_thick_bottom(ws, total_cols: int, end_row: int):
    for c in range(1, total_cols + 1):
        ws.cell(row=end_row, column=c).border = Border(
            left=ws.cell(row=end_row, column=c).border.left,
            right=ws.cell(row=end_row, column=c).border.right,
            top=ws.cell(row=end_row, column=c).border.top,
            bottom=THICK_BOTTOM,
        )


def write_role_row(ws, row_idx, label, desc, total_cols, fill):
    ws.cell(row=row_idx, column=1).value = label
    ws.cell(row=row_idx, column=1).alignment = LEFT
    ws.cell(row=row_idx, column=3).value = desc
    ws.cell(row=row_idx, column=3).alignment = LEFT
    fill_row(ws, row_idx, 1, total_cols, fill)


def get_label(node):
    return (node.get("appName") or node.get("name") or "").strip()


def get_desc(node):
    return (node.get("description") or "").strip()


def is_ab_block(label: str):
    return label.startswith("Ab_")


def write_roles_for_node(ws, node, row_idx, roles_count, in_ab_tree=False):
    total_cols = 3 + roles_count * 3
    label = get_label(node)
    if not label:
        return row_idx

    children = node.get("children") or []
    is_parent = bool(children)
    disabled = node.get("enabled") is False

    if is_ab_block(label) or (in_ab_tree and is_parent):
        ws.cell(row=row_idx, column=1).value = ""
        row_idx += 1

    current_row = row_idx

    if is_parent:
        fill = DISABLED_BLUE if disabled else BLUE
    else:
        fill = PALEGR

    write_role_row(ws, current_row, label, get_desc(node), total_cols, fill)

    if is_parent:
        for c in range(1, total_cols + 1):
            ws.cell(row=current_row, column=c).font = WHITEB if not disabled else GRAYB
    else:
        if disabled:
            gray_out_row_full(ws, current_row, total_cols)

    row_idx = current_row + 1

    next_in_ab_tree = in_ab_tree or is_ab_block(label)
    for ch in children:
        row_idx = write_roles_for_node(ws, ch, row_idx, roles_count, next_in_ab_tree)

    if is_parent:
        ws.cell(row=row_idx, column=1).value = ""
        row_idx += 1

    return row_idx


def is_blank_row(ws, row, total_cols: int):
    for c in range(1, total_cols + 1):
        v = ws.cell(row=row, column=c).value
        if v not in (None, ""):
            return False
    return True


def compress_blank_rows(ws, start_row: int, total_cols: int):
    r = ws.max_row
    prev_blank = False
    while r >= start_row:
        if is_blank_row(ws, r, total_cols):
            if prev_blank:
                ws.delete_rows(r, 1)
            else:
                prev_blank = True
        else:
            prev_blank = False
        r -= 1


def find_last_populated_row(ws, total_cols: int):
    max_row = ws.max_row
    for r in range(max_row, 2, -1):
        if ws.cell(row=r, column=1).value not in (None, "") or ws.cell(row=r, column=3).value not in (None, ""):
            return r
    return 2


def add_third_sheet(wb: Workbook, tree, roles_count: int):
    ws = wb.create_sheet(title=SHEET3_NAME)
    roles_sheet_header(ws, roles_count)

    working_nodes = strip_prefix_levels(tree, SKIP_PARENTS)

    r = 3
    for top in working_nodes:
        r = write_roles_for_node(ws, top, r, roles_count, False)

    total_cols = 3 + roles_count * 3
    compress_blank_rows(ws, 3, total_cols)

    end_row = find_last_populated_row(ws, total_cols)

    apply_role_vertical_borders(ws, roles_count, end_row)
    apply_gray_horizontal_grid(ws, 3, end_row, total_cols)
    apply_thick_bottom(ws, total_cols, end_row)

    USER_ROWS = 15
    bottom_start = end_row + 1
    bottom_end = end_row + USER_ROWS

    ws.merge_cells(start_row=bottom_start, start_column=1, end_row=bottom_end, end_column=3)
    label_cell = ws.cell(row=bottom_start, column=1)
    label_cell.value = (
        "Benutzer\n\n"
        "Pro Zelle genau EIN Benutzer.\n"
        "Benutzer nur in diesem Bereich eintragen – je Rolle in den zugehörigen Feldern.\n"
        "Eintragung spaltenweise oder zeilenweise möglich."
    )
    label_cell.font = BLACKB
    label_cell.alignment = LEFT
    label_cell.border = BOX

    for rr in range(bottom_start, bottom_end + 1):
        for cc in range(4, total_cols + 1):
            ws.cell(row=rr, column=cc).value = ""

    apply_role_vertical_borders(ws, roles_count, bottom_end)
    apply_gray_horizontal_grid(ws, bottom_start, bottom_end, total_cols)
    apply_thick_bottom(ws, total_cols, bottom_end)

    ws.freeze_panes = "D3"
    autosize_columns(ws, 8, 60)
    return ws
