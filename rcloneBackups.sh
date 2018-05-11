#!/bin/bash

export RCLONE_CONFIG="/home/user/.rclone.conf"

EXC='--exclude .nas_os*/'

script=$(basename "$0" .sh)
exec &>>/var/log/custom/$script/log

function slack {
    ~/sendSlackMessage backups rclone "$1"
}

if [[ "$1" == "a" ]]; then
	slack "rclone backup started"
	/usr/bin/flock -xn /locks/rcloneBackups.lock -c "rclone $EXC --log-level INFO sync /mount/media/Media/ dropbox:server"
	if [ "$?" -eq "0" ]; then
		slack "rclone backup finished"
	else
		slack "<!channel> rclone backup failed"
	fi
fi
if [[ "$1" == "v" ]]; then
	slack "rclone video backup started"
	/usr/bin/flock -xn /locks/rcloneBackups.lock -c "rclone $EXC --delete-before --transfers 1 --log-level INFO sync /mount/media/Media/Videos/ dropbox:server/Videos"
	if [ "$?" -eq "0" ]; then
		slack "rclone video backup finished"
	else
		slack "<!channel> rclone video backup failed"
	fi
fi
if [[ "$1" == "p" ]]; then
	slack "rclone picture backup started"
	/usr/bin/flock -xn /locks/rcloneBackups.lock -c "rclone $EXC --log-level INFO sync /mount/media/Media/Pictures/ dropbox:server/Pictures"
	if [ "$?" -eq "0" ]; then
		slack "rclone picture backup finished"
	else
		slack "<!channel> rclone picture backup failed"
	fi
fi
if [[ "$1" == "d" ]]; then
	slack "rclone document backup started"
	/usr/bin/flock -xn /locks/rcloneBackups.lock -c "rclone $EXC --log-level INFO sync /mount/media/Media/Documents/ dropbox:server/Documents"
	if [ "$?" -eq "0" ]; then
		slack "rclone document backup finished"
	else
		slack "<!channel> rclone document backup failed"
	fi
fi
if [[ "$1" == "m" ]]; then
	slack "rclone music backup started"
	/usr/bin/flock -xn /locks/rcloneBackups.lock -c "rclone $EXC --log-level INFO sync /mount/media/Media/Music/ dropbox:server/Music"
	if [ "$?" -eq "0" ]; then
		slack "rclone music backup finished"
	else
		slack "<!channel> rclone music backup failed"
	fi
fi
