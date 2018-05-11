#!/bin/bash
PIDS=$(pidof -o %PPID -x basename "$0")
PID_COUNT=$(echo "$PIDS" | wc -w)
RE_RUN='/tmp/postProcessVideos_rerun'
if [ "$PID_COUNT" -ge "1" ]; then
	if [ -f "$RE_RUN" ]; then
		for kind in "$@"; do
			if ! grep -q "$kind" "$RE_RUN"; then
				echo "$kind ">>"$RE_RUN"
			fi
		done
	else
		echo "$@ ">>"$RE_RUN"
	fi
    exit 0
else
	exec &>>/var/log/custom/postProcessVideos/log

	~/rcloneBackups.sh v

	for kind in "$@"
	do
		lib='1'
		loc_lib='1'
		if [ "$kind" == "tv" ]; then
			lib='6'
			loc_lib='2'
		fi

		echo "----------UPDATING PLEX LIBRARIES----------"
		token='?X-Plex-Token=plex_token'

		PLEX_CLOUD_URL=$(wget -qO- "https://plex.tv/users/cpms$token" | grep -o -m 1 "user_token.*services")
		if [[ -z  $PLEX_CLOUD_URL ]]; then
			~/sendSlackMessage error_logs plex "failed to find Plex Cloud URL"
		fi

		nas_ip=$(cat '/home/user/.ip_nas')
		if [ "$nas_ip" == '' ]; then
			~/sendSlackMessage error_logs plex "failed to get NAS IP"
			exit 1;
		fi

		# empty trash
		wget --method=PUT -qO- "$nas_ip:32400/library/sections/6/emptyTrash$token" &> /dev/null

		wget -qO- "https://$PLEX_CLOUD_URL/library/sections/$lib/refresh$token" &> /dev/null
		if [ "$?" -ne "0" ]; then
			~/sendSlackMessage error_logs plex "failed to update Plex Cloud"
		fi
		wget -qO- "$nas_ip:32400/library/sections/$loc_lib/refresh$token" &> /dev/null
		if [ "$?" -ne "0" ]; then
			~/sendSlackMessage error_logs plex "failed to update PersonalCloud"
		fi
	done
	if [ -f "$RE_RUN" ]; then
    	ARGS=$(cat "$RE_RUN")
    	rm $RE_RUN
    	exec $0 $ARGS
	fi
fi