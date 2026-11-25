#!/bin/bash
set -Eeuo pipefail

# ==========================================
# CONFIGURACION
# ==========================================
APP_NAME="DeboletoCom"
APP_DIR="/var/www/DeBoletoCOM"
DEPLOY_USER="${1:-admin}"
PHP_VERSION="8.3"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"

MAINTENANCE_ENABLED=0

# Archivos versionados que ningún paso del despliegue debe sobrescribir
# (por ejemplo `php artisan reverb:install` o un vendor:publish --force).
PROTECTED_FILES=(
    "config/broadcasting.php"
    "config/reverb.php"
)

echo "=========================================="
echo " ACTUALIZACION DE ${APP_NAME}"
echo "=========================================="

# ==========================================
# VALIDACIONES
# ==========================================
if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: Ejecuta el script con sudo."
    exit 1
fi

if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    echo "ERROR: No existe el usuario $DEPLOY_USER"
    exit 1
fi

if [ ! -f "$APP_DIR/artisan" ]; then
    echo "ERROR: No existe $APP_DIR/artisan"
    exit 1
fi

if [ ! -d "$APP_DIR/.git" ]; then
    echo "ERROR: El proyecto no contiene un repositorio Git."
    exit 1
fi

for CMD in git composer node npm; do
    if ! command -v "$CMD" >/dev/null 2>&1; then
        echo "ERROR: No está instalado $CMD"
        exit 1
    fi
done

if [ ! -x "$APP_DIR/.venv/bin/python" ] && [ -d "$APP_DIR/.venv" ]; then
    echo "AVISO: Se encontró un entorno Python, pero no afecta este despliegue."
fi

APP_HOME="$(getent passwd "$DEPLOY_USER" | cut -d: -f6)"

# Ejecutar comandos como usuario propietario del proyecto
run_app() {
    sudo -u "$DEPLOY_USER" env \
        HOME="$APP_HOME" \
        APP_DIR="$APP_DIR" \
        bash -c 'cd "$APP_DIR" && exec "$@"' _ "$@"
}

# ==========================================
# RESTAURAR MODO NORMAL SI HAY UN ERROR
# ==========================================
cleanup() {
    EXIT_CODE=$?
    if [ "$MAINTENANCE_ENABLED" -eq 1 ]; then
        echo "Restaurando aplicación fuera de mantenimiento..."
        run_app php artisan up || true
    fi
    exit "$EXIT_CODE"
}

trap cleanup EXIT

# Restaura desde Git los archivos protegidos si algún paso los modificó.
protect_files() {
    for FILE in "${PROTECTED_FILES[@]}"; do
        if ! run_app git ls-files --error-unmatch "$FILE" >/dev/null 2>&1; then
            echo "ERROR: $FILE no está en Git; confírmalo antes de desplegar."
            exit 1
        fi

        if ! run_app git diff --quiet -- "$FILE"; then
            echo "AVISO: $FILE fue modificado durante el despliegue. Se restaura la versión de Git."
            run_app git checkout -- "$FILE"
        fi
    done
}

# ==========================================
# VALIDAR CAMBIOS LOCALES
# ==========================================
echo "[1/10] Verificando repositorio Git..."

if [ -n "$(run_app git status --porcelain)" ]; then
    echo "ERROR: Existen cambios locales o archivos sin confirmar."
    echo "Revisa el proyecto antes de actualizar:"
    run_app git status --short
    exit 1
fi

# ==========================================
# MODO MANTENIMIENTO
# ==========================================
echo "[2/10] Activando modo mantenimiento..."

run_app php artisan down --retry=60
MAINTENANCE_ENABLED=1

# ==========================================
# GIT PULL
# ==========================================
echo "[3/10] Actualizando proyecto desde Git..."

run_app git pull --ff-only

echo "Ultimo commit:"
run_app git log -1 --oneline

# ==========================================
# COMPOSER
# ==========================================
echo "[4/10] Instalando dependencias PHP..."

run_app composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

# ==========================================
# NODE Y VUE
# ==========================================
echo "[5/10] Instalando dependencias frontend..."

if [ -f "$APP_DIR/package.json" ]; then

    if [ -f "$APP_DIR/package-lock.json" ]; then
        run_app npm ci
    else
        echo "AVISO: No existe package-lock.json. Se utilizará npm install."
        run_app npm install
    fi

    echo "[6/10] Generando build de Vue/Vite..."

    run_app npm run build

else
    echo "AVISO: No existe package.json. Se omite frontend."
fi

# ==========================================
# ARCHIVOS PROTEGIDOS
# ==========================================
echo "Verificando archivos protegidos..."

protect_files

# ==========================================
# PERMISOS LARAVEL
# ==========================================
echo "[7/10] Configurando permisos..."

chown -R "$DEPLOY_USER":www-data \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"

chmod -R 2775 \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"

if command -v setfacl >/dev/null 2>&1; then
    setfacl -R -m u:www-data:rwX \
        "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

    setfacl -R -d -m u:www-data:rwX \
        "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
fi

# ==========================================
# LIMPIAR Y GENERAR CACHES
# ==========================================
echo "[8/10] Limpiando y generando caches Laravel..."

run_app php artisan optimize:clear

run_app php artisan config:cache

# Requiere que todos los nombres de rutas sean unicos.
run_app php artisan route:cache

run_app php artisan view:cache

run_app php artisan event:cache

# ==========================================
# REINICIAR SERVICIOS
# ==========================================
echo "[9/10] Reiniciando PHP-FPM..."

systemctl restart "$PHP_FPM_SERVICE"

systemctl is-active --quiet "$PHP_FPM_SERVICE" || {
    echo "ERROR: PHP-FPM no inició correctamente."
    exit 1
}

echo "Recargando Nginx..."

nginx -t
systemctl reload nginx

# Reiniciar workers de Laravel Queue, si existen.
run_app php artisan queue:restart

# Reiniciar Reverb para que tome el código y el .env nuevos (supervisor lo levanta).
run_app php artisan reverb:restart || echo "AVISO: Reverb no está corriendo."

# ==========================================
# SALIR DE MANTENIMIENTO
# ==========================================
echo "[10/10] Finalizando despliegue..."

run_app php artisan up
MAINTENANCE_ENABLED=0

echo ""
echo "=========================================="
echo " ACTUALIZACION COMPLETADA"
echo "=========================================="
echo "Proyecto: $APP_DIR"
echo "Usuario: $DEPLOY_USER"
echo "PHP: $PHP_VERSION"
echo "Git:"
run_app git log -1 --oneline

echo ""
echo "Estado PHP-FPM:"
systemctl is-active "$PHP_FPM_SERVICE"

echo "Estado Nginx:"
systemctl is-active nginx

echo ""
echo "Despliegue finalizado correctamente."
