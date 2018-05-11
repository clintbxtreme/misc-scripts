#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

$DIR/sendSlackMessage.sh general server "entered work"

exit 0

curl https://server.com/setAtWork.php

if [ "$?" -ne "0" ]; then
	$DIR/sendSlackMessage.sh error_logs server "'setAtWork failed'"
fi