#!/bin/bash
# fxw_probe.sh — sondeo SOLO LECTURA de la cámara Fujifilm por USB.
# Solo usa: gphoto2 --auto-detect, --summary, --list-all-config. Ninguno escribe en la cámara.
#
# Antes: cámara encendida, MENÚ > Configuración de red/USB > Ajuste de conexión
#        = "USB RAW CONV./BACKUP RESTORE" (o "PC CONNECTION MODE"), cable USB-C de datos.
set -u

command -v gphoto2 >/dev/null || { echo "Falta gphoto2: brew install gphoto2"; exit 1; }

OUT="probe_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$OUT"

# En macOS el demonio de Captura de Imagen (ptpcamerad) se adueña de la cámara y se relanza
# solo, así que lo vamos parando en segundo plano mientras dura el sondeo.
( while :; do killall -9 ptpcamerad 2>/dev/null; sleep 0.3; done ) &
KILLER=$!
trap 'kill $KILLER 2>/dev/null' EXIT
sleep 1

echo "== Detectando cámara"
gphoto2 --auto-detect | tee "$OUT/detect.txt"
if ! grep -qi fuji "$OUT/detect.txt"; then
  echo "No veo ninguna Fujifilm. Revisa el modo de conexión y que el cable sea de datos."
  exit 1
fi

echo "== Leyendo información del dispositivo y propiedades (puede tardar ~30 s)"
gphoto2 --summary > "$OUT/summary.txt" 2>&1
gphoto2 --list-all-config > "$OUT/config.txt" 2>&1

# No compartir el número de serie
sed -i '' -E 's/(Serial ?Number[^:]*:).*/\1 [oculto]/I; s/(Serial ?Number.*Current: ).*/\1[oculto]/I' "$OUT"/*.txt

echo
echo "== Resumen"
echo "Modelo:            $(grep -m1 -i 'model:' "$OUT/summary.txt" | sed 's/.*: *//')"
echo "Propiedades PTP:   $(grep -ciE '^ *0x[0-9a-f]{4}' "$OUT/summary.txt")"
echo "Propiedades Fuji (0xDxxx): $(grep -ciE '0xd[0-9a-f]{3}' "$OUT/summary.txt")"
echo "Entradas de config: $(grep -c '^/main' "$OUT/config.txt")"
echo
echo "Ficheros en $OUT/:  summary.txt  config.txt  — pásamelos en el chat."
