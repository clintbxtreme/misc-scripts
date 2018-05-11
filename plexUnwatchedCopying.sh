#!/bin/bash
HOME=/home/user
IFS=$'\n';

isPlexDown=$(curl --silent "http://127.0.0.1:32400" | grep "myPlexUsername")

if [ -z "$isPlexDown" ]; then
	~/sendSlackMessage misc rsync "plex is down!!"
	exit 5
fi

movieFiles=$(curl --silent "http://127.0.0.1:32400/library/sections/1/unwatched" | grep "file=" | grep -v "<Media" | sed 's_.*/home/user/Videos/__' | sed 's/" size.*//' | recode html..ascii | sed "s/&apos;/'/" | sed 's|\[\([^]]*\)\]|\\[\1\\]|g')
tvFiles=$(curl --silent "http://127.0.0.1:32400/library/sections/2/all?type=4&viewCount=0" | grep "file=" | grep -v "<Media" | sed 's_.*/home/user/Videos/__' | sed 's/" size.*//' | recode html..ascii | sed "s/&apos;/'/" | sed 's|\[\([^]]*\)\]|\\[\1\\]|g')

if [ -z "$tvFiles" ] && [ -z "$movieFiles" ]; then
	~/sendSlackMessage misc rsync "no video files!!"
	exit 5
fi

filesFrom=/tmp/unwatchedFiles.txt
rsyncLog=/tmp/rsync.log

rm $filesFrom
rm $rsyncLog

echo "$movieFiles" > $filesFrom
echo "$tvFiles" >> $filesFrom
rsync -rltDv --safe-links --delete --delete-excluded --force --human-readable --progress --prune-empty-dirs --size-only --include-from=/tmp/unwatchedFiles.txt --include="*/" --exclude="*" /home/user/Videos/ /mount/MOVIES > $rsyncLog
if [ "$?" -eq "0" ]; then
	~/sendSlackMessage misc rsync "movie sync successful"
else
	~/sendSlackMessage misc rsync "movie sync failed"
fi
