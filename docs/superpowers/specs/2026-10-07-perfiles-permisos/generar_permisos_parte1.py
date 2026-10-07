"""
Generador de la parte 1 de "Perfiles y permisos" (2026-10-07).

Lee la foto del "antes" (tests/Fixtures/route_access_antes_de_perfiles.json) y:
  1. Asigna a cada ruta protegida por `role:<lista>` su permiso.
  2. VERIFICA que todas las rutas de un mismo permiso tenían la misma lista de
     perfiles (si no, el día uno alguien ganaría o perdería acceso) y aborta.
  3. Escribe backend/app/Support/PermissionCatalog.php con los perfiles que
     tienen cada permiso el día uno.
  4. Reescribe backend/routes/api.php: cada bloque `role:` pasa a
     `->middleware('permission:<nombre>')` por ruta.

Se corre UNA vez. Queda en el repo como registro de cómo se derivó el catálogo.
"""
import json
import re
import sys

RAIZ = "/datos/Documentos/PERSONAL/AgriFlor/backend"
FOTO = json.load(open(f"{RAIZ}/tests/Fixtures/route_access_antes_de_perfiles.json"))
FANTASMAS = {"liquidador", "farm_operator"}  # nombres en rutas que no existen como perfil

MODULOS = [
    ("master", "Datos Maestros"), ("technical", "Procesos Técnicos"), ("purchases", "Compras"),
    ("outputs", "Salidas"), ("reception", "Recepción"), ("inventory", "Inventario"),
    ("reports", "Reportes"), ("liquidation", "Liquidación"), ("performance", "Rendimiento"),
    ("admin", "Administración"), ("audit", "Auditoría"),
]

# (nombre, etiqueta, módulo, grupo, acción, es_menú, perfiles_fijos | None = se deriva de las rutas)
C = []
def p(nombre, etiqueta, modulo, grupo, accion, menu=False, fijos=None):
    C.append(dict(name=nombre, label=etiqueta, module=modulo, group=grupo, action=accion, menu=menu, fijos=fijos))

# --- Datos Maestros
p("view_master_data", "Ver Datos Maestros", "master", "Menú", "view", menu=True, fijos=["purchasing"])
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_product", f"{e} productos", "master", "Productos", a)
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_master_data", f"{e} catálogos", "master", "Catálogos (marcas, categorías, unidades, proveedores)", a)
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_location", f"{e} ubicaciones", "master", "Ubicaciones", a)
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_farm_lot", f"{e} lotes de finca", "master", "Lotes de finca", a)
p("manage_output_types", "Administrar tipos de salida", "master", "Tipos de salida", "special")
# --- Procesos Técnicos
p("view_technical", "Ver Procesos Técnicos", "technical", "Menú", "view", menu=True, fijos=["agronomist"])
p("view_recipes", "Ver recetas", "technical", "Recetas técnicas", "view")
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_recipe", f"{e} recetas", "technical", "Recetas técnicas", a)
p("view_technical_orders", "Ver órdenes técnicas", "technical", "Órdenes técnicas", "view")
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_technical_order", f"{e} órdenes técnicas", "technical", "Órdenes técnicas", a)
p("process_technical_order", "Aprobar, completar y cancelar órdenes técnicas", "technical", "Órdenes técnicas", "special")
# --- Compras
p("view_purchases", "Ver Compras", "purchases", "Menú", "view", menu=True, fijos=["purchasing"])
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_purchase", f"{e} compras", "purchases", "Compras", a)
p("reverse_purchase", "Eliminar compra recibida (revierte inventario)", "purchases", "Compras", "special")
# --- Salidas
p("view_outputs", "Ver Salidas", "outputs", "Salidas", "view", menu=True)
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_output", f"{e} salidas", "outputs", "Salidas", a)
p("approve_output", "Aprobar salidas", "outputs", "Salidas", "special")
p("register_application", "Registrar aplicaciones", "outputs", "Aplicaciones", "special")
p("approve_application", "Aprobar aplicaciones", "outputs", "Aplicaciones", "special")
# --- Recepción
p("view_reception", "Ver Recepción", "reception", "Menú", "view", menu=True,
  fijos=["agronomist", "farm", "financiero", "purchasing", "supervisor", "warehouse"])
p("create_reception", "Crear recepciones", "reception", "Recepciones", "create")
p("edit_reception", "Completar, cancelar y finalizar recepciones", "reception", "Recepciones", "edit")
# --- Inventario
p("view_inventory", "Ver Inventario", "inventory", "Menú", "view", menu=True,
  fijos=["agronomist", "financiero", "supervisor", "warehouse"])
