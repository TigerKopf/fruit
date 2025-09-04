#!/bin/bash
# Macht das Skript ausführbar
chmod +x "$0"

echo "--- HTTPD Entrypoint gestartet ---"
echo "Datum und Uhrzeit: $(date)"
echo "Aktuelles Verzeichnis (pwd): $(pwd)"
echo "Inhalt des aktuellen Verzeichnisses (ls -a .):"
ls -a .

echo "Inhalt des Apache Konfigurationsverzeichnisses (/usr/local/apache2/conf/):"
ls -a /usr/local/apache2/conf/

echo "Inhalt des Webroot-Verzeichnisses (/var/www/html/):"
ls -a /var/www/html/

# Optional: Wenn Sie eine spezifische .zip-Datei innerhalb des Repos entpacken möchten.
# BEACHTEN SIE: `unzip` ist in den Standard-httpd-Images NICHT installiert und benötigt ROOT-Rechte.
# Um dies zu tun, müssten Sie ein benutzerdefiniertes Dockerfile erstellen, das `unzip` installiert.
# echo "--- Versuch, app.zip zu entpacken (falls vorhanden) ---"
# if [ -f /var/www/html/app.zip ]; then
#     echo "app.zip gefunden. Entpacke..."
#     # Dies würde fehlschlagen, da unzip nicht installiert ist oder root-Rechte fehlen könnten.
#     # apt-get update && apt-get install -y unzip
#     # unzip /var/www/html/app.zip -d /var/www/html/
#     echo "Inhalt nach 'Entpacken':"
#     ls -a /var/www/html/
# else
#     echo "app.zip nicht gefunden."
# fi

echo "--- Starte den Apache HTTPD-Dienst ---"
# Führt den ursprünglichen Apache HTTPD-Befehl aus, um den Server im Vordergrund zu starten
exec httpd -DFOREGROUND