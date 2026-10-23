#!/usr/bin/env python3
"""Build an entity-relationship document (DOCX) for the condominium database.

The script reads the authoritative schema dump, recovers the physical foreign keys declared by
the engine plus the logical relations that only exist in the application layer, renders one crow's
foot diagram per domain, and assembles a Word document with the diagrams and the full catalog.

Usage:
    python scripts/generate_er_diagram_docx.py
    python scripts/generate_er_diagram_docx.py --assets-dir out/er-assets --keep-assets

Dependencies (Pillow, python-docx) are intentionally not part of the project. Run it from a
throwaway virtualenv; see the method note in the generated document.
"""

from __future__ import annotations

import argparse
import datetime as dt
import math
import re
import sys
import tempfile
from dataclasses import dataclass, field
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.section import WD_ORIENT, WD_SECTION
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


# ---------------------------------------------------------------------------
# Palette and typography for the rendered diagrams
# ---------------------------------------------------------------------------

INK = (26, 32, 40)
MUTED = (104, 118, 134)
NAVY = (23, 42, 63)
NAVY_SOFT = (233, 239, 246)
GRID = (203, 214, 226)
BORDER = (52, 86, 120)
ROW_ALT = (248, 250, 252)
PK_CHIP = (23, 42, 63)
FK_CHIP = (150, 105, 20)
DECLARED = (38, 78, 128)
INFERRED = (168, 118, 24)
PAPER = (255, 255, 255)
ANCHOR = (92, 106, 122)

FONT_DIR = Path("C:/Windows/Fonts")
FONT_REGULAR = FONT_DIR / "segoeui.ttf"
FONT_BOLD = FONT_DIR / "segoeuib.ttf"
FONT_ITALIC = FONT_DIR / "segoeuii.ttf"

NAME_PX = 30
ROW_PX = 25
NOTE_PX = 22
TITLE_PX = 40
SUBTITLE_PX = 26

HEADER_H = 58
ROW_H = 42
BOX_PAD = 14
MIN_BOX_W = 360
MAX_BOX_W = 560
GAP_X = 250
GAP_Y = 54
MARGIN = 64
TITLE_BLOCK = 26


# ---------------------------------------------------------------------------
# Schema model
# ---------------------------------------------------------------------------


@dataclass
class Column:
    name: str
    type: str
    nullable: bool
    default: str | None
    auto_increment: bool
    comment: str | None


@dataclass
class ForeignKey:
    columns: list[str]
    parent: str
    parent_columns: list[str]
    on_delete: str | None
    constraint: str


@dataclass
class Table:
    name: str
    columns: list[Column] = field(default_factory=list)
    primary_key: list[str] = field(default_factory=list)
    uniques: list[list[str]] = field(default_factory=list)
    indexes: list[str] = field(default_factory=list)
    foreign_keys: list[ForeignKey] = field(default_factory=list)

    def column(self, name: str) -> Column | None:
        for col in self.columns:
            if col.name == name:
                return col
        return None


@dataclass(eq=False)
class Relation:
    child: str
    child_columns: list[str]
    parent: str
    parent_columns: list[str]
    declared: bool
    on_delete: str | None = None
    note: str = ""

    def one_to_one(self, schema: dict[str, Table]) -> bool:
        table = schema[self.child]
        child_set = set(self.child_columns)
        return any(set(unique) == child_set for unique in table.uniques)

    def required(self, schema: dict[str, Table]) -> bool:
        cols = [schema[self.child].column(name) for name in self.child_columns]
        return all(col is not None and not col.nullable for col in cols)


POLYMORPHIC = "__polymorphic__"

# Logical relations recovered from the column conventions and confirmed against app/Models and
# scripts/migrations_phase16.sql. Every *_id-like column of the schema must appear here; the script
# fails loudly when the dump grows a column that this map does not classify.
RELATION_MAP: dict[tuple[str, str], str] = {
    ("auth_otp_tokens", "usuario_id"): POLYMORPHIC,
    ("backups_log", "admin_id"): "usuarios",
    ("cartas_deuda_envios", "unidad_id"): "unidades",
    ("cartas_deuda_envios", "propietario_id"): "personas",
    ("cartas_deuda_envios", "usuario_id"): "usuarios",
    ("comprobantes_pago", "residente_id"): "personas",
    ("comprobantes_pago", "factura_id"): "facturas",
    ("comprobantes_pago", "cuenta_bancaria_id"): "cuentas_bancarias",
    ("comunicados", "admin_id"): "usuarios",
    ("comunicados", "edificio_id"): "edificios",
    ("comunicados", "unidad_id"): "unidades",
    ("comunicados_destinos", "comunicado_id"): "comunicados",
    ("comunicados_destinos", "unidad_id"): "unidades",
    ("conciliacion_abono_pago", "movimiento_id"): "extractos_bancarios",
    ("conciliacion_abono_pago", "pago_id"): "pagos",
    ("conciliacion_abono_pago", "conciliado_por"): "usuarios",
    ("deudas_manuales", "unidad_id"): "unidades",
    ("deudas_manuales", "admin_id"): "usuarios",
    ("estacionamientos", "unidad_id"): "unidades",
    ("estacionamientos", "edificio_id"): "edificios",
    ("extractos_bancarios", "pago_id"): "pagos",
    ("extractos_bancarios", "admin_id"): "usuarios",
    ("extractos_bancarios", "usuario_carga"): "usuarios",
    ("facturas", "unidad_id"): "unidades",
    ("gastos_comunes", "categoria_id"): "categorias_gastos",
    ("gastos_comunes", "edificio_id"): "edificios",
    ("gastos_comunes", "admin_id"): "usuarios",
    ("historial_estado_abono", "movimiento_id"): "extractos_bancarios",
    ("historial_estado_abono", "pago_id"): "pagos",
    ("historial_estado_abono", "usuario_id"): "usuarios",
    ("jwt_blacklist", "user_id"): POLYMORPHIC,
    ("log_auditoria", "pago_id"): "pagos",
    ("log_auditoria", "admin_id"): "usuarios",
    ("log_auditoria", "usuario_id"): "usuarios",
    ("log_auditoria", "registro_id"): POLYMORPHIC,
    ("movimientos_cuenta", "unidad_id"): "unidades",
    ("movimientos_cuenta", "referencia_id"): POLYMORPHIC,
    ("notificaciones", "residente_id"): "personas",
    ("notificaciones", "persona_id"): "personas",
    ("notificaciones", "comunicado_id"): "comunicados",
    ("pagos", "residente_id"): "personas",
    ("pagos", "unidad_id"): "unidades",
    ("pagos", "cuenta_bancaria_id"): "cuentas_bancarias",
    ("personas", "unidad_id"): "unidades",
    ("purgas_log", "admin_id"): "usuarios",
    ("refresh_tokens", "usuario_id"): POLYMORPHIC,
    ("reportes_cartelera", "usuario_id"): "usuarios",
    ("solicitudes_cambio_datos", "persona_id"): "personas",
    ("solicitudes_cambio_datos", "admin_id"): "usuarios",
    ("solicitudes_registro", "unidad_id"): "unidades",
    ("solicitudes_registro", "admin_id"): "usuarios",
    ("unidades", "edificio_id"): "edificios",
    ("unidades", "propietario_id"): "personas",
    ("vehiculos", "unidad_id"): "unidades",
    ("vehiculos", "persona_id"): "personas",
    ("vehiculos", "estacionamiento_id"): "estacionamientos",
}

POLYMORPHIC_NOTES = {
    ("auth_otp_tokens", "usuario_id"): (
        "Referencia polimórfica: usuario_tipo decide si apunta a usuarios o a personas."
    ),
    ("jwt_blacklist", "user_id"): (
        "Referencia polimórfica: el token puede pertenecer a un usuario administrativo o a una persona."
    ),
    ("log_auditoria", "registro_id"): (
        "Referencia polimórfica: apunta a la tabla indicada en tabla_afectada."
    ),
    ("movimientos_cuenta", "referencia_id"): (
        "Referencia polimórfica: apunta a la entidad indicada en referencia_tipo."
    ),
    ("refresh_tokens", "usuario_id"): (
        "Referencia polimórfica: la comparten UsuariosModel y PersonasModel."
    ),
}

