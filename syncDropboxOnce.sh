#!/bin/bash

date
cd "$HOME"
touch "/tmp/keepAlive-disable"
echo "added keepAlive-disable"
sudo systemctl stop my.service
echo "stopped my"
dropbox.py start &> /dev/null
echo "started dropbox"
STATUS=''
PREV_STATUS=''
while [[ "$STATUS" != "Up to date" ]]; do
	STATUS=$(dropbox.py status)
	if [[ "$STATUS" != "$PREV_STATUS" ]]; then
		echo "dropbox status: $STATUS";
		PREV_STATUS="$STATUS"
	fi
	sleep 5;
done
dropbox.py stop
echo "stopped dropbox"
rm "/tmp/keepAlive-disable"
echo "removed keepAlive-disable"
sudo systemctl start my.service
echo "started my"
