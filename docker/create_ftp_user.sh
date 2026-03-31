#!/bin/sh
set -e

USERNAME="$1"
PASSWORD="$2"

PASSWD_FILE="/etc/pureftpd/pureftpd.passwd"
DB_FILE="/etc/pureftpd/pureftpd.pdb"
HOME_DIR="/home/ftpusers/$USERNAME"

if [ -z "$USERNAME" ] || [ -z "$PASSWORD" ]; then
    echo "Chyba: chybi username nebo password"
    exit 1
fi

mkdir -p /etc/pureftpd
mkdir -p /home/ftpusers
mkdir -p "$HOME_DIR"

if [ ! -f "$PASSWD_FILE" ]; then
    touch "$PASSWD_FILE"
fi

if pure-pw show "$USERNAME" -f "$PASSWD_FILE" >/dev/null 2>&1; then
    echo "FTP uzivatel $USERNAME uz existuje"
    exit 0
fi

echo "Vytvarim FTP uzivatele $USERNAME s home $HOME_DIR"

pure-pw useradd "$USERNAME" \
    -f "$PASSWD_FILE" \
    -u 1000 \
    -g 1000 \
    -d "$HOME_DIR" <<EOF
$PASSWORD
$PASSWORD
EOF

pure-pw mkdb "$DB_FILE" -f "$PASSWD_FILE"

echo "FTP uzivatel $USERNAME byl vytvoren"