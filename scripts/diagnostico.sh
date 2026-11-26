#!/usr/bin/env bash
# scripts/diagnostico.sh: estado del servidor en una sola pantalla
# Muestra la red (IP, gateway, DNS y si responden), los servicios, el firewall,
# el espacio en disco y el último backup de la base escuela.
# Uso, desde la carpeta del repo:
#   sudo scripts/diagnostico.sh
# Va con sudo porque las reglas de ufw y la carpeta de los backups son solo de root.

# -u: usar una variable que no existe es un error
# Sin -e a propósito: si una prueba falla, el script la informa y sigue con la siguiente
set -u

# Configuración
SERVICIOS="apache2 mysql smbd cron"      # servicios que tienen que estar activos
CARPETA_BACKUP="/var/backups/escuela"    # donde deja las copias scripts/backup.sh
ETIQUETA_BACKUP="backup-escuela"         # nombre del backup en el registro del sistema
NOMBRE_PRUEBA="google.com"               # nombre que se usa para probar el DNS
LIMITE_DISCO=80                          # avisa si el disco pasa este porcentaje
HORAS_BACKUP=26                          # avisa si el último backup es más viejo

# id -u: el número del usuario que corre el script; root es el 0
if [ "$(id -u)" -ne 0 ]; then
    echo "Este script se corre como root: sudo $0" >&2
    exit 1
fi

# Contador de problemas encontrados
FALLAS=0

# titulo: encabezado de cada sección
titulo() {
    echo
    echo "== $1 =="
}

# ok y falla: una línea con su marca; falla además suma un problema
ok() {
    echo "  [ OK  ] $1"
}
falla() {
    echo "  [FALLA] $1"
    FALLAS=$((FALLAS + 1))
}

# responde: un solo ping (-c 1) que espera como mucho 1 segundo (-W 1)
# > /dev/null 2>&1 descarta la salida: solo importa si respondió o no
responde() {
    ping -c 1 -W 1 "$1" > /dev/null 2>&1
}

echo "Diagnóstico de $(hostname) · $(date '+%d/%m/%Y %H:%M')"

# ---------------------------------------------------------------- Red
titulo "Red"

# La ruta por defecto trae el gateway y la placa:
#   default via 192.168.1.1 dev enp0s3 proto dhcp ...
#   campo:  1    2       3       4     5
RUTA="$(ip route show default | head -n 1)"

if [ -z "$RUTA" ]; then
    falla "Sin ruta por defecto: el servidor no sabe por dónde salir (revisar DHCP o netplan)"
else
    GATEWAY="$(echo "$RUTA" | awk '{print $3}')"
    PLACA="$(echo "$RUTA" | awk '{print $5}')"

    # -4: solo IPv4; -o: una línea por dirección; el campo 4 es la IP con la máscara
    LINEA_IP="$(ip -4 -o addr show dev "$PLACA" | head -n 1)"
    IP="$(echo "$LINEA_IP" | awk '{print $4}')"

    echo "  Placa:    $PLACA"
    echo "  IP:       ${IP:-sin IPv4}"

    # "dynamic" en la línea de la IP quiere decir que vino por DHCP
    if echo "$LINEA_IP" | grep -q dynamic; then
        echo "  Origen:   DHCP (la IP puede cambiar: conviene una reserva)"
    else
        echo "  Origen:   IP fija"
    fi

    if responde "$GATEWAY"; then
        ok "Gateway $GATEWAY responde"
    else
        falla "Gateway $GATEWAY no responde (cable, Wi-Fi, switch o router)"
    fi

    # resolvectl dns enp0s3 responde: "Link 2 (enp0s3): 8.8.8.8 1.1.1.1"
    # cut -d: -f2- se queda con lo que está después de los dos puntos
    DNS="$(resolvectl dns "$PLACA" 2>/dev/null | cut -d: -f2-)"

    if [ -z "${DNS// /}" ]; then
        falla "No hay servidores DNS configurados en $PLACA"
    fi

    # Sin comillas en $DNS: así el for recorre cada servidor por separado
    for SERVIDOR in $DNS; do
        if responde "$SERVIDOR"; then
            ok "DNS $SERVIDOR responde"
        else
            falla "DNS $SERVIDOR no responde"
        fi
    done

    # getent hosts: traduce un nombre igual que cualquier programa del sistema
    if getent hosts "$NOMBRE_PRUEBA" > /dev/null; then
        ok "El DNS traduce $NOMBRE_PRUEBA"
    else
        falla "No se puede traducir $NOMBRE_PRUEBA: falla el DNS (por IP puede andar)"
    fi
