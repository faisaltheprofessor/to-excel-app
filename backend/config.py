PREFIX = "203_"
GROUPS_FROM_LEVEL = 3

SHEET_NAME  = "GE_Gruppenstruktur"
SHEET2_NAME = "Strukt. Ablage Behörde"
SHEET3_NAME = "Geschäftsrollen"

ROW_BAND, ROW_TITLE, ROW_CAPTION, ROW_HEADERS = 2, 3, 3, 5
DATA_START_ROW = 4

# Wrapped ancestors: .PANKOW -> ba -> DigitaleAkte-203
SKIP_PARENTS = 3

ORG_NAME = "BA-PANKOW"
ADMIN_ACCOUNT = "T1_PL_extMA_DigitaleAkte_Fach_Admin_Role@admin"

PERM_HEADERS = [
    "Lesen", "Schreiben", "Administrieren", "Löschadministration", "Ablageadministration",
    "Aktenplanadministration", "Vorlagenadministration", "Aussonderung",
    "Postverteilung- zentral", "Postverteilung- dezentral", "Designkonfiguration",
]
PERM_SUFFIX = ["RO", "", "FA", "LA", "AA", "APA", "VA", "AUS", "POZ", "POD", "DK"]

LEFT_HEADERS = [h for h in PERM_HEADERS if h != "Schreiben"]
LEFT_SUFFIX  = [s for h, s in zip(PERM_HEADERS, PERM_SUFFIX) if h != "Schreiben"]

FORCE_BLUE = {"Org", "Org Name"}

ROLES_COUNT = 10
ROLE_HEADER_TEXT = "Rollenname"