p("adjust_inventory", "Ajustes de inventario (reservado)", "inventory", "Ajustes", "special",
  fijos=["financiero", "supervisor", "warehouse"])
p("approve_adjustment", "Aprobar y rechazar ajustes", "inventory", "Ajustes", "special")
p("manage_alerts", "Crear y resolver alertas", "inventory", "Alertas", "special")
# --- Reportes
p("view_reports", "Ver Reportes", "reports", "Menú", "view", menu=True, fijos=["financiero"])
p("export_reports", "Exportar informes a Excel y PDF", "reports", "Informes", "special", fijos=["financiero"])
# --- Liquidación
p("view_liquidation", "Ver Liquidación", "liquidation", "Trabajadores, tareas y asignaciones", "view", menu=True)
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_liquidation", f"{e} en liquidación", "liquidation", "Trabajadores, tareas y asignaciones", a)
p("view_liquidation_reports", "Ver y exportar informes de liquidación", "liquidation", "Informes", "special")
# --- Rendimiento
p("create_schedule", "Crear programaciones", "performance", "Programación", "create")
p("edit_schedule", "Editar y cancelar programaciones", "performance", "Programación", "edit")
p("finalize_schedule", "Finalizar programaciones", "performance", "Programación", "special")
for a, e in (("create", "Crear"), ("edit", "Editar"), ("delete", "Eliminar")):
    p(f"{a}_task_catalog", f"{e} catálogo de tareas", "performance", "Catálogo de tareas", a)
p("register_task_log", "Registrar avance diario", "performance", "Registro diario", "special")
p("delete_task_log", "Eliminar registros de avance", "performance", "Registro diario", "special")
p("manage_performance_settings", "Cambiar la configuración de rendimiento", "performance", "Configuración", "special")
# --- Administración
p("view_admin", "Ver Administración", "admin", "Menú", "view", menu=True, fijos=[])
p("manage_users", "Administrar usuarios", "admin", "Usuarios", "special")
p("manage_companies", "Administrar empresas", "admin", "Empresas", "special")
p("manage_roles", "Administrar perfiles y permisos", "admin", "Perfiles", "special", fijos=[])
# --- Auditoría (la ruta sigue por nombre de perfil: role:auditor)
p("audit.view", "Ver la auditoría", "audit", "Menú", "view", menu=True, fijos=["auditor"])

OBSOLETOS = ["view_products", "manage_master_data", "manage_technical", "delete_reception", "system_settings"]

