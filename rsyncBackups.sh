#!/bin/bash

~/sendSlackMessage backups rsync "rsync backup started"
DEST="/shares/USB/MyMedia"
RSYNC_CMD="rsync -avPh --delete-before --exclude *.nas_os* --exclude *.nasos* /shares/username/ $DEST"
COMMAND="test -d $DEST && /usr/bin/flock -xn /tmp/rsyncUSB.lock -c '$RSYNC_CMD'"

nas_ip=$(cat '/home/user/.ip_nas')
if [ "$nas_ip" == '' ]; then
	~/sendSlackMessage backups rsync "<!channel> rsync failed to find NAS IP"
	exit 1;
fi

ssh username@$nas_ip "$COMMAND"
if [ "$?" -eq "0" ]; then
	~/sendSlackMessage backups rsync "rsync backup finished"
else
	~/sendSlackMessage backups rsync "<!channel> rsync backup failed"
fi

# don't use anymore
exit 0
HOME="/mount/media/"
REMOTE="user@my-server.com:~"
BW_LIMIT='--bwlimit=150'
if [ "$1" == "all" ]; then
	~/sendSlackMessage.sh backups rsync "feral rsync backups started"

	/usr/bin/flock -xn $HOME/.tmp/rsync_docs.lock -c "rsync -rltzvPh --delete --update $HOME/Documents/ $REMOTE/Documents"
	/usr/bin/flock -xn $HOME/.tmp/rsync_docs.lock -c "rsync --chmod=go-rwx -rltzvPh --delete $BW_LIMIT --update $REMOTE/Documents/ $HOME/Documents"
	/usr/bin/flock -xn $HOME/.tmp/rsync_music.lock -c "rsync --chmod=go-rwx -rltzvPh $BW_LIMIT $REMOTE/Music/ $HOME/Music"
	/usr/bin/flock -xn $HOME/.tmp/rsync_pics.lock -c "rsync --chmod=go-rwx -rltzvPh $BW_LIMIT $REMOTE/Pictures/ $HOME/Pictures"
	/usr/bin/flock -xn $HOME/.tmp/rsync_vids.lock -c "rsync --chmod=go-rwx -rltzvPh $BW_LIMIT $REMOTE/Videos/Home\\\\\ Videos/ $HOME/Videos/Home\ Videos"

	~/sendSlackMessage.sh backups rsync "feral rsync backups ended"
fi
~/sendSlackMessage.sh backups rsync "Video rsync started"

/usr/bin/flock -xn $HOME/.tmp/rsync_movies.lock -c "rsync -rlzvPh --size-only $HOME/Videos/Movies/ $REMOTE/Videos/Movies"
/usr/bin/flock -xn $HOME/.tmp/rsync_tv.lock -c "rsync -rlzvPh --size-only $HOME/Videos/TV\ Shows/ $REMOTE/Videos/TV\\\\\ Shows"

~/sendSlackMessage.sh backups rsync "Video rsync ended"