#!/bin/sh

# Cesta k adresáři s FTP uživateli
FTP_BASE="/home/ftpusers"

REQUEST_FILE="$1"

if [ ! -f "$REQUEST_FILE" ]; then
    echo "Soubor $REQUEST_FILE neexistuje"
    exit 1
fi

# Parsování JSON
USERNAME=$(sed -n 's/.*"username"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")
DOMAIN=$(sed -n 's/.*"domain"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")
REMOTE_PATH=$(sed -n 's/.*"remote_path"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")
CONTENT_B64=$(sed -n 's/.*"content_base64"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$REQUEST_FILE")

if [ -z "$USERNAME" ] || [ -z "$DOMAIN" ] || [ -z "$REMOTE_PATH" ] || [ -z "$CONTENT_B64" ]; then
    echo "Neplatný request: chybí některá pole"
    mv "$REQUEST_FILE" "$REQUEST_FILE.invalid"
    exit 1
fi

# Cílová cesta
TARGET_DIR="$FTP_BASE/$USERNAME/$DOMAIN"
TARGET_FILE="$TARGET_DIR/$REMOTE_PATH"

# Bezpečnostní kontrola – zakázat cestu obsahující ".."
if [[ "$REMOTE_PATH" == *".."* ]]; then
    echo "Pokus o únik z adresáře: $REMOTE_PATH"
    mv "$REQUEST_FILE" "$REQUEST_FILE.invalid"
    exit 1
fi

# Vytvoření adresářové struktury (včetně podadresářů)
mkdir -p "$(dirname "$TARGET_FILE")"

# Dekódování base64 a uložení
echo "$CONTENT_B64" | base64 -d > "$TARGET_FILE"

if [ $? -eq 0 ]; then
    echo "Soubor úspěšně nahrán: $TARGET_FILE"
    rm -f "$REQUEST_FILE"
else
    echo "Chyba při ukládání souboru $TARGET_FILE"
    mv "$REQUEST_FILE" "$REQUEST_FILE.failed"
    exit 1
fi