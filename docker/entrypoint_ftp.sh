#!/bin/sh
set -e

PASSWD_FILE="/etc/pureftpd/pureftpd.passwd"
DB_FILE="/etc/pureftpd/pureftpd.pdb"

if [ ! -f "$DB_FILE" ]; then
    printf "${FTP_ADMIN_PASSWORD}\n${FTP_ADMIN_PASSWORD}\n" | \
    pure-pw useradd "${FTP_ADMIN_USERNAME}" -f "$PASSWD_FILE" -u 1000 -g 1000 -d "${FTP_ADMIN_HOME}"
    
    pure-pw mkdb "$DB_FILE" -f "$PASSWD_FILE"
fi

exec /usr/sbin/pure-ftpd \
    -P "$PUBLIC_HOST" \
    -p "$MIN_PASV_PORT:$MAX_PASV_PORT" \
    -l puredb:/etc/pureftpd/pureftpd.pdb \
    -E \
    -j \
    -R