REGLAS = [  # (regex sobre "METODO uri-sin-api/", permiso) — la primera que coincide gana
    (r"^(GET|POST|PUT|PATCH|DELETE) users(/|$)", "manage_users"),
    (r"^POST products$", "create_product"), (r"^PUT products/", "edit_product"), (r"^DELETE products/", "delete_product"),
    (r"^(POST|DELETE) suppliers/\{id\}/contacts", "edit_master_data"),
    (r"^POST (brands|categories|suppliers|packaging-units|base-units)$", "create_master_data"),
    (r"^PUT (brands|categories|suppliers|packaging-units|base-units)/", "edit_master_data"),
    (r"^DELETE (brands|categories|suppliers|packaging-units|base-units)/", "delete_master_data"),
    (r"^(POST|PUT|DELETE) companies(/|$)", "manage_companies"),
    (r"^POST locations$", "create_location"), (r"^PUT locations/", "edit_location"), (r"^DELETE locations/", "delete_location"),
    (r"^POST farm-lots$", "create_farm_lot"), (r"^PUT farm-lots/", "edit_farm_lot"), (r"^DELETE farm-lots/", "delete_farm_lot"),
    (r"^(POST|PUT|DELETE) output-types(/|$)", "manage_output_types"),
    (r"^GET technical-recipes", "view_recipes"), (r"^POST technical-recipes", "create_recipe"),
    (r"^PUT technical-recipes/", "edit_recipe"), (r"^DELETE technical-recipes/", "delete_recipe"),
    (r"^GET technical-orders", "view_technical_orders"),
    (r"^POST technical-orders/\{id\}/(approve|complete|cancel)$", "process_technical_order"),
    (r"^POST technical-orders$", "create_technical_order"), (r"^PUT technical-orders/", "edit_technical_order"),
    (r"^DELETE technical-orders/", "delete_technical_order"),
    (r"^(GET purchases/\{id\}/reversal-preview|POST purchases/\{id\}/reverse)$", "reverse_purchase"),
    (r"^POST purchases$", "create_purchase"), (r"^DELETE purchases/\{id\}$", "delete_purchase"),
    (r"^(PUT purchases/\{id\}(/cancel)?|(POST|DELETE) purchases/\{id\}/attachments.*)$", "edit_purchase"),
    (r"^POST product-outputs/\{id\}/approve$", "approve_output"),
    (r"^(POST product-outputs/\{id\}/register-application|GET product-outputs/\{id\}/applications)$", "register_application"),
    (r"^(GET product-outputs.*|POST product-outputs/validate-inventory)$", "view_outputs"),
    (r"^POST product-outputs$", "create_output"),
    (r"^(PUT product-outputs/\{id\}|POST product-outputs/\{id\}/(mark-in-transit|complete))$", "edit_output"),
    (r"^DELETE product-outputs/", "delete_output"),
    (r"^POST applications/\{id\}/approve$", "approve_application"),
    (r"^POST applications(/\{id\}/cancel)?$", "register_application"),
    (r"^POST receptions(/direct-reception|/\{id\}/batches)?$", "create_reception"),
    (r"^(PUT receptions/\{id\}/(complete|cancel)|POST receptions/\{id\}/(close-with-available|finalize))$", "edit_reception"),
    (r"^PUT adjustments/\{id\}/(approve|reject)$", "approve_adjustment"),
    (r"^(POST alerts|PUT alerts/)", "manage_alerts"),
    (r"^GET (workers|tasks|daily-assignments)", "view_liquidation"),
    (r"^POST (workers|tasks|daily-assignments)", "create_liquidation"),
    (r"^PUT (workers|tasks|daily-assignments)", "edit_liquidation"),
    (r"^DELETE (workers|tasks|daily-assignments)", "delete_liquidation"),
    (r"^(GET|POST) reports/(analytics|liquidation)", "view_liquidation_reports"),
    (r"^POST performance/schedules$", "create_schedule"),
    (r"^POST performance/schedules/\{id\}/finalize$", "finalize_schedule"),
    (r"^POST performance/schedules/\{id\}/logs$", "register_task_log"),
    (r"^(PUT performance/schedules/\{id\}|POST performance/schedules/\{id\}/cancel)$", "edit_schedule"),
    (r"^DELETE performance/logs/", "delete_task_log"),
    (r"^PUT performance/settings$", "manage_performance_settings"),
    (r"^POST performance/(task-catalog|task-categories)$", "create_task_catalog"),
    (r"^(PUT|PATCH) performance/(task-catalog|task-categories)/", "edit_task_catalog"),
    (r"^DELETE performance/task-categories/", "delete_task_catalog"),
]
SIGUEN_POR_NOMBRE = [r"^(GET|POST) admin/", r"^GET audits"]  # mantenimiento (role:admin) y auditoría (role:auditor)


def permiso_de(clave):
    for patron in SIGUEN_POR_NOMBRE:
        if re.search(patron, clave):
            return None
    for patron, permiso in REGLAS:
        if re.search(patron, clave):
            return permiso
    raise SystemExit(f"SIN REGLA: {clave}")


# ---------------------------------------------------------------- 1 y 2
por_permiso = {}
RUTA_A_PERMISO = {}
for clave, puerta in FOTO.items():
    if puerta["tipo"] != "perfiles":
        continue
    corta = clave.replace(" api/", " ")
    perm = permiso_de(corta)
    if perm is None:
        continue
    RUTA_A_PERMISO[corta] = perm
    lista = tuple(sorted(set(puerta["perfiles"]) - {"admin"} - FANTASMAS))
    por_permiso.setdefault(perm, {}).setdefault(lista, []).append(corta)

errores = [f"{perm}: {dict(v)}" for perm, v in por_permiso.items() if len(v) > 1]
if errores:
    print("PERMISOS CON LISTAS DE PERFILES DISTINTAS (rompería la paridad):")
    print("\n".join(errores))
    sys.exit(1)

nombres = {c["name"] for c in C}
faltan = set(por_permiso) - nombres
assert not faltan, f"permisos usados en rutas que no están en el catálogo: {faltan}"

for c in C:
    derivado = list(next(iter(por_permiso[c["name"]]))) if c["name"] in por_permiso else None
    if c["fijos"] is not None and derivado is not None:
        assert sorted(c["fijos"]) == sorted(derivado), (c["name"], c["fijos"], derivado)
    c["roles"] = sorted(c["fijos"] if c["fijos"] is not None else (derivado or []))
    c["gates"] = len(sum(por_permiso.get(c["name"], {}).values(), []))

