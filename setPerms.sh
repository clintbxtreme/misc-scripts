#!/bin/bash

date

if [[ -d "$1" ]]; then
	DIR=$1
elif id username >/dev/null 2>&1; then
	DIR='/home/username/scripts/'
elif id home-user >/dev/null 2>&1; then
	DIR='/home/user/Documents/'
fi

if [ -z $DIR ]; then
	echo "***** no directory set"
	exit 1
fi

echo "***** The Directory is $DIR"
if id username >/dev/null 2>&1; then
	echo "***** chown username:admn"
	find $DIR ! \( -user username -group admn \) -exec ls -lah {} \; -exec chown username:admn {} \;
elif id home-user >/dev/null 2>&1; then
	echo "***** chown home-user:admin"
	find $DIR ! \( -user home-user -group admin \) -exec ls -lah {} \; -exec chown home-user:admin {} \;
fi

echo "***** all directories"
find $DIR -type d ! -perm u=rwx,g=rwxs -exec ls -lahd {} \; -exec chmod u+rwx,g+rwxs,o-rwx {} \;
echo "***** for .sh files"
find $DIR -type f -name "*.sh" ! -name "*.data" ! -path "*/backups/*" ! -perm u+rwx,g+rx-w,o-rw+x -exec ls -lah {} \; -exec chmod u+rwx,g+rx-w,o-rw+x {} \;
echo "***** for non .sh files"
find $DIR -type f ! -name "*.sh" ! -name "*.data" ! -path "*/backups/*" ! -perm u+rw-x,g+r-wx,o-rwx -exec ls -lah {} \; -exec chmod u+rw-x,g+r-wx,o-rwx {} \;
