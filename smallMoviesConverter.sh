#!/bin/bash

scriptname=$(basename "$0")
scriptname="${scriptname%.*}"

for fullfile in ~/Movies/*.mp4; do
	if [[ $fullfile == *"-small"* ]]; then
	  continue
	fi
	directory=$(dirname "$fullfile")
	filename=$(basename "$fullfile")
	filename="${filename%.*}"
	# extension="${fullfile##*.}"
	newfullfile="${fullfile%.*}.mp4"
	newfullfile="$directory/$filename-small.mp4"
	if [ -s "$newfullfile" ]; then
		continue
	fi
	ffmpeg -y -i "$fullfile" -vcodec mpeg4 -qscale:v 10 -vf scale=800:-1 "$newfullfile" &>> "$HOME/.tmp/$scriptname.log"

	if [ "$?" -eq "0" ]; then
		slackText="$filename has been converted"
	else
		slackText="$filename did not convert successfully"
		rm "$newfullfile"
	fi

	"~/sendSlackMessage.sh" misc server "$slackText"
done