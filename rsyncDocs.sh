#!/bin/bash

nas_ip=$(cat '/home/user/.ip_nas')
if [ "$nas_ip" == '' ]; then
	>&2 echo "rsyncDocs failed to get nas ip"
	exit 1;
fi

/usr/bin/flock -xn /locks/rsyncDocs.lock -c "rsync --size-only -rlzvPh --delete --exclude *.nas_os* --exclude *.nasos* /home/user/Documents/ username@$nas_ip:~/username/Media/Documents"

if [ "$?" -eq "0" ]; then
	echo "rsyncDocs success"
else
	>&2 echo "rsyncDocs failed"
fi