# Columns that duplicate an equivalent reference already documented by another column.
LEGACY_COLUMN_NOTES = {
    ("notificaciones", "residente_id"): "Duplica semánticamente a persona_id; se conserva por compatibilidad.",
    ("notificaciones", "persona_id"): "Columna vigente; residente_id es el equivalente heredado.",
    ("extractos_bancarios", "usuario_carga"): "Equivalente a admin_id en cargas recientes.",
}


DOMAINS: list[dict] = [
    {
        "key": "nucleo",
        "title": "Núcleo residencial",
        "purpose": (
            "Soporte físico y humano del condominio: edificios, unidades, personas, cuentas de "
            "acceso, vehículos y estacionamientos."
        ),
        "tables": [
            "edificios",
            "unidades",
            "personas",
            "usuarios",
            "estacionamientos",
            "vehiculos",
        ],
    },
    {
        "key": "cobranzas",
        "title": "Cobranzas, gastos y cuentas bancarias",
        "purpose": (
            "Ciclo financiero del condominio: emisión de facturas, reporte y aprobación de pagos, "
            "deudas manuales, gastos comunes y cuentas de recaudación."
        ),
        "tables": [
            "facturas",
            "comprobantes_pago",
            "pagos",
            "deudas_manuales",
            "movimientos_cuenta",
            "cartas_deuda_envios",
            "categorias_gastos",
            "gastos_comunes",
            "cuentas_bancarias",
        ],
    },
    {
        "key": "conciliacion",
        "title": "Conciliación bancaria",
        "purpose": (
            "Cruce entre los movimientos del banco y los pagos reportados por los residentes, con "
            "traza del estado de cada abono."
        ),
        "tables": [
            "extractos_bancarios",
            "conciliacion_abono_pago",
            "historial_estado_abono",
        ],
    },
    {
        "key": "comunicacion",
        "title": "Comunicación y notificaciones",
        "purpose": (
            "Publicación de comunicados con alcance por edificio o unidad, entrega de "
            "notificaciones y registro de carteleras importadas."
        ),
        "tables": [
            "comunicados",
            "comunicados_destinos",
            "notificaciones",
            "notificaciones_cola",
            "reportes_cartelera",
        ],
    },
    {
        "key": "seguridad",
        "title": "Solicitudes, seguridad y auditoría",
        "purpose": (
            "Registro y cambio de datos de residentes, autenticación de dos factores, revocación de "
            "sesiones, límites de peticiones, bitácora y respaldos."
        ),
        "tables": [
            "solicitudes_registro",
            "solicitudes_cambio_datos",
            "auth_otp_tokens",
            "refresh_tokens",
            "jwt_blacklist",
            "rate_limits",
            "log_auditoria",
            "backups_log",
            "purgas_log",
        ],
    },
]


# ---------------------------------------------------------------------------
# Dump parsing
# ---------------------------------------------------------------------------

CREATE_RE = re.compile(r"CREATE TABLE `([^`]+)` \((.*?)\n\)\s*ENGINE", re.S)
COLUMN_RE = re.compile(r"^`([^`]+)`\s+([A-Za-z]+(?:\([^)]*\))?(?:\s+unsigned)?)(?=\s|$)\s*(.*)$")
FK_RE = re.compile(
    r"^CONSTRAINT `([^`]+)`\s+FOREIGN KEY\s+\(([^)]*)\)\s+REFERENCES `([^`]+)`\s+\(([^)]*)\)(.*)$"
)
DEFAULT_RE = re.compile(
    r"DEFAULT\s+('(?:[^']|'')*'|[A-Za-z_][\w.]*(?:\([^)]*\))?|-?\d+(?:\.\d+)?|NULL)", re.I
)
COMMENT_RE = re.compile(r"COMMENT\s+'([^']*)'")
ON_DELETE_RE = re.compile(r"ON DELETE\s+(CASCADE|SET NULL|RESTRICT|NO ACTION|SET DEFAULT)", re.I)


def _backticked(fragment: str) -> list[str]:
    return re.findall(r"`([^`]+)`", fragment)


INT_WIDTH_RE = re.compile(r"^(tinyint|smallint|mediumint|int|bigint)\((\d+)\)$")
ENUM_RE = re.compile(r"^(enum|set)\((.*)\)$", re.I)


def present_type(raw: str) -> tuple[str, str | None]:
    """Return the type shown in the catalog and an optional note with enum values."""
    match = ENUM_RE.match(raw)
    if match:
        values = [value.strip().strip("'") for value in match.group(2).split(",")]
        return (
            f"{match.group(1).lower()}({len(values)})",
            "valores: " + ", ".join(values),
        )
    match = INT_WIDTH_RE.match(raw)
    if match and not (match.group(1) == "tinyint" and match.group(2) == "1"):
        return match.group(1), None
    return raw, None


def parse_dump(text: str) -> dict[str, Table]:
    schema: dict[str, Table] = {}
    for name, body in CREATE_RE.findall(text):
        table = Table(name=name)
        for raw in body.splitlines():
            line = raw.strip().rstrip(",")
            if not line:
                continue
            if line.startswith("PRIMARY KEY"):
                table.primary_key = _backticked(line)
                continue
            if line.upper().startswith("UNIQUE KEY") or line.upper().startswith("UNIQUE INDEX"):
                table.uniques.append(_backticked(line))
                continue
            if line.upper().startswith("KEY ") or line.upper().startswith("INDEX "):
                table.indexes.append(line.split("`")[1] if "`" in line else line)
                continue
            if line.startswith("CONSTRAINT"):
                match = FK_RE.match(line)
                if match:
                    constraint, child_cols, parent, parent_cols, trailer = match.groups()
                    on_delete = ON_DELETE_RE.search(trailer)
                    table.foreign_keys.append(
                        ForeignKey(
                            columns=_backticked(child_cols),
                            parent=parent,
                            parent_columns=_backticked(parent_cols),
                            on_delete=on_delete.group(1).upper() if on_delete else None,
                            constraint=constraint,
                        )
                    )
                continue
            match = COLUMN_RE.match(line)
            if not match:
                continue
            col_name, col_type, rest = match.groups()
            default = DEFAULT_RE.search(rest)
            comment = COMMENT_RE.search(rest)
            # "NOT NULL" puede aparecer dentro de una expresión generada o de un
            # COMMENT; solo cuenta como restricción al inicio de la definición.
            head = re.sub(r"COMMENT\s+'([^']*)'", "", rest).split("GENERATED ALWAYS AS")[0]
            table.columns.append(
                Column(
                    name=col_name,
                    type=col_type,
                    nullable="NOT NULL" not in head.upper(),
                    default=default.group(1) if default else None,
                    auto_increment="AUTO_INCREMENT" in rest.upper(),
                    comment=comment.group(1) if comment else None,
                )
            )
        schema[name] = table
    return schema


def build_relations(schema: dict[str, Table]) -> tuple[list[Relation], list[tuple[str, str, str]]]:
    declared: dict[tuple[str, str], Relation] = {}
    relations: list[Relation] = []
    for table in schema.values():
        for fk in table.foreign_keys:
            relation = Relation(
                child=table.name,
                child_columns=fk.columns,
                parent=fk.parent,
                parent_columns=fk.parent_columns,
                declared=True,
                on_delete=fk.on_delete,
            )
            relations.append(relation)
            declared[(table.name, fk.columns[0])] = relation

    polymorphic: list[tuple[str, str, str]] = []
    covered: set[tuple[str, str]] = set()
    for table in schema.values():
        for column in table.columns:
            key = (table.name, column.name)
            looks_like_reference = column.name.endswith("_id") or column.name in {
                "conciliado_por",
                "usuario_carga",
            }
            if not looks_like_reference:
                continue
            if key in RELATION_MAP:
                covered.add(key)
                target = RELATION_MAP[key]
                if target == POLYMORPHIC:
                    polymorphic.append(
                        (table.name, column.name, POLYMORPHIC_NOTES.get(key, "Referencia polimórfica."))
                    )
                    continue
                if key in declared:
                    continue
                relations.append(
                    Relation(
                        child=table.name,
                        child_columns=[column.name],
                        parent=target,
                        parent_columns=[schema[target].primary_key[0]],
                        declared=False,
                    )
                )
            else:
                print(
                    f"WARNING uncovered reference column: {table.name}.{column.name}",
                    file=sys.stderr,
                )

    for key, target in RELATION_MAP.items():
        if target != POLYMORPHIC and key not in covered:
            print(f"WARNING map entry without column: {key[0]}.{key[1]}", file=sys.stderr)

    relations.sort(key=lambda r: (r.child, r.child_columns[0]))
    polymorphic.sort()
    return relations, polymorphic


