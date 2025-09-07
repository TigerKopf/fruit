#!/bin/bash

# Die Zieldatei, in die alles geschrieben wird
OUTPUT_FILE="alles_in_einer_datei.txt"

# Eine Liste der zu verarbeitenden Verzeichnisse und Dateien
TARGETS=(
    "src/assets/style"
    "src/api"
    "src/include"
    "src/modules"
    "src/partials"
    "src/templates"
    "src/index.php"
    "src/.htaccess"
    "Dockerfile"
    "composer.json"
)

# NEU: Eine Liste von Mustern (Dateien oder Verzeichnisse), die ignoriert werden sollen.
# Beispiele für Muster:
# "src/api/test_data.json"   -> Schließt eine spezifische Datei aus.
# "src/modules/vendor"       -> Schließt das Verzeichnis 'vendor' und alle seine Inhalte aus.
# "*.log"                    -> Schließt alle Dateien aus, die auf '.log' enden.
# "temp_*.php"               -> Schließt Dateien aus, die mit 'temp_' beginnen und auf '.php' enden.
EXCLUDE_PATTERNS=(
    # Füge hier deine auszuschließenden Muster hinzu
    # "src/api/example_excluded_file.json"
    # "src/modules/node_modules"
    # "*.backup"
    "src/assets/style/archive__styles.css"
)

# Funktion, um zu prüfen, ob ein Pfad (Datei oder Verzeichnis) ausgeschlossen werden soll.
# Argument 1: Der zu prüfende Pfad
is_excluded() {
    local path="$1"
    for pattern in "${EXCLUDE_PATTERNS[@]}"; do
        # Prüft, ob der Pfad genau dem Muster entspricht oder ein Glob-Muster ist (z.B. "*.log")
        if [[ "$path" == $pattern ]]; then
            return 0 # 0 bedeutet "wird ausgeschlossen"
        fi
        # Prüft, ob der Pfad ein Kind eines ausgeschlossenen Verzeichnisses ist
        # Beispiel: pattern="src/modules/vendor", path="src/modules/vendor/file.js"
        if [[ "$path" == "$pattern"/* ]]; then
            return 0 # 0 bedeutet "wird ausgeschlossen"
        fi
    done
    return 1 # 1 bedeutet "wird NICHT ausgeschlossen"
}

# Die Ausgabedatei zu Beginn leeren
> "$OUTPUT_FILE"

# Durchlaufe alle Ziele in der Liste
for TARGET in "${TARGETS[@]}"; do
    # Überprüfe, ob das Ziel (Verzeichnis oder Datei) selbst ausgeschlossen werden soll
    if is_excluded "$TARGET"; then
        echo "##################################################" >> "$OUTPUT_FILE"
        echo "INFO: '$TARGET' wurde aufgrund der Ausschlussliste übersprungen." >> "$OUTPUT_FILE"
        echo "##################################################" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
        continue # Springe zum nächsten TARGET
    fi

    # Überprüfe, ob das Ziel existiert
    if [ ! -e "$TARGET" ]; then
        echo "##################################################" >> "$OUTPUT_FILE"
        echo "WARNUNG: '$TARGET' wurde nicht gefunden und wird übersprungen." >> "$OUTPUT_FILE"
        echo "##################################################" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
        continue
    fi

    # Überprüfe, ob es sich um ein Verzeichnis handelt
    if [ -d "$TARGET" ]; then
        # Durchlaufe alle Dateien und Unterverzeichnisse im Verzeichnis (nicht rekursiv)
        for FILE in "$TARGET"/*; do
            # Überprüfe, ob die aktuelle Datei/Unterverzeichnis ausgeschlossen werden soll
            if is_excluded "$FILE"; then
                echo "##################################################" >> "$OUTPUT_FILE"
                echo "INFO: '$FILE' wurde aufgrund der Ausschlussliste übersprungen." >> "$OUTPUT_FILE"
                echo "##################################################" >> "$OUTPUT_FILE"
                echo "" >> "$OUTPUT_FILE"
                continue # Springe zur nächsten FILE im Verzeichnis
            fi

            # Überprüfe, ob es sich um eine Datei handelt (und nicht um ein Unterverzeichnis)
            if [ -f "$FILE" ]; then
                echo "==================================================" >> "$OUTPUT_FILE"
                echo "DATEINAME: $FILE" >> "$OUTPUT_FILE"
                echo "--------------------------------------------------" >> "$OUTPUT_FILE"
                cat "$FILE" >> "$OUTPUT_FILE"
                echo "" >> "$OUTPUT_FILE"
                echo "" >> "$OUTPUT_FILE"
            fi
        done
    # Überprüfe, ob es sich um eine einzelne Datei handelt
    elif [ -f "$TARGET" ]; then
        # Die Datei wurde bereits am Anfang der Schleife auf Ausschluss geprüft
        echo "==================================================" >> "$OUTPUT_FILE"
        echo "DATEINAME: $TARGET" >> "$OUTPUT_FILE"
        echo "--------------------------------------------------" >> "$OUTPUT_FILE"
        cat "$TARGET" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
    fi
done

echo "Skript erfolgreich abgeschlossen. Der gesamte Inhalt (abzüglich ausgeschlossener Elemente) wurde in '$OUTPUT_FILE' geschrieben."