sin_ruta = [c["name"] for c in C if c["gates"] == 0]
print(f"catálogo: {len(C)} permisos · protegen rutas: {sum(1 for c in C if c['gates'])} · rutas con permiso: {len(RUTA_A_PERMISO)}")
print("sin ruta (menú o reservados):", ", ".join(sin_ruta))

# ---------------------------------------------------------------- 3
def php(v):
    if isinstance(v, bool):
        return "true" if v else "false"
    if isinstance(v, list):
        return "[" + ", ".join(php(x) for x in v) + "]"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "\\'") + "'"

filas = []
for i, c in enumerate(C):
    filas.append(
        f"            ['name' => {php(c['name'])}, 'label' => {php(c['label'])}, 'module' => {php(c['module'])}, "
        f"'group' => {php(c['group'])}, 'action' => {php(c['action'])}, 'menu' => {php(c['menu'])}, "
        f"'sort' => {(i + 1) * 10}, 'roles' => {php(c['roles'])}],"
    )
PLANTILLA = open("/datos/Documentos/PERSONAL/AgriFlor/docs/superpowers/specs/2026-10-07-perfiles-permisos/PermissionCatalog.plantilla.php").read()
salida = (PLANTILLA
          .replace("/*MODULOS*/", "\n".join(f"        {php(k)} => {php(v)}," for k, v in MODULOS))
          .replace("/*PERMISOS*/", "\n".join(filas))
          .replace("/*OBSOLETOS*/", ", ".join(php(o) for o in OBSOLETOS)))
open(f"{RAIZ}/app/Support/PermissionCatalog.php", "w").write(salida)
print("escrito app/Support/PermissionCatalog.php")

# ---------------------------------------------------------------- 4
lineas = open(f"{RAIZ}/routes/api.php").read().split("\n")
nuevo, i, bloques, rutas_cambiadas = [], 0, 0, 0
ABRE = re.compile(r"^(\s*)Route::middleware\('role:([a-z_,]+)'\)->group\(function \(\) \{\s*$")
RUTA = re.compile(r"^(\s*)Route::(get|post|put|patch|delete)\('([^']+)',(.*)\);\s*$")
RECURSO = re.compile(r"^(\s*)Route::apiResource\('([^']+)', ([A-Za-z\\]+)::class\);\s*$")

while i < len(lineas):
    m = ABRE.match(lineas[i])
    if not m:
        nuevo.append(lineas[i]); i += 1; continue
    sangria = m.group(1)
    j = i + 1
    while not re.match(rf"^{re.escape(sangria)}\}}\);\s*$", lineas[j]):
        j += 1
    cuerpo = lineas[i + 1:j]
    convertido, intactas = [], 0
    for l in cuerpo:
        r = RUTA.match(l)
        rec = RECURSO.match(l)
        if r:
            clave = f"{r.group(2).upper()} {r.group(3)}"
            perm = RUTA_A_PERMISO.get(clave)
            if perm is None:
                intactas += 1; convertido.append(l); continue
            convertido.append(f"{sangria}Route::{r.group(2)}('{r.group(3)}',{r.group(4)})->middleware('permission:{perm}');")
            rutas_cambiadas += 1
        elif rec:
            recurso, ctrl = rec.group(2), rec.group(3)
            if recurso == "users":
                convertido.append(f"{sangria}Route::apiResource('users', {ctrl}::class)->middleware('permission:manage_users');")
                rutas_cambiadas += 5
            elif recurso == "technical-recipes":
                for solo, perm in ((["index", "show"], "view_recipes"), (["store"], "create_recipe"),
                                   (["update"], "edit_recipe"), (["destroy"], "delete_recipe")):
                    convertido.append(f"{sangria}Route::apiResource('technical-recipes', {ctrl}::class)->only({php(solo)})->middleware('permission:{perm}');")
                rutas_cambiadas += 5
            else:
                raise SystemExit(f"apiResource sin regla: {l}")
        else:
            convertido.append(l[4:] if l.startswith(sangria + "    ") else l)  # comentarios y líneas en blanco, sin la sangría del grupo
    if intactas and intactas != sum(1 for l in cuerpo if RUTA.match(l) or RECURSO.match(l)):
        raise SystemExit(f"bloque mixto en la línea {i + 1}: unas rutas con permiso y otras no")
    if intactas:  # bloque que sigue por nombre (mantenimiento, auditoría): intacto
        nuevo.extend(lineas[i:j + 1])
    else:
        nuevo.extend(convertido); bloques += 1
    i = j + 1

open(f"{RAIZ}/routes/api.php", "w").write("\n".join(nuevo))
print(f"routes/api.php: {bloques} bloques role: convertidos, {rutas_cambiadas} rutas con permission:")
