#!/bin/sh

FTP_BASE="/home/ftpusers"

REQUEST_FILE="$1"

if [ ! -f "$REQUEST_FILE" ]; then
    echo "Soubor $REQUEST_FILE neexistuje"
    exit 1
fi

USERNAME=$(sed -n 's/.*"username"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")
DOMAIN=$(sed -n 's/.*"domain"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")
REMOTE_PATH=$(sed -n 's/.*"remote_path"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")

if [ -z "$USERNAME" ] || [ -z "$DOMAIN" ] || [ -z "$REMOTE_PATH" ]; then
    echo "Neplatný request: chybí některá pole"
    mv "$REQUEST_FILE" "$REQUEST_FILE.invalid"
    exit 1
fi

# Bezpečnostní kontrola – zakázat cestu obsahující ".."
if [[ "$REMOTE_PATH" == *".."* ]]; then
    echo "Pokus o únik z adresáře: $REMOTE_PATH"
    mv "$REQUEST_FILE" "$REQUEST_FILE.invalid"
    exit 1
fi

TARGET_FILE="$FTP_BASE/$USERNAME/$DOMAIN/$REMOTE_PATH"

if [ -f "$TARGET_FILE" ]; then
    rm -f "$TARGET_FILE"
    if [ $? -eq 0 ]; then
        echo "Soubor smazán: $TARGET_FILE"
        rm -f "$REQUEST_FILE"
    else
        echo "Chyba při mazání $TARGET_FILE"
        mv "$REQUEST_FILE" "$REQUEST_FILE.failed"
        exit 1
    fi
else
    echo "Soubor neexistuje: $TARGET_FILE"
    rm -f "$REQUEST_FILE"   # Soubor neexistuje, request je neplatný – smažeme ho
fi