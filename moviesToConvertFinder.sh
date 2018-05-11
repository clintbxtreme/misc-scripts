#!/bin/bash

# scriptname=$(basename $0)
# lock="/var/run/${scriptname}"

# exec 333>"$lock"
# flock -w 180 333 || exit 1

# pid=$$
# echo $pid 1>&333

directory=$(pwd)
script=~/movieConverter.sh

find "$directory" -type f \( -iname \*.avi -o -iname \*.mkv \) -exec $script {} \;