# ---------------------------------------------------------------------------
# Vector-ish painter on top of Pillow with supersampling
# ---------------------------------------------------------------------------


class Painter:
    def __init__(self, width: int, height: int, ss: int = 2):
        self.ss = ss
        self.width = width
        self.height = height
        self.image = Image.new("RGB", (width * ss, height * ss), PAPER)
        self.draw = ImageDraw.Draw(self.image)

    def _s(self, value: float) -> int:
        return int(round(value * self.ss))

    def rect(self, x, y, w, h, fill=None, outline=None, width=1.4, radius=0):
        box = [self._s(x), self._s(y), self._s(x + w), self._s(y + h)]
        stroke = max(1, self._s(width))
        if radius:
            self.draw.rounded_rectangle(box, radius=self._s(radius), fill=fill, outline=outline, width=stroke)
        else:
            self.draw.rectangle(box, fill=fill, outline=outline, width=stroke)

    def line(self, points, fill, width=2.6):
        self.draw.line(
            [(self._s(x), self._s(y)) for x, y in points],
            fill=fill,
            width=max(1, self._s(width)),
            joint="curve",
        )

    def dashed(self, points, fill, width=2.4, dash=11, gap=8):
        for start, end in zip(points, points[1:]):
            x1, y1 = start
            x2, y2 = end
            length = math.hypot(x2 - x1, y2 - y1)
            if length == 0:
                continue
            ux, uy = (x2 - x1) / length, (y2 - y1) / length
            travelled = 0.0
            while travelled < length:
                seg = min(dash, length - travelled)
                self.line(
                    [
                        (x1 + ux * travelled, y1 + uy * travelled),
                        (x1 + ux * (travelled + seg), y1 + uy * (travelled + seg)),
                    ],
                    fill,
                    width,
                )
                travelled += dash + gap

    def text(self, x, y, value, font, fill, anchor="la"):
        self.draw.text((self._s(x), self._s(y)), value, font=font, fill=fill, anchor=anchor)

    def text_width(self, value, font) -> float:
        return self.draw.textlength(value, font=font) / self.ss

    def text_block(self, x, y, value, font, fill, anchor="la"):
        self.draw.text((self._s(x), self._s(y)), value, font=font, fill=fill, anchor=anchor)

    def save(self, path: Path):
        self.image.resize((self.width, self.height), Image.LANCZOS).save(path, optimize=True)


def load_font(path: Path, size: int, ss: int = 2):
    try:
        return ImageFont.truetype(str(path), size * ss)
    except OSError:
        return ImageFont.load_default()


def split_words(value: str, font, painter: Painter, max_width: float) -> list[str]:
    words = value.split()
    lines: list[str] = []
    current = ""
    for word in words:
        candidate = f"{current} {word}".strip()
        if painter.text_width(candidate, font) <= max_width or not current:
            current = candidate
        else:
            lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines


# ---------------------------------------------------------------------------
# Layout
# ---------------------------------------------------------------------------


@dataclass
class Node:
    name: str
    level: int
    order: int
    rows: list[tuple[str, str, str]]  # tag, column, note
    hidden: int
    x: float = 0.0
    y: float = 0.0
    w: float = 0.0
    h: float = 0.0

    def row_y(self, index: int) -> float:
        return self.y + HEADER_H + index * ROW_H + ROW_H / 2


def displayed_rows(
    table: Table, child_columns: set[str], parent_columns: set[str]
) -> tuple[list[tuple[str, str, str]], int]:
    rows: list[tuple[str, str, str]] = []
    used: set[str] = set()
    for name in table.primary_key:
        rows.append(("PK", name, ""))
        used.add(name)
    for column in table.columns:
        if column.name in used:
            continue
        if column.name in child_columns:
            rows.append(("FK", column.name, ""))
            used.add(column.name)
    hidden = len([c for c in table.columns if c.name not in used])
    return rows, hidden


def compute_levels(
    names: list[str], edges: list[tuple[str, str]], longest: bool = True
) -> tuple[dict[str, int], set]:
    adjacency: dict[str, list[str]] = {name: [] for name in names}
    for child, parent in edges:
        adjacency.setdefault(child, []).append(parent)

    state: dict[str, int] = {}
    back_edges: set[tuple[str, str]] = set()

    def visit(node: str):
        state[node] = 1
        for neighbour in adjacency.get(node, []):
            if state.get(neighbour) == 1:
                back_edges.add((node, neighbour))
            elif neighbour not in state:
                visit(neighbour)
        state[node] = 2

    for name in names:
        if name not in state:
            visit(name)

    forward = [(child, parent) for child, parent in edges if (child, parent) not in back_edges]
    level = {name: 0 for name in names}
    for _ in range(len(names) + 2):
        changed = False
        for child, parent in forward:
            target = level[parent] + 1
            if longest and level[child] < target:
                level[child] = target
                changed = True
            elif not longest and level[child] > target:
                level[child] = target
                changed = True
        if not changed:
            break
    return level, back_edges


def choose_levels(names: list[str], edges: list[tuple[str, str]]) -> tuple[dict[str, int], int]:
    """Prefer the levelling that keeps more relations on adjacent columns.

    Longest-path levelling spreads a table as far right as its deepest parent chain, which turns
    short relations into long corridor detours. Shortest-path levelling keeps relations adjacent but
    can place a child beside one of its parents. The penalty counts both cases, so the layout with
    the fewer awkward edges wins.
    """
    best = None
    for longest in (True, False):
        level, _ = compute_levels(names, edges, longest=longest)
        spans = [abs(level[parent] - level[child]) for child, parent in edges]
        penalty = sum(1 for span in spans if span != 1) + 3 * sum(1 for span in spans if span == 0)
        if best is None or penalty < best[1]:
            best = (level, penalty)
    return best


def barycenter_order(
    names: list[str],
    level: dict[str, int],
    adjacency: dict[str, list[str]],
    levels_count: int,
) -> dict[str, int]:
    columns: dict[int, list[str]] = {}
    for name in names:
        columns.setdefault(level[name], []).append(name)
    for bucket in columns.values():
        bucket.sort()

    order = {name: index for index, name in enumerate(names)}

    for _ in range(3):
        for lv in range(1, levels_count):
            bucket = columns.get(lv, [])
            bucket.sort(
                key=lambda n: (
                    sum(order[p] for p in adjacency.get(n, []) if level.get(p) == lv - 1)
                    / max(1, len([p for p in adjacency.get(n, []) if level.get(p) == lv - 1])),
                    n,
                )
            )
            for index, name in enumerate(bucket):
                order[name] = index
        for lv in range(levels_count - 2, -1, -1):
            bucket = columns.get(lv, [])
            children: dict[str, list[str]] = {n: [] for n in bucket}
            for name in bucket:
                children[name] = [
                    other for other, parents_list in adjacency.items() if name in parents_list
                ]
            bucket.sort(
                key=lambda n: (
                    sum(order[c] for c in children[n]) / max(1, len(children[n])),
                    n,
                )
            )
            for index, name in enumerate(bucket):
                order[name] = index
    final: dict[str, int] = {}
    for lv, bucket in columns.items():
        for index, name in enumerate(bucket):
            final[name] = index
    return final