fi

# ---------------------------------------------------------------- Servicios
titulo "Servicios"

for SERVICIO in $SERVICIOS; do
    # is-active responde "active" si el servicio está andando
    ESTADO="$(systemctl is-active "$SERVICIO" 2>/dev/null)"
    if [ "$ESTADO" = "active" ]; then
        ok "$SERVICIO activo"
    else
        falla "$SERVICIO: ${ESTADO:-desconocido} (ver: systemctl status $SERVICIO)"
    fi
done

# ---------------------------------------------------------------- Firewall
titulo "Firewall (ufw)"

# LC_ALL=C: la salida en inglés, para buscar siempre la misma frase
if LC_ALL=C ufw status | grep -q "Status: active"; then
    ok "ufw activo. Reglas:"
    # sed: borra las 2 primeras líneas (estado y línea vacía), las líneas en blanco y sangra las demás
    LC_ALL=C ufw status | sed '1,2d; /^ *$/d; s/^/    /'
else
    falla "ufw inactivo: el servidor acepta conexiones a cualquier puerto"
fi

# ---------------------------------------------------------------- Disco
titulo "Disco"

# df -h /: la partición principal en formato legible (G, M)
df -h / | sed 's/^/  /'

# NR==2: la segunda línea (la primera son los títulos); el campo 5 es el uso (23%)
USO="$(df / | awk 'NR==2 {print $5}' | tr -d '%')"

if [ "$USO" -lt "$LIMITE_DISCO" ]; then
    ok "Disco al $USO%"
else
    falla "Disco al $USO%: liberar espacio (logs, backups viejos, paquetes)"
fi

# ---------------------------------------------------------------- Backup
titulo "Último backup"

# ls -t: ordena por fecha, el más nuevo primero; head -n 1 se queda con ese
ULTIMO="$(ls -t "$CARPETA_BACKUP"/*.sql.gz 2>/dev/null | head -n 1)"

if [ -z "$ULTIMO" ]; then
    falla "No hay copias en $CARPETA_BACKUP"
else
    # stat -c %Y: fecha del archivo en segundos; date +%s: la hora actual en segundos
    EDAD=$(( ( $(date +%s) - $(stat -c %Y "$ULTIMO") ) / 3600 ))
    echo "  Archivo:  $ULTIMO ($(du -h "$ULTIMO" | cut -f1))"
    if [ "$EDAD" -le "$HORAS_BACKUP" ]; then
        ok "Último backup hace $EDAD h"
    else
        falla "El último backup tiene $EDAD h: revisar cron y journalctl -t $ETIQUETA_BACKUP"
    fi
fi

# crontab -l: la programación de root; grep -v '^#' saca los comentarios
# y grep -F busca el nombre del backup tal cual
PROGRAMADO="$(crontab -l 2>/dev/null | grep -v '^#' | grep -F "$ETIQUETA_BACKUP")"
# grep -c . cuenta las líneas que no están vacías
CANTIDAD="$(echo "$PROGRAMADO" | grep -c .)"

if [ "$CANTIDAD" -eq 1 ]; then
    ok "Programado en cron: $PROGRAMADO"
elif [ "$CANTIDAD" -eq 0 ]; then
    falla "El backup no está en el crontab de root"
else
    falla "El backup aparece $CANTIDAD veces en el crontab de root (revisar con sudo crontab -e):"
    echo "$PROGRAMADO" | sed 's/^/    /'
fi

# Lo último que anotó backup.sh en el registro (OK o ERROR)
# -n 1: una sola entrada; -o cat: solo el mensaje; -q: sin avisos
REGISTRO="$(journalctl -t "$ETIQUETA_BACKUP" -n 1 -o cat -q 2>/dev/null)"
echo "  Registro: ${REGISTRO:-sin entradas}"

# ---------------------------------------------------------------- Resumen
# exit 0 si está todo bien y exit 1 si hubo fallas: así lo puede usar cron u otro script
echo
if [ "$FALLAS" -eq 0 ]; then
    echo "Resultado: todo OK"
    exit 0
else
    echo "Resultado: $FALLAS problema(s) para revisar"
    exit 1
fi
