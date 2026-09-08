#!/usr/bin/env bash
# =====================================================================
# comparar_arboles.sh — chequeo de sincronización antes de deployar
#
# El proyecto mantiene DOS copias intencionales de ciertos archivos
# (ver MEMORY.md / RELEVAMIENTO.md sección 1 y 5):
#   1. DEPLOY_HOSTINGER/  vs  _dev_no_subir/  — mismo código de
#      aplicación, solo cambian las rutas relativas de los `require`.
#   2. database.sql  vs  carta_real.sql — el bloque de categorías y
#      productos de la carta real está duplicado en ambos archivos.
#
# Nada sincroniza estas copias automáticamente: si se edita una sin
# tocar la otra, quedan desincronizadas sin ningún aviso. Este script
# no corrige nada, solo LISTA diferencias reales para revisar a mano.
#
# Uso: correrlo desde la raíz del repo antes de cada deploy.
#   bash scripts/comparar_arboles.sh
# Sale con código 0 si todo está sincronizado, 1 si encontró diferencias.
# =====================================================================
set -u
cd "$(dirname "$0")/.."

ENCONTRO_DIFERENCIAS=0

echo "== 1) DEPLOY_HOSTINGER/ vs _dev_no_subir/public_html/ (código de app) =="
# Normaliza la única diferencia esperada (un nivel extra de "../" hacia
# includes/config) antes de comparar, así el diff solo muestra
# diferencias REALES de contenido.
while IFS= read -r -d '' archivo; do
    relativo="${archivo#DEPLOY_HOSTINGER/}"
    par="_dev_no_subir/public_html/$relativo"
    if [ ! -f "$par" ]; then
        echo "  FALTA en _dev_no_subir: $relativo"
        ENCONTRO_DIFERENCIAS=1
        continue
    fi
    # Se ignoran diferencias de fin de línea (CRLF/LF): no son una
    # desincronización real de contenido, solo ruido de cómo cada
    # herramienta escribió el archivo.
    if ! diff -q \
        <(sed -e "s#__DIR__ \. '/\.\./includes/#__DIR__ . '/../../includes/#g" \
              -e "s#__DIR__ \. '/\.\./config/#__DIR__ . '/../../config/#g" \
              -e "s#__DIR__ \. '/includes/#__DIR__ . '/../includes/#g" \
              -e "s#__DIR__ \. '/config/#__DIR__ . '/../config/#g" "$archivo" | tr -d '\r') \
        <(tr -d '\r' < "$par") > /dev/null; then
        echo "  DIFIERE: $relativo"
        ENCONTRO_DIFERENCIAS=1
    fi
done < <(find DEPLOY_HOSTINGER -type f -name '*.php' \
    ! -path 'DEPLOY_HOSTINGER/config/*' ! -path 'DEPLOY_HOSTINGER/includes/*' -print0)

echo "== 2) DEPLOY_HOSTINGER/includes/ vs _dev_no_subir/includes/ (idénticos, sin ajuste de rutas) =="
while IFS= read -r -d '' archivo; do
    relativo="${archivo#DEPLOY_HOSTINGER/includes/}"
    par="_dev_no_subir/includes/$relativo"
    if [ ! -f "$par" ] || ! diff -q <(tr -d '\r' < "$archivo") <(tr -d '\r' < "$par") > /dev/null; then
        echo "  DIFIERE: includes/$relativo"
        ENCONTRO_DIFERENCIAS=1
    fi
done < <(find DEPLOY_HOSTINGER/includes -type f -print0)

echo "== 3) config.example.php y archivos .htaccess =="
if ! diff -q <(tr -d '\r' < DEPLOY_HOSTINGER/config/config.example.php) <(tr -d '\r' < _dev_no_subir/config/config.example.php) > /dev/null; then
    echo "  DIFIERE: config/config.example.php"
    ENCONTRO_DIFERENCIAS=1
fi
if ! diff -q <(tr -d '\r' < DEPLOY_HOSTINGER/.htaccess) <(tr -d '\r' < _dev_no_subir/public_html/.htaccess) > /dev/null; then
    echo "  DIFIERE: .htaccess (raíz)"
    ENCONTRO_DIFERENCIAS=1
fi
if ! diff -q <(tr -d '\r' < DEPLOY_HOSTINGER/config/.htaccess) <(tr -d '\r' < _dev_no_subir/config/.htaccess) > /dev/null; then
    echo "  DIFIERE: config/.htaccess"
    ENCONTRO_DIFERENCIAS=1
fi

echo "== 4) assets/vendor/ (Bootstrap y Chart.js vendorizados) =="
while IFS= read -r -d '' archivo; do
    relativo="${archivo#DEPLOY_HOSTINGER/assets/vendor/}"
    par="_dev_no_subir/public_html/assets/vendor/$relativo"
    if [ ! -f "$par" ] || ! cmp -s "$archivo" "$par"; then
        echo "  DIFIERE: assets/vendor/$relativo"
        ENCONTRO_DIFERENCIAS=1
    fi
done < <(find DEPLOY_HOSTINGER/assets/vendor -type f -print0 2>/dev/null)

echo "== 5) database.sql vs carta_real.sql (bloque CARTA REAL) =="
extraer_carta() {
    sed -n '/-- INICIO_CARTA_COMPARABLE/,/-- FIN_CARTA_COMPARABLE/p' "$1" | tr -d '\r'
}
if ! diff -q <(extraer_carta database.sql) <(extraer_carta carta_real.sql) > /dev/null; then
    echo "  DIFIEREN los productos/categorías entre database.sql y carta_real.sql."
    echo "  Corré: diff <(sed -n '/INICIO_CARTA_COMPARABLE/,/FIN_CARTA_COMPARABLE/p' database.sql) <(sed -n '/INICIO_CARTA_COMPARABLE/,/FIN_CARTA_COMPARABLE/p' carta_real.sql)"
    ENCONTRO_DIFERENCIAS=1
fi

echo
if [ "$ENCONTRO_DIFERENCIAS" -eq 0 ]; then
    echo "OK: los árboles están sincronizados."
else
    echo "Hay diferencias sin resolver (ver arriba). Revisalas antes de deployar."
fi
exit "$ENCONTRO_DIFERENCIAS"