def render_domain(
    spec: dict,
    schema: dict[str, Table],
    relations: list[Relation],
    path: Path,
) -> dict:
    own = list(spec["tables"])
    own_set = set(own)

    edges = [r for r in relations if r.child in own_set]
    reciprocal = {
        frozenset({r.child, r.parent})
        for r in edges
        if any(o.child == r.parent and o.parent == r.child for o in edges)
    }

    referenced = {r.parent for r in edges if r.parent not in own_set}
    names = own + sorted(referenced)

    child_columns: dict[str, set[str]] = {name: set() for name in names}
    parent_columns: dict[str, set[str]] = {name: set() for name in names}
    for relation in edges:
        child_columns[relation.child].update(relation.child_columns)
        parent_columns[relation.parent].update(relation.parent_columns)

    level, layout_penalty = choose_levels(names, [(r.child, r.parent) for r in edges])
    # Referenced tables have no outgoing keys of their own, so their level is a free choice. Placing
    # them one column to the left of their shallowest child keeps the connector short instead of
    # forcing every reference into a corridor.
    for anchor in referenced:
        children_levels = [level[r.child] for r in edges if r.parent == anchor]
        if children_levels:
            level[anchor] = max(0, min(children_levels) - 1)
    levels_count = max(level.values()) + 1

    adjacency: dict[str, list[str]] = {name: [] for name in names}
    for relation in edges:
        adjacency[relation.child].append(relation.parent)
    order = barycenter_order(names, level, adjacency, levels_count)

    rows_by_table: dict[str, list[tuple[str, str, str]]] = {}
    hidden_by_table: dict[str, int] = {}
    for name in names:
        rows, hidden = displayed_rows(
            schema[name],
            child_columns[name] if name in own_set else set(),
            parent_columns[name],
        )
        rows_by_table[name] = rows
        hidden_by_table[name] = hidden

    fonts = {
        "title": load_font(FONT_BOLD, TITLE_PX),
        "subtitle": load_font(FONT_REGULAR, SUBTITLE_PX),
        "name": load_font(FONT_BOLD, NAME_PX),
        "row": load_font(FONT_REGULAR, ROW_PX),
        "note": load_font(FONT_ITALIC, NOTE_PX),
        "tiny": load_font(FONT_BOLD, 20),
    }

    measure = Painter(10, 10)
    box_w = MIN_BOX_W
    for name in names:
        width = measure.text_width(name, fonts["name"]) + BOX_PAD * 2 + 26
        for tag, column, _ in rows_by_table[name]:
            width = max(width, measure.text_width(f"{tag}   {column}", fonts["row"]) + BOX_PAD * 2 + 18)
        box_w = max(box_w, min(MAX_BOX_W, width))
    box_w = float(int(box_w))

    nodes: dict[str, Node] = {}
    for name in names:
        rows = rows_by_table[name]
        height = HEADER_H + len(rows) * ROW_H
        if name in own_set and hidden_by_table[name]:
            height += 34
        nodes[name] = Node(
            name=name,
            level=level[name],
            order=order[name],
            rows=rows,
            hidden=hidden_by_table[name],
            w=box_w,
            h=height,
        )

    columns: dict[int, list[Node]] = {}
    for node in nodes.values():
        columns.setdefault(node.level, []).append(node)
    for bucket in columns.values():
        bucket.sort(key=lambda n: n.order)

    column_heights = {
        lv: sum(n.h for n in bucket) + GAP_Y * (len(bucket) - 1) for lv, bucket in columns.items()
    }
    tallest = max(column_heights.values())

    def row_index_of(node: Node, column_name: str) -> int:
        for index, (_, column, _) in enumerate(node.rows):
            if column == column_name:
                return index
        return 0

    # Adjacent-column edges use the free band between the two columns. Everything else goes through
    # a single bottom corridor, which keeps the header area and the tables box-free.
    band_edges: dict[int, list[Relation]] = {}
    corridor_edges: list[Relation] = []
    for relation in edges:
        span = level[relation.parent] - level[relation.child]
        if abs(span) == 1:
            band_edges.setdefault(min(level[relation.child], level[relation.parent]), []).append(relation)
        else:
            corridor_edges.append(relation)

    lane_x: dict[Relation, float] = {}
    for band, bucket in band_edges.items():
        band_start = MARGIN + band * (box_w + GAP_X) + box_w
        bucket.sort(key=lambda r: (nodes[r.child].y + row_index_of(nodes[r.child], r.child_columns[0]) * ROW_H, r.parent))
        for index, relation in enumerate(bucket):
            lane_x[relation] = band_start + GAP_X * (index + 1) / (len(bucket) + 1)

    corridor_h = 26 + 18 * len(corridor_edges)
    canvas_w = int(MARGIN * 2 + levels_count * box_w + (levels_count - 1) * GAP_X)
    canvas_h = int(MARGIN * 2 + TITLE_BLOCK + tallest + corridor_h)

    for lv, bucket in columns.items():
        x = MARGIN + lv * (box_w + GAP_X)
        top = MARGIN + TITLE_BLOCK + (tallest - column_heights[lv]) / 2
        cursor = top
        for node in bucket:
            node.x = x
            node.y = cursor
            cursor += node.h + GAP_Y

    painter = Painter(canvas_w, canvas_h)

    def channel_right(level: int) -> float:
        edge = MARGIN + level * (box_w + GAP_X) + box_w
        return edge + (GAP_X * 0.5 if level < levels_count - 1 else MARGIN * 0.5)

    def channel_left(level: int) -> float:
        if level == 0:
            return MARGIN * 0.5
        return MARGIN + level * (box_w + GAP_X) - GAP_X * 0.5

    for relation in edges:
        child = nodes[relation.child]
        parent = nodes[relation.parent]
        y1 = child.row_y(row_index_of(child, relation.child_columns[0]))
        y2 = parent.row_y(row_index_of(parent, relation.parent_columns[0]))
        color = DECLARED if relation.declared else INFERRED
        span = level[relation.parent] - level[relation.child]

        if abs(span) == 1:
            mid = lane_x[relation]
            if span == 1:  # parent to the right
                points = [(child.x + child.w, y1), (mid, y1), (mid, y2), (parent.x, y2)]
                draw_many(painter, child.x + child.w, y1, 1, 0, color)
                draw_one(painter, parent.x, y2, -1, 0, color)
            else:  # parent to the left
                points = [(child.x, y1), (mid, y1), (mid, y2), (parent.x + parent.w, y2)]
                draw_many(painter, child.x, y1, -1, 0, color)
                draw_one(painter, parent.x + parent.w, y2, 1, 0, color)
            if relation.declared:
                painter.line(points, color)
            else:
                painter.dashed(points, color)
        else:
            index = corridor_edges.index(relation)
            y_lane = MARGIN + TITLE_BLOCK + tallest + 20 + index * 18
            if parent.level < child.level:
                exit_x, exit_dir = child.x, -1
                entry_x, entry_dir = parent.x + parent.w, 1
                child_channel = channel_left(child.level) - index * 6
                parent_channel = channel_right(parent.level) + index * 6
            else:
                exit_x, exit_dir = child.x + child.w, 1
                entry_x, entry_dir = parent.x, -1
                child_channel = channel_right(child.level) + index * 6
                parent_channel = channel_left(parent.level) - index * 6
            points = [
                (exit_x, y1),
                (child_channel, y1),
                (child_channel, y_lane),
                (parent_channel, y_lane),
                (parent_channel, y2),
                (entry_x, y2),
            ]
            if relation.declared:
                painter.line(points, color)
            else:
                painter.dashed(points, color)
            draw_many(painter, exit_x, y1, exit_dir, 0, color)
            draw_one(painter, entry_x, y2, entry_dir, 0, color)

    for node in nodes.values():
        is_own = node.name in own_set
        header_fill = NAVY if is_own else ANCHOR
        painter.rect(node.x, node.y, node.w, node.h, fill=PAPER, outline=BORDER, width=1.6, radius=8)
        painter.rect(node.x, node.y, node.w, HEADER_H, fill=header_fill, outline=header_fill, width=1.6, radius=8)
        painter.rect(node.x, node.y + HEADER_H - 10, node.w, 10, fill=header_fill, outline=header_fill, width=0)
        painter.text(
            node.x + 16,
            node.y + HEADER_H / 2,
            node.name,
            fonts["name"],
            (255, 255, 255),
            anchor="lm",
        )
        for index, (tag, column, _note) in enumerate(node.rows):
            center = node.row_y(index)
            if index % 2 == 1:
                painter.rect(node.x + 1.6, center - ROW_H / 2, node.w - 3.2, ROW_H, fill=ROW_ALT)
            chip_color = PK_CHIP if tag == "PK" else FK_CHIP
            chip_w = 34
            painter.rect(
                node.x + 12,
                center - 11,
                chip_w,
                22,
                fill=(255, 255, 255) if tag == "FK" else chip_color,
                outline=chip_color,
                width=1.2,
                radius=5,
            )
            painter.text(
                node.x + 12 + chip_w / 2,
                center,
                tag,
                fonts["tiny"],
                (255, 255, 255) if tag == "PK" else chip_color,
                anchor="mm",
            )
            painter.text(node.x + 12 + chip_w + 12, center, column, fonts["row"], INK, anchor="lm")
        if is_own and node.hidden:
            painter.text(
                node.x + 16,
                node.y + node.h - 17,
                f"+{node.hidden} columnas en el catálogo",
                fonts["note"],
                MUTED,
                anchor="lm",
            )
        if not is_own:
            painter.text(
                node.x + node.w - 16,
                node.y + HEADER_H / 2,
                "referencia",
                fonts["note"],
                (232, 238, 245),
                anchor="rm",
            )

    painter.save(path)

    return {
        "path": path,
        "canvas": (canvas_w, canvas_h),
        "own": len(own),
        "referenced": len(referenced),
        "relations": len(edges),
        "declared": sum(1 for r in edges if r.declared),
        "inferred": sum(1 for r in edges if not r.declared),
    }


