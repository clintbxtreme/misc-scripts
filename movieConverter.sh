#!/bin/bash

scriptname=$(basename $0)
scriptname="${scriptname%.*}"
fullfile="$1"
filename=$(basename "$fullfile")
filename="${filename%.*}"
extension="${fullfile##*.}"
newfullfile="${fullfile%.*}.mp4"

if [ -s "$newfullfile" ]; then
	exit 99
fi

if [ "$extension" == "mkv" ]; then
	echo "do you need to convert an mkv?"
	# nice -n 19 ffmpeg -i "$fullfile" -vcodec copy -acodec copy "$newfullfile" >>/var/log/custom/$scriptname/$scriptname.log 2>>/var/log/custom/$scriptname/$scriptname.err
elif [ "$extension" == "avi" ]; then
	if [[ "$OSTYPE" == "linux-gnu"* ]]; then
		# for pi
		nice -n 19 ffmpeg -i "$fullfile" -vcodec libx264 -crf 20 -strict -2 "$newfullfile" >>/var/log/custom/$scriptname/$scriptname.log 2>>/var/log/custom/$scriptname/$scriptname.err
	elif [[ "$OSTYPE" == "darwin"* ]]; then
		# for mac
		nice -n 19 ~/HandBrakeCLI -i "$fullfile" -o "$newfullfile" -e x264 -O -q 20 >>/tmp/$scriptname.log 2>>/tmp/$scriptname.err
	fi
fi

if [ "$?" -eq "0" ]; then
	slackText="$filename has been converted"
	# rm "$fullfile"
else
	slackText="$filename did not convert successfully"
	rm "$newfullfile"
fi

~/sendSlackMessage misc HandBrakeCLI "$slackText"