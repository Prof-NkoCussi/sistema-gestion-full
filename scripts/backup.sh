#!/usr/bin/env bash
# scripts/backup.sh: copia de seguridad de la base escuela con mysqldump
# Corre como root: root entra a MySQL por el socket (auth_socket), sin contraseña en ningún archivo.
# Instalación en el servidor (copia de root, que solo root puede modificar):
#   sudo install -m 755 -o root -g root scripts/backup.sh /usr/local/sbin/backup-escuela.sh
# Programación con sudo crontab -e (todos los días a las 2:30):
#   30 2 * * * /usr/local/sbin/backup-escuela.sh
# Resultado de cada copia, en el registro del sistema:
#   journalctl -t backup-escuela

# -e: si un comando falla, el script se corta ahí
# -u: usar una variable que no existe es un error (evita rutas vacías)
# -o pipefail: en mysqldump | gzip, si falla mysqldump falla toda la tubería
set -euo pipefail

# cron arranca con un PATH mínimo: lo fijamos para encontrar mysqldump, gzip y find
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# Configuración
BASE="escuela"                            # la base que se copia
DESTINO="/var/backups/escuela"            # carpeta de las copias
DIAS=7                                    # las copias de más de 7 días se borran
FECHA="$(date +%Y-%m-%d_%H%M)"            # ejemplo: 2026-11-27_0230
ARCHIVO="$DESTINO/${BASE}_${FECHA}.sql.gz"
ETIQUETA="backup-escuela"                 # nombre con el que aparece en el registro

# id -u: el número del usuario que corre el script; root es el 0
if [ "$(id -u)" -ne 0 ]; then
    echo "Este script se corre como root: sudo $0" >&2
    exit 1
fi

# umask 077: todo lo que cree el script queda solo para root (carpeta 700, archivos 600)
umask 077

# trap ... ERR: si un comando falla, deja el error en el registro y borra la copia a medio hacer
trap 'logger -t "$ETIQUETA" -p user.err "ERROR: falló el backup de $BASE"; rm -f "$ARCHIVO.tmp"' ERR

# -p: crea la carpeta si no existe (y no da error si ya está)
mkdir -p "$DESTINO"

# mysqldump escribe la base como sentencias SQL; gzip la comprime al vuelo
#   --single-transaction: copia consistente de las tablas InnoDB, sin bloquear el sistema
#   --no-tablespaces: no pide el privilegio PROCESS (no lo necesita esta base)
#   sin --databases: el archivo no trae CREATE DATABASE ni USE, así se puede
#   restaurar en otra base (por ejemplo escuela_prueba) sin tocar la real
mysqldump --single-transaction --no-tablespaces --default-character-set=utf8mb4 "$BASE" \
    | gzip > "$ARCHIVO.tmp"

# gzip -t: comprueba que el comprimido esté sano antes de darlo por bueno
gzip -t "$ARCHIVO.tmp"

# Recién cuando todo salió bien, la copia toma su nombre definitivo
mv "$ARCHIVO.tmp" "$ARCHIVO"

# Rotación: borra las copias de esta base con más de $DIAS días (-mtime +7)
find "$DESTINO" -name "${BASE}_*.sql.gz" -mtime +"$DIAS" -delete

# du -h: el tamaño de la copia en formato legible (8,0K); cut -f1 se queda con el número
TAMANIO="$(du -h "$ARCHIVO" | cut -f1)"
logger -t "$ETIQUETA" "OK: $ARCHIVO ($TAMANIO)"

# -t 1: la salida es una terminal (corrida a mano); con cron no muestra nada
if [ -t 1 ]; then
    echo "Backup OK: $ARCHIVO ($TAMANIO)"
fi