def draw_many(painter: Painter, x: float, y: float, nx: float, ny: float, color):
    tx, ty = -ny, nx
    apex = (x + nx * 16, y + ny * 16)
    painter.line([apex, (x + tx * 10, y + ty * 10)], color, 2.6)
    painter.line([apex, (x, y)], color, 2.6)
    painter.line([apex, (x - tx * 10, y - ty * 10)], color, 2.6)


def draw_one(painter: Painter, x: float, y: float, nx: float, ny: float, color):
    tx, ty = -ny, nx
    cx, cy = x + nx * 13, y + ny * 13
    painter.line([(cx + tx * 10, cy + ty * 10), (cx - tx * 10, cy - ty * 10)], color, 2.6)


def render_legend(path: Path) -> Path:
    width, height = 1180, 400
    painter = Painter(width, height)
    title = load_font(FONT_BOLD, 26)
    body = load_font(FONT_REGULAR, 22)
    painter.text(0, 0, "Notacion", title, NAVY)

    y = 60
    painter.line([(10, y), (150, y)], DECLARED, 2.8)
    draw_many(painter, 10, y, -1, 0, DECLARED)
    draw_one(painter, 150, y, 1, 0, DECLARED)
    painter.text(180, y, "Relación declarada (FOREIGN KEY en el motor)", body, INK, anchor="lm")

    y = 110
    painter.dashed([(10, y), (150, y)], INFERRED, 2.6)
    draw_many(painter, 10, y, -1, 0, INFERRED)
    draw_one(painter, 150, y, 1, 0, INFERRED)
    painter.text(180, y, "Relación inferida (convención de columnas, sin restricción física)", body, INK, anchor="lm")

    y = 160
    painter.rect(10, y - 11, 34, 22, fill=PK_CHIP, outline=PK_CHIP, radius=5)
    painter.text(27, y, "PK", load_font(FONT_BOLD, 20), (255, 255, 255), anchor="mm")
    painter.text(58, y, "Clave primaria de la tabla", body, INK, anchor="lm")

    y = 210
    painter.rect(10, y - 11, 34, 22, fill=(255, 255, 255), outline=FK_CHIP, width=1.2, radius=5)
    painter.text(27, y, "FK", load_font(FONT_BOLD, 20), FK_CHIP, anchor="mm")
    painter.text(58, y, "Clave foránea (columna que referencia a otra tabla)", body, INK, anchor="lm")

    y = 262
    painter.rect(10, y - 15, 150, 30, fill=ANCHOR, outline=ANCHOR, radius=6)
    painter.text(85, y, "unidades", load_font(FONT_BOLD, 20), (255, 255, 255), anchor="mm")
    painter.text(180, y, "Tabla de otro dominio, incluida como referencia para no perder la relación", body, INK, anchor="lm")

    y = 330
    painter.text(10, y, "Solo se dibujan claves. El resto de columnas esta en el catálogo de tablas.", body, MUTED, anchor="lm")
    painter.save(path)
    return path


# ---------------------------------------------------------------------------
# DOCX helpers
# ---------------------------------------------------------------------------


def shade(cell, color: str):
    properties = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color)
    properties.append(shd)


def cell_margins(cell, top=40, bottom=40, left=45, right=45):
    properties = cell._tc.get_or_add_tcPr()
    mar = OxmlElement("w:tcMar")
    for name, value in (("top", top), ("bottom", bottom), ("start", left), ("end", right)):
        node = OxmlElement(f"w:{name}")
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")
        mar.append(node)
    properties.append(mar)


def repeat_header(row):
    properties = row._tr.get_or_add_trPr()
    header = OxmlElement("w:tblHeader")
    header.set(qn("w:val"), "true")
    properties.append(header)


def cant_split(row):
    properties = row._tr.get_or_add_trPr()
    node = OxmlElement("w:cantSplit")
    node.set(qn("w:val"), "true")
    properties.append(node)


def set_widths(table, widths):
    table.autofit = False
    usable = 6.55
    total = sum(widths)
    if total > usable:
        widths = [width * usable / total for width in widths]
    for row in table.rows:
        for cell, width in zip(row.cells, widths):
            cell.width = Inches(width)
    for index, width in enumerate(widths):
        table.columns[index].width = Inches(width)


def add_page_field(paragraph):
    response = OxmlElement("w:fldSimple")
    response.set(qn("w:instr"), "PAGE")
    run = OxmlElement("w:r")
    text = OxmlElement("w:t")
    text.text = "1"
    run.append(text)
    response.append(run)
    paragraph._p.append(response)


def configure_footer(section, label: str):
    footer = section.footer
    footer.is_linked_to_previous = False
    paragraph = footer.paragraphs[0]
    paragraph.text = ""
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = paragraph.add_run(f"{label}  ·  ")
    run.font.size = Pt(7.5)
    run.font.color.rgb = RGBColor(0x6B, 0x7A, 0x8C)
    add_page_field(paragraph)


def configure_header(section, label: str):
    header = section.header
    header.is_linked_to_previous = False
    paragraph = header.paragraphs[0]
    paragraph.text = ""
    paragraph.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = paragraph.add_run(label)
    run.font.size = Pt(7.5)
    run.font.color.rgb = RGBColor(0x8A, 0x98, 0xA8)


def add_heading(doc, text, level, color=(0x17, 0x2A, 0x3F), size=None):
    heading = doc.add_heading(text, level=level)
    for run in heading.runs:
        run.font.name = "Segoe UI"
        run.font.color.rgb = RGBColor(*color)
        if size:
            run.font.size = Pt(size)
    heading.paragraph_format.space_before = Pt(14 if level == 1 else 10)
    heading.paragraph_format.space_after = Pt(6)
    return heading


def add_body(doc, text, size=9.5, color=(0x1A, 0x20, 0x28), italic=False, space_after=6):
    paragraph = doc.add_paragraph()
    run = paragraph.add_run(text)
    run.font.size = Pt(size)
    run.font.name = "Segoe UI"
    run.italic = italic
    run.font.color.rgb = RGBColor(*color)
    paragraph.paragraph_format.space_after = Pt(space_after)
    return paragraph


