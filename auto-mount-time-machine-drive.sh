#!/bin/bash

# check if script already running
PROCESS=$(basename $0)
number=$(ps aux| grep -v grep | grep $PROCESS | wc -l)
if [ $number -gt 2 ]; then
	echo "TOO MANY PROCESSES"
	exit 99
fi

# check if server is accessible
PING=$(/sbin/ping -q -c 5 -t 5 example.com)
PING_SUCCESS="$?"
ONLINE=$(echo "$PING" | grep '100.0% packet loss')
if [ "$ONLINE" != "" ] || [ "$PING_SUCCESS" -ne "0" ]; then
	echo "HOST NOT FOUND"
	exit 98
fi

# good to mount
MOUNT_POINT="/Volumes/TimeMachine"
SERVER_MOUNT=~/.server
SERVER_MOUNTED=$(/sbin/mount | grep "on $SERVER_MOUNT")
if [ "$SERVER_MOUNTED" == "" ]; then
	echo "MOUNTING ===== $SERVER_MOUNT"
	$(/usr/local/bin/sshfs -o IdentityFile=~/.ssh/id_rsa user@example.com:/mac/ ~/.server)
fi
SERVER_MOUNTED=$(/sbin/mount | grep "on $SERVER_MOUNT")
MOUNTED=$(/sbin/mount | grep "on $MOUNT_POINT")
if [ "$MOUNTED" == "" ] && [ "$SERVER_MOUNTED" != "" ]; then
	echo "MOUNTING ===== $MOUNT_POINT"
	$(hdiutil attach -mountpoint $MOUNT_POINT/ ~/.server/TimeMachine.sparsebundle)
fi