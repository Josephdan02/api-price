# FASE 6.4.6 — BUSQUEDAS Y SUBRECURSO DE ESTABLECIMIENTOS

## Objetivo

Busqueda/filtros en GET /api/establecimientos y subrecurso
GET /api/establecimientos/{id}/fiscalizaciones, sin migraciones.

## Cambios

- index(Request): search LIKE OR en razon_social, nombre_comercial,
  codigo_osinergmin, ruc_dni; distrito LIKE; departamento LIKE;
  activo via filter_var BOOLEAN NULL_ON_FAILURE (invalido = ignorado).
  Mantiene orderBy razon_social, paginate 15, appends query.
- fiscalizaciones(id): 404 si no existe; relacion existente
  fiscalizaciones()->with(establecimiento,user)->orderBy fecha desc->paginate 15.
- Ruta: GET establecimientos/{establecimiento}/fiscalizaciones en grupo
  role ADMIN,FISCALIZADOR,CONSULTA, declarada ANTES de show para evitar shadowing.

## Roles

Ambos endpoints: ADMIN/FISCALIZADOR/CONSULTA 200, sin token 401.
Solo lectura, sin mutaciones.

## Ejemplos

- ?search=Huanuco | ?distrito=Huanuco | ?departamento=Huanuco
- ?activo=1 / ?activo=0 / ?activo=true
- ?search=Combo ABC&distrito=Huanuco&activo=1
- /api/establecimientos/5/fiscalizaciones

## Tests

- EstablecimientoTest +11 (search x5, filtros x4, combinado, sin filtros, activo invalido).
- EstablecimientoFiscalizacionesTest nuevo: 5 tests (2 propias, vacia, 404, roles, 401).
- Suite: 267 passed (834 assertions). migrate:status 17 Ran.
- route:list establecimientos: 6 rutas.

## Problemas

Ninguno. Sin discrepancias con el prompt; columnas verificadas en modelo.