def add_caption(doc, prefix, text):
    paragraph = doc.add_paragraph()
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = paragraph.add_run(f"{prefix}  ")
    run.bold = True
    run.font.size = Pt(7.5)
    run.font.color.rgb = RGBColor(0x17, 0x2A, 0x3F)
    run2 = paragraph.add_run(text)
    run2.font.size = Pt(7.5)
    run2.font.color.rgb = RGBColor(0x6B, 0x7A, 0x8C)
    paragraph.paragraph_format.space_after = Pt(10)
    return paragraph


def add_table(doc, headers, rows, widths, font_size=7.5, header_fill="172A3F", zebra=True):
    table = doc.add_table(rows=1, cols=len(headers), style="Table Grid")
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    header = table.rows[0]
    repeat_header(header)
    for index, text in enumerate(headers):
        cell = header.cells[index]
        cell.text = ""
        paragraph = cell.paragraphs[0]
        paragraph.paragraph_format.space_after = Pt(0)
        paragraph.paragraph_format.keep_with_next = True
        run = paragraph.add_run(text)
        run.bold = True
        run.font.size = Pt(font_size)
        run.font.name = "Segoe UI"
        run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        shade(cell, header_fill)
        cell_margins(cell)
    for index_row, row in enumerate(rows):
        cells = table.add_row().cells
        cant_split(table.rows[-1])
        for index_cell, value in enumerate(row):
            cell = cells[index_cell]
            cell.text = ""
            paragraph = cell.paragraphs[0]
            paragraph.paragraph_format.space_after = Pt(0)
            run = paragraph.add_run(str(value))
            run.font.size = Pt(font_size)
            run.font.name = "Segoe UI"
            run.font.color.rgb = RGBColor(0x1A, 0x20, 0x28)
            if zebra and index_row % 2 == 1:
                shade(cell, "F4F7FA")
            cell_margins(cell)
    set_widths(table, widths)
    return table


def add_image(doc, path: Path, max_width_in: float, max_height_in: float) -> tuple[float, float]:
    with Image.open(path) as image:
        px_w, px_h = image.size
    native_w = px_w / 220.0
    native_h = px_h / 220.0
    width = min(max_width_in, native_w)
    height = width * px_h / px_w
    if height > max_height_in:
        height = max_height_in
        width = height * px_w / px_h
    paragraph = doc.add_paragraph()
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    paragraph.paragraph_format.space_before = Pt(10)
    paragraph.paragraph_format.space_after = Pt(4)
    paragraph.add_run().add_picture(str(path), width=Inches(width))
    return width, height


# ---------------------------------------------------------------------------
# Document assembly
# ---------------------------------------------------------------------------


