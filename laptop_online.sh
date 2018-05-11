#!/bin/bash

# check if my laptop is on is accessible
PING_FILE="/tmp/laptop_on"
PING=$(ping -q -c 3 -t 3 192.168.1.12)
PING_SUCCESS="$?"
ONLINE=$(echo "$PING" | grep '100% packet loss')
if [ "$ONLINE" != "" ] || [ "$PING_SUCCESS" -ne "0" ]; then
	if [ -f "$PING_FILE" ]; then
		rm "$PING_FILE"
	fi
elif [ ! -f "$PING_FILE" ]; then
	touch "$PING_FILE"
	~/sendSlackMessage random server "My laptop is on"
fi