#!/bin/bash

LOCAL="192.168.1.11"
REMOTE="my-server.com"

HOST=$REMOTE
if ping -q -c1 -W300 $LOCAL &> /dev/null
	then
	HOST=$LOCAL
fi

COUNTER=0
while [  $COUNTER -lt 100 ]; do
	rsync /Users/my-username/Pictures/ "home-user@$HOST:/home/user/Pictures/By\ Date" --rsh ssh --delete --exclude=.DS_Store -avh
	EXIT_CODE="$?"
	if [ "$EXIT_CODE" != "255" ]; then
		break
	fi
    let COUNTER=COUNTER+1
	echo "retry #: $COUNTER"
done
if [ "$EXIT_CODE" != "0" ]; then
	MESSAGE="Failed rsync from laptop with error code of $EXIT_CODE"
	CHANNEL="error_logs"
else
	MESSAGE="Successful rsync from laptop with a retry count of $COUNTER"
	CHANNEL="backups"
fi
curl --silent -X POST --data-urlencode "payload={\"text\": \"$MESSAGE\",\"channel\":\"#$CHANNEL\"}" https://hooks.slack.com/services/rest-of-url > /dev/null 2>&1