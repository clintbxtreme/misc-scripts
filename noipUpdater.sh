#!/bin/bash

H=/home/user
URL="https://freedns.afraid.org/dynamic/update.php?key-here"
if [ "$1" == "cloud" ]; then
	H=/home/username
	URL="https://freedns.afraid.org/dynamic/update.php?key-here"
fi
STOREDIPFILE=$H/.current_ip
USERAGENT="Bash IP Updater"

exec 1>>/var/log/custom/noip/log 2> >(sel.sh)

if [ ! -e $STOREDIPFILE ]; then
	touch $STOREDIPFILE
fi

if ! ping -q -c 1 -W 1 google.com >/dev/null; then
	echo "[$(date +"%Y-%m-%d %H:%M:%S")] can't ping google"
	~/sendSlackMessage misc server "can't ping google"
	# ip addr flush enxb827ebf9ca75 && systemctl restart networking.service
	# echo "[$(date +"%Y-%m-%d %H:%M:%S")] restarted networking service"
	# ~/sendSlackMessage misc server "restarted networking service"
fi

NEWIP=$(wget -O - http://ipinfo.io/ip -o /dev/null)
STOREDIP=$(cat $STOREDIPFILE)

if [ "$NEWIP" != "" ] && [ "$NEWIP" != "$STOREDIP" ]; then
	RESULT=$(wget -qO - --user-agent="$USERAGENT" --no-check-certificate "$URL")

	echo "[$(date +"%Y-%m-%d %H:%M:%S")] $RESULT"
	~/sendSlackMessage misc afraid.org "$RESULT"
	echo $NEWIP > $STOREDIPFILE
else
	echo "[$(date +"%Y-%m-%d %H:%M:%S")] No IP change"
fi

if [ "$1" == "cloud" ]; then
	exit 0
fi

upnpc -e 'SSH on Raspberry Pi' -r 23 TCP > /dev/null
upnpc -e 'HTTP on Raspberry Pi' -r 8181 TCP > /dev/null
upnpc -e 'HTTPS on Raspberry Pi' -r 444 TCP > /dev/null
upnpc -e 'OpenVPN on Raspberry Pi' -r 1195 TCP 1195 UDP > /dev/null

SCAN=$(arp-scan --interface=enxb827ebf9ca75 --localnet)

CHANGED=''
camera_ip=$(echo "$SCAN" | grep "camara_mac" | awk '{print $1}')
if [ "$camera_ip" != "" ]; then
	upnpc -e 'camera' -a "$camera_ip" 80 8250 TCP > /dev/null
	camera_ip_stored_file=$H/.ip_camera
	camera_ip_stored=$(cat $camera_ip_stored_file)
	if [ "$camera_ip" != "$camera_ip_stored" ]; then
		CHANGED+="camera ip changed to $camera_ip\n"
		echo $camera_ip > $camera_ip_stored_file
	fi
fi

nas_ip=$(echo "$SCAN" | grep "nas_mac" | awk '{print $1}')
if [ "$nas_ip" != "" ]; then
	upnpc -e 'PLEX-NAS' -a "$nas_ip" 32400 31222 TCP > /dev/null
	upnpc -e 'SSH-NAS' -a "$nas_ip" 22 33333 TCP > /dev/null
	nas_ip_stored_file=$H/.ip_nas
	nas_ip_stored=$(cat $nas_ip_stored_file)
	if [ "$nas_ip" != "$nas_ip_stored" ]; then
		CHANGED+="NAS ip changed to $nas_ip\n"
		umount /nas
		sed  -i "s/$nas_ip_stored/$nas_ip/g" /etc/fstab
		mount /nas
		echo $nas_ip > $nas_ip_stored_file
	fi
fi

garage_ip=$(echo "$SCAN" | grep "garage_mac" | awk '{print $1}')
if [ "$garage_ip" != "" ]; then
	garage_ip_stored_file=$H/.ip_garage
	garage_ip_stored=$(cat $garage_ip_stored_file)
	if [ "$garage_ip" != "$garage_ip_stored" ]; then
		CHANGED+="garage ip changed to $garage_ip\n"
		echo $garage_ip > $garage_ip_stored_file
	fi
fi

if [ "$CHANGED" != "" ]; then
	~/sendSlackMessage error_logs server "$CHANGED"
fi

exit 0
