#!/bin/bash

box_dir=~/Box/example.gmail.com
mount $box_dir
mounted=$(mount | grep "$box_dir")
if [ "$mounted" != "" ]; then
	~/sendSlackMessage backups rsync "backup to box(example.gmail) started"
	paths=("Documents" "Pictures")
	for i in "${paths[@]}"; do
		rsync -rtDPh --size-only --max-size=250mb --exclude="*.git*" --delete "~/$i/" "$box_dir/$i"
	done
	~/sendSlackMessage backups rsync "backup to box(example.gmail) ended"
	umount $box_dir
fi

box_dir=~/Box/example.yahoo.com
mount $box_dir
mounted=$(mount | grep "$box_dir")
if [ "$mounted" != "" ]; then
	~/sendSlackMessage backups rsync "backup to box(example.yahoo) started"
	paths=("Music" "Other Music")
	for i in "${paths[@]}"; do
		rsync -rtDPh --size-only --max-size=250mb --exclude="*.git*" --delete "~/Music/$i/" "$box_dir/$i"
	done
	~/sendSlackMessage backups rsync "backup to box(example.yahoo) ended"
	umount $box_dir
fi

box_dir=~/Box/example.live.com
mount $box_dir
mounted=$(mount | grep "$box_dir")
if [ "$mounted" != "" ]; then
	~/sendSlackMessage backups rsync "backup to box(example.live) started"
	rsync -rtDPh --size-only --max-size=250mb --exclude="*.git*" --delete "~/Videos/" "$box_dir/Videos"
	~/sendSlackMessage backups rsync "backup to box(example.live) ended"
	umount $box_dir
fi

box_dir=~/Box/example2.gmail.com
mount $box_dir
mounted=$(mount | grep "$box_dir")
if [ "$mounted" != "" ]; then
	~/sendSlackMessage backups rsync "backup to box(example2.gmail) started"
	rsync -rtDPh --size-only --max-size=250mb --exclude="*.git*" --delete "~/Pictures/Other Pictures/" "$box_dir/Other Pictures"
	~/sendSlackMessage backups rsync "backup to box(example2.gmail) ended"
	umount $box_dir
fi