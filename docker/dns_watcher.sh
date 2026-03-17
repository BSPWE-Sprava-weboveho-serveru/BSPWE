#!/bin/bash

FILE="/etc/dnsmasq.conf"

echo "Watcher started: Monitoring $FILE for changes..."

while true; do
    inotifywait -e modify -e close_write "$FILE"
    
    echo "Change detected in $FILE. Reloading Dnsmasq..."
    
    kill 1
done