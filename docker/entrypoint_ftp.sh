#!/bin/sh
set -e

PASSWD_FILE="/etc/pureftpd/pureftpd.passwd"
DB_FILE="/etc/pureftpd/pureftpd.pdb"

PUBLICHOST="${PUBLICHOST:-127.0.0.1}"
MIN_PASV_PORT="${MIN_PASV_PORT:-30000}"
MAX_PASV_PORT="${MAX_PASV_PORT:-30009}"

FTP_ADMIN_USERNAME="${FTP_ADMIN_USERNAME:-admin}"
FTP_ADMIN_PASSWORD="${FTP_ADMIN_PASSWORD:-adminpass}"
FTP_ADMIN_HOME="${FTP_ADMIN_HOME:-/home/admin}"

mkdir -p /etc/pureftpd
mkdir -p /ftp-requests
mkdir -p /home/ftpusers
mkdir -p "$FTP_ADMIN_HOME"

if [ ! -f "$PASSWD_FILE" ]; then
    touch "$PASSWD_FILE"
fi

if [ ! -f "$DB_FILE" ]; then
    pure-pw mkdb "$DB_FILE" -f "$PASSWD_FILE" || true
fi

echo "Kontroluji admin FTP účet..."

if ! pure-pw show "$FTP_ADMIN_USERNAME" -f "$PASSWD_FILE" >/dev/null 2>&1; then
    pure-pw useradd "$FTP_ADMIN_USERNAME" \
        -f "$PASSWD_FILE" \
        -u 1000 \
        -g 1000 \
        -d "$FTP_ADMIN_HOME" <<EOF
$FTP_ADMIN_PASSWORD
$FTP_ADMIN_PASSWORD
EOF

    pure-pw mkdb "$DB_FILE" -f "$PASSWD_FILE"
    echo "Admin FTP účet vytvořen"
else
    echo "Admin FTP účet už existuje"
fi

process_requests() {
    while true; do
        for file in /ftp-requests/*.json; do
            [ -e "$file" ] || {
                sleep 2
                continue
            }

            TYPE=$(sed -n 's/.*"type"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$file")
            USERNAME=$(sed -n 's/.*"username"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$file")
            PASSWORD=$(sed -n 's/.*"ftp_password_plain"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$file")

            if [ -z "$TYPE" ] || [ -z "$USERNAME" ]; then
                echo "Neplatny request soubor: $file"
                mv "$file" "$file.invalid"
                continue
            fi

            if [ "$TYPE" = "create_user" ]; then
                if [ -n "$PASSWORD" ]; then
                    echo "Zpracovavam vytvoreni FTP uzivatele: $USERNAME"
                    /create_ftp_user.sh "$USERNAME" "$PASSWORD"
                    rm -f "$file"
                else
                    echo "Request create_user nema heslo: $file"
                    mv "$file" "$file.invalid"
                fi
            elif [ "$TYPE" = "reset_password" ]; then
                if [ -n "$PASSWORD" ]; then
                    echo "Zpracovavam reset FTP hesla pro: $USERNAME"

                    if pure-pw show "$USERNAME" -f "$PASSWD_FILE" >/dev/null 2>&1; then
                        pure-pw passwd "$USERNAME" -m -f "$PASSWD_FILE" <<EOF
$PASSWORD
$PASSWORD
EOF
                        pure-pw mkdb "$DB_FILE" -f "$PASSWD_FILE"
                        rm -f "$file"
                    else
                        echo "Uzivatel $USERNAME ve FTP neexistuje, vytvarim ho nove"
                        /create_ftp_user.sh "$USERNAME" "$PASSWORD"
                        rm -f "$file"
                    fi
                else
                    echo "Request reset_password nema heslo: $file"
                    mv "$file" "$file.invalid"
                fi
            else
                echo "Neznamy typ requestu: $TYPE"
                mv "$file" "$file.invalid"
            fi
        done
        sleep 2
    done
}

process_requests &

exec /usr/sbin/pure-ftpd \
    -P "$PUBLICHOST" \
    -p "$MIN_PASV_PORT:$MAX_PASV_PORT" \
    -l puredb:"$DB_FILE" \
    -E \
    -j \
    -R