def build_document(
    schema: dict[str, Table],
    relations: list[Relation],
    polymorphic: list[tuple[str, str, str]],
    images: dict,
    out_path: Path,
    dump_path: Path,
):
    declared_count = sum(1 for r in relations if r.declared)
    inferred_count = len(relations) - declared_count

    document = Document()
    styles = document.styles
    normal = styles["Normal"]
    normal.font.name = "Segoe UI"
    normal.font.size = Pt(9.5)

    section = document.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.left_margin = Inches(0.8)
    section.right_margin = Inches(0.8)
    section.top_margin = Inches(0.7)
    section.bottom_margin = Inches(0.7)
    configure_header(section, "Modelo entidad-relación  ·  condominio_cobranzas")
    configure_footer(section, "ProyectoT3")

    document.core_properties.title = "Modelo entidad-relación: condominio_cobranzas"
    document.core_properties.subject = "Diagrama ER y catálogo de la base de datos"
    document.core_properties.keywords = "base de datos, modelo entidad-relación, MariaDB, ProyectoT3"

    # ---- cover ----
    spacer = document.add_paragraph()
    spacer.paragraph_format.space_after = Pt(90)

    kicker = document.add_paragraph()
    run = kicker.add_run("DOCUMENTACIÓN TÉCNICA  ·  BASE DE DATOS")
    run.font.size = Pt(8.5)
    run.bold = True
    run.font.color.rgb = RGBColor(0x6B, 0x7A, 0x8C)
    kicker.paragraph_format.space_after = Pt(10)

    title = document.add_paragraph()
    run = title.add_run("Modelo entidad-relación")
    run.font.size = Pt(30)
    run.bold = True
    run.font.name = "Segoe UI"
    run.font.color.rgb = RGBColor(0x17, 0x2A, 0x3F)
    title.paragraph_format.space_after = Pt(2)

    subtitle = document.add_paragraph()
    run = subtitle.add_run("Sistema de cobranzas y gestión de pagos de condominio")
    run.font.size = Pt(13)
    run.font.color.rgb = RGBColor(0x4A, 0x5C, 0x70)
    subtitle.paragraph_format.space_after = Pt(24)

    rule = document.add_paragraph()
    run = rule.add_run("_" * 96)
    run.font.size = Pt(5)
    run.font.color.rgb = RGBColor(0xC7, 0xD2, 0xDE)
    rule.paragraph_format.space_after = Pt(18)

    meta_rows = [
        ("Base de datos", "condominio_cobranzas"),
        ("Motor", "MariaDB 10.4.32 (InnoDB, utf8mb4)"),
        ("Tablas documentadas", str(len(schema))),
        ("Relaciones", f"{len(relations)}  ({declared_count} declaradas, {inferred_count} inferidas)"),
        ("Fuente del esquema", dump_path.name),
        ("Generado", dt.date.today().strftime("%d/%m/%Y")),
    ]
    table = document.add_table(rows=0, cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    for label, value in meta_rows:
        cells = table.add_row().cells
        paragraph = cells[0].paragraphs[0]
        run = paragraph.add_run(label)
        run.font.size = Pt(9)
        run.bold = True
        run.font.color.rgb = RGBColor(0x4A, 0x5C, 0x70)
        paragraph2 = cells[1].paragraphs[0]
        run2 = paragraph2.add_run(value)
        run2.font.size = Pt(9)
        run2.font.color.rgb = RGBColor(0x1A, 0x20, 0x28)
    set_widths(table, [1.5, 4.6])

    document.add_paragraph()

    # ---- how to read ----
    document.add_page_break()
    add_heading(document, "1. Cómo leer este documento", 1)
    add_body(
        document,
        "El modelo se presenta en dos niveles. Los diagramas de la sección 3 muestran la estructura "
        "de cada dominio con sus claves y las relaciones que lo atraviesan. El catálogo de la sección 5 "
        "documenta todas las columnas de cada tabla, incluidos los tipos y los valores por defecto.",
    )
    add_body(
        document,
        "Las relaciones se clasifican en declaradas e inferidas. Una relación declarada existe como "
        "restricción FOREIGN KEY en el motor y la base de datos la hace cumplir. Una relación inferida "
        "solo existe en el código de la aplicación: la columna se llama igual que la clave de la tabla "
        "destino y los modelos la usan en sus consultas, pero nada impide insertar un valor invalido. "
        "La distinción importa porque las inferidas son justamente las que pueden quedar inconsistentes.",
    )
    add_image(document, images["legend"], 6.3, 2.4)
    add_caption(document, "Figura 1.", "Leyenda de notación utilizada en los diagramas de dominio.")

    add_heading(document, "2. Dominios del modelo", 2)
    add_body(
        document,
        "Las 32 tablas se agruparon en cinco dominios para que cada diagrama quepa en una página y se "
        "pueda leer sin ampliar. La agrupación sigue el ciclo de negocio, no el orden alfabético.",
    )
    domain_rows = []
    for spec in DOMAINS:
        internal = [
            r
            for r in relations
            if r.child in spec["tables"] and r.parent in spec["tables"]
        ]
        domain_rows.append(
            (
                spec["title"],
                len(spec["tables"]),
                len(internal),
                spec["purpose"],
            )
        )
    add_table(
        document,
        ["Dominio", "Tablas", "Relaciones internas", "Propósito"],
        domain_rows,
        [1.35, 0.55, 0.8, 3.9],
        font_size=7.5,
    )

    ownership = {}
    for spec in DOMAINS:
        for table_name in spec["tables"]:
            ownership[table_name] = spec["key"]

    cross: dict[tuple[str, str], int] = {}
    for relation in relations:
        source = ownership[relation.child]
        target = ownership[relation.parent]
        if source != target:
            cross[(source, target)] = cross.get((source, target), 0) + 1

    short = {
        "nucleo": "Núcleo",
        "cobranzas": "Cobranzas",
        "conciliacion": "Conciliación",
        "comunicacion": "Comunicación",
        "seguridad": "Seguridad",
    }
    keys = [spec["key"] for spec in DOMAINS]
    matrix_rows = []
    for source in keys:
        row = [short[source]]
        for target in keys:
            if source == target:
                row.append("—")
            else:
                row.append(str(cross.get((source, target), 0)) or "·")
        row.append(str(sum(1 for relation in relations
                           if ownership[relation.child] == source
                           and ownership[relation.parent] != source)))
        matrix_rows.append(tuple(row))
    matrix_rows.append(tuple(
        ["Total recibido"]
        + [str(sum(1 for relation in relations
                   if ownership[relation.child] != target
                   and ownership[relation.parent] == target)) for target in keys]
        + [""]
    ))
    add_body(
        document,
        "La matriz siguiente cuenta las claves foráneas que cada dominio dirige hacia otro. La columna "
        "Total salida agrupa las referencias que el dominio exporta; la fila Total recibido, las que recibe.",
        size=8.5,
        space_after=4,
    )
    add_table(
        document,
        ["Desde \\ Hacia"] + [short[key] for key in keys] + ["Total salida"],
        matrix_rows,
        [1.15, 0.95, 1.0, 0.95, 1.05, 0.9, 0.85],
        font_size=7.2,
    )
    document.add_paragraph()

    # ---- domain diagrams ----
    figure = 2
    for index, spec in enumerate(DOMAINS):
        info = images[spec["key"]]
        canvas_w, canvas_h = info["canvas"]
        landscape = canvas_w / canvas_h > 1.15

        section = document.add_section(WD_SECTION.NEW_PAGE)
        if landscape:
            section.orientation = WD_ORIENT.LANDSCAPE
            section.page_width = Inches(11)
            section.page_height = Inches(8.5)
            section.left_margin = Inches(0.6)
            section.right_margin = Inches(0.6)
            section.top_margin = Inches(0.5)
            section.bottom_margin = Inches(0.5)
            max_width, max_height = 9.8, 6.3
        else:
            section.orientation = WD_ORIENT.PORTRAIT
            section.page_width = Inches(8.5)
            section.page_height = Inches(11)
            section.left_margin = Inches(0.8)
            section.right_margin = Inches(0.8)
            section.top_margin = Inches(0.7)
            section.bottom_margin = Inches(0.6)
            max_width, max_height = 6.9, 8.2

        if index == 0:
            add_heading(document, "3. Diagramas por dominio", 1, size=16)
            add_body(
                document,
                "Cada diagrama muestra las claves de sus tablas y todas las relaciones en las que "
                "participa al menos una tabla del dominio. Para no perder esas relaciones, las tablas "
                "destino de otro dominio aparecen en gris como nodos referenciados: se dibujan con todas "
                "sus claves una sola vez, en el diagrama de su propio dominio. La sección 4 lista cada "
                "relación con su ámbito.",
                size=8.5,
            )
            add_heading(document, "3.1  " + spec["title"], 2, size=12.5)
        else:
            add_heading(document, f"3.{index + 1}  {spec['title']}", 2, size=12.5)
        add_body(
            document,
            f"{spec['purpose']} El dominio reúne {info['own']} tablas propias, "
            f"{info['referenced']} referenciadas y {info['relations']} relaciones dibujadas "
            f"({info['declared']} declaradas y {info['inferred']} inferidas).",
            size=8.5,
            space_after=2,
        )
        add_image(document, info["path"], max_width, max_height)
        add_caption(
            document,
            f"Figura {figure}.",
            f"Modelo entidad-relación del dominio {spec['title'].lower()}.",
        )
        figure += 1

    # ---- relation catalog ----
    catalog_section = document.add_section(WD_SECTION.NEW_PAGE)
    catalog_section.orientation = WD_ORIENT.PORTRAIT
    catalog_section.page_width = Inches(8.5)
    catalog_section.page_height = Inches(11)
    catalog_section.left_margin = Inches(0.8)
    catalog_section.right_margin = Inches(0.8)
    catalog_section.top_margin = Inches(0.7)
    catalog_section.bottom_margin = Inches(0.7)
    configure_header(catalog_section, "Modelo entidad-relación  ·  condominio_cobranzas")
    configure_footer(catalog_section, "ProyectoT3")

    add_heading(document, "4. Catálogo de relaciones", 1)
    add_body(
        document,
        "Una fila por cada referencia entre tablas. La columna Ámbito indica si la relación se dibuja "
        "en el diagrama del dominio o si cruza hacia otro dominio.",
    )
    relation_rows = []
    for relation in relations:
        child_column = relation.child_columns[0]
        column = schema[relation.child].column(child_column)
        parent_column = relation.parent_columns[0]
        cardinality = "1 : 1" if relation.one_to_one(schema) else "N : 1"
        scope_label = (
            "Interna"
            if ownership.get(relation.child) == ownership.get(relation.parent)
            else "Entre dominios"
        )
        if any(
            other.child == relation.parent and other.parent == relation.child
            for other in relations
        ):
            cardinality += " recíproca"
        relation_rows.append(
            (
                f"{relation.child}.{child_column}",
                "Sí" if relation.required(schema) else "No",
                f"{relation.parent}.{parent_column}",
                cardinality,
                "Declarada" if relation.declared else "Inferida",
                relation.on_delete or "-",
                scope_label,
            )
        )
    add_table(
        document,
        ["Origen", "Obligatoria", "Destino", "Cardinalidad", "Tipo", "ON DELETE", "Ámbito"],
        relation_rows,
        [1.6, 0.64, 1.5, 0.72, 0.6, 0.62, 0.86],
        font_size=7.2,
    )
    _ = column  # column lookup kept for readability of the intent

    # ---- table catalog ----
    document.add_page_break()
    add_heading(document, "5. Catálogo de tablas", 1)
    add_body(
        document,
        "Cada tabla se documenta con sus columnas en el orden físico del esquema. La columna Clave "
        "marca PRIMARY KEY, UNIQUE y las referencias a otras tablas.",
    )

    used_column_names = {}
    for spec in DOMAINS:
        add_heading(document, spec["title"], 2, size=12)
        for table_name in spec["tables"]:
            table = schema[table_name]
            heading = document.add_paragraph()
            heading.paragraph_format.space_before = Pt(9)
            heading.paragraph_format.space_after = Pt(3)
            heading.paragraph_format.keep_with_next = True
            run = heading.add_run(table_name)
            run.bold = True
            run.font.size = Pt(10)
            run.font.name = "Consolas"
            run.font.color.rgb = RGBColor(0x17, 0x2A, 0x3F)
            run2 = heading.add_run(
                f"     {len(table.columns)} columnas  ·  PK ({', '.join(table.primary_key)})"
                + (
                    "  ·  "
                    + (
                        f"{len(table.foreign_keys)} FK física"
                        if len(table.foreign_keys) == 1
                        else f"{len(table.foreign_keys)} FK físicas"
                    )
                    if table.foreign_keys
                    else "  ·  sin FK físicas"
                )
            )
            run2.font.size = Pt(7.5)
            run2.font.color.rgb = RGBColor(0x6B, 0x7A, 0x8C)

            rows = []
            for column in table.columns:
                keys = []
                if column.name in table.primary_key:
                    keys.append("PK")
                for relation in relations:
                    if relation.child == table_name and column.name in relation.child_columns:
                        keys.append(f"FK -> {relation.parent}.{relation.parent_columns[0]}")
                for unique in table.uniques:
                    if column.name in unique and len(unique) == 1:
                        keys.append("UNIQUE")
                notes = []
                if column.default is not None:
                    default = column.default
                    if len(default) > 40:
                        default = default[:37] + "..."
                    notes.append(f"default {default}")
                if column.auto_increment:
                    notes.append("auto_increment")
                if column.comment:
                    notes.append(column.comment)
                legacy = LEGACY_COLUMN_NOTES.get((table_name, column.name))
                if legacy:
                    notes.append(legacy)
                display_type, type_note = present_type(column.type)
                if type_note:
                    notes.append(type_note)
                rows.append(
                    (
                        column.name,
                        display_type,
                        "Sí" if column.nullable else "No",
                        "  ".join(keys) if keys else "",
                        "; ".join(notes),
                    )
                )
            add_table(
                document,
                ["Columna", "Tipo", "Nulo", "Clave", "Notas"],
                rows,
                [1.35, 1.3, 0.45, 1.45, 2.25],
                font_size=7.0,
            )
            spacer = document.add_paragraph()
            spacer.paragraph_format.space_after = Pt(2)

    # ---- method note ----
    document.add_page_break()
    add_heading(document, "6. Notas metodológicas", 1)

    add_heading(document, "6.1  Fuente y vigencia", 2, size=11.5)
    add_body(
        document,
        f"El modelo se extrajo de {dump_path.name}. El volcado incluye las columnas incorporadas por las "
        "migraciones hasta la fase 22 (entre otras, referencia_norm, archivo_hash, dup_guard, "
        "cuenta_bancaria_id, fecha_expiracion y soporte_hash), por lo que refleja el esquema después de "
        "aplicadas las migraciones. Si se agregan migraciones nuevas, este documento queda desactualizado "
        "hasta regenerarlo.",
    )

    add_heading(document, "6.2  Cómo se recuperaron las relaciones inferidas", 2, size=11.5)
    add_body(
        document,
        "El motor declara solo 18 claves foráneas. El resto de las referencias se recuperó con tres "
        "fuentes: la convención de nombres de columna (por ejemplo unidad_id hacia unidades), la lectura "
        "de los modelos en app/Models para confirmar el destino real de cada columna, y la migración de "
        "la fase 16 para la conciliación bancaria, donde movimiento_id apunta a extractos_bancarios. "
        "Toda referencia que no pudo confirmarse con evidencia quedo fuera del diagrama y se documenta "
        "abajo como polimórfica.",
    )

    add_heading(document, "6.3  Referencias polimórficas", 2, size=11.5)
    add_body(
        document,
        "Estas columnas guardan un identificador cuyo destino depende de otra columna de la misma fila. "
        "No se dibujan como relaciones porque hacerlo afirmaría un destino único que la base no tiene.",
    )
    add_table(
        document,
        ["Tabla", "Columna", "Por qué no se dibuja"],
        [(table, column, note) for table, column, note in polymorphic],
        [1.55, 1.25, 4.0],
        font_size=7.5,
    )

    add_heading(document, "6.4  Observaciones sobre el esquema", 2, size=11.5)
    observations = [
        "Existe una relación recíproca entre unidades y personas: unidades.propietario_id referencia al "
        "propietario y personas.unidad_id referencia a la unidad que habita la persona. En el diagrama se "
        "dibujan dos conectores paralelos, uno por cada dirección.",
        "La fase 16 agregó movimientos_bancarios como copia canónica de extractos_bancarios, pero el "
        "importador nunca volvió a escribir en ella y la fase 23 la retiró. extractos_bancarios es la "
        "única fuente de verdad. pagos y comprobantes_pago conservan columnas equivalentes "
        "(referencia y referencia_norm).",
        "movimientos_cuenta.referencia_id es polimórfica: referencia_tipo decide si el movimiento "
        "corresponde a una factura, un pago o un ajuste. Por eso no hay clave foránea posible ahí.",
        "log_auditoria concentra dos referencias distintas a usuarios (admin_id y usuario_id) y una "
        "referencia polimórfica a la entidad auditada (registro_id con tabla_afectada).",
        "notificaciones mantiene residente_id y persona_id apuntando a la misma tabla; la segunda es la "
        "columna vigente.",
    ]
    for text in observations:
        paragraph = document.add_paragraph(style="List Bullet")
        run = paragraph.add_run(text)
        run.font.size = Pt(8.5)
        paragraph.paragraph_format.space_after = Pt(4)

    add_heading(document, "6.5  Regeneración", 2, size=11.5)
    add_body(
        document,
        "El documento se genera con scripts/generate_er_diagram_docx.py a partir del volcado. El script "
        "falla de forma explícita cuando aparece una columna de referencia que su mapa de relaciones no "
        "clasifica, de modo que una migración nueva no puede pasar inadvertida. Requiere Pillow y "
        "python-docx en un entorno aparte; no agrega dependencias al proyecto.",
    )

    document.save(str(out_path))
    return {
        "pages_estimate": None,
        "sections": len(document.sections),
        "tables": len(document.tables),
        "relations": len(relations),
    }


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser(description="Genera el documento del modelo entidad-relación.")
    parser.add_argument("--dump", default=str(root / "database" / "condominio_cobranzas.sql"))
    parser.add_argument(
        "--out", default=str(root / "database" / "Diagrama_ER_ProyectoT3.docx")
    )
    parser.add_argument("--assets-dir", default=None)
    parser.add_argument("--keep-assets", action="store_true")
    args = parser.parse_args()

    dump_path = Path(args.dump)
    out_path = Path(args.out)
    if not dump_path.exists():
        print(f"ERROR: dump not found: {dump_path}", file=sys.stderr)
        return 2

    text = dump_path.read_text(encoding="utf-8", errors="replace")
    schema = parse_dump(text)
    if not schema:
        print("ERROR: no CREATE TABLE statements found", file=sys.stderr)
        return 2

    assigned = [table for spec in DOMAINS for table in spec["tables"]]
    if sorted(assigned) != sorted(schema):
        missing = sorted(set(schema) - set(assigned))
        unknown = sorted(set(assigned) - set(schema))
        print(f"ERROR: domain assignment mismatch. unassigned={missing} unknown={unknown}", file=sys.stderr)
        return 2

    relations, polymorphic = build_relations(schema)

    assets = Path(args.assets_dir) if args.assets_dir else Path(tempfile.mkdtemp(prefix="er-assets-"))
    assets.mkdir(parents=True, exist_ok=True)

    images = {}
    images["legend"] = render_legend(assets / "leyenda.png")
    for spec in DOMAINS:
        images[spec["key"]] = render_domain(spec, schema, relations, assets / f"dominio-{spec['key']}.png")

    info = build_document(schema, relations, polymorphic, images, out_path, dump_path)

    print(f"dump: {dump_path}")
    print(f"tables: {len(schema)}  relations: {len(relations)} "
          f"(declared {sum(1 for r in relations if r.declared)}, inferred {sum(1 for r in relations if not r.declared)})")
    print(f"polymorphic: {len(polymorphic)}")
    for spec in DOMAINS:
        data = images[spec["key"]]
        canvas_w, canvas_h = data["canvas"]
        natural_w = canvas_w / 220.0
        natural_h = canvas_h / 220.0
        landscape = canvas_w / canvas_h > 1.15
        max_w, max_h = (9.8, 6.3) if landscape else (6.9, 8.2)
        fit = min(1.0, max_w / natural_w, max_h / natural_h)
        pt = NAME_PX / 220.0 * 72.0 * fit
        print(
            f"  {spec['key']}: {canvas_w}x{canvas_h}px  {data['own']} own + "
            f"{data['referenced']} ref  {data['relations']} edges  "
            f"{'landscape' if landscape else 'portrait'}  name ~{pt:.1f}pt"
        )
    print(f"docx: {out_path}  sections={info['sections']} tables={info['tables']}")
    print(f"assets: {assets}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
