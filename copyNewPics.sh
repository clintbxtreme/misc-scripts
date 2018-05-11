#!/bin/bash

scriptname=$(basename "$0")
number=$(ps aux | grep $scriptname  | grep -v grep | wc -l)

if [ $number -gt 2 ]
    then
        echo "Already Running";
        exit 99;
fi
search_dir="/Users/example_user/Pictures/Eyefi"
to_dir="/Users/example_user/Pictures/from_canon_camera"
pics=$(find "$search_dir" -type f  \( -iname \*.jpeg -o -iname \*.jpg -o -iname \*.mov -o -iname \*.cr2 \))

# 5m8s
# existing=$(<~/.coppiedPics.txt)
# if [ "$existing" == "" ]; then
# 	existing_pics=$(find "$to_dir" -type f  \( -iname \*.jpeg -o -iname \*.jpg -o -iname \*.mov -o -iname \*.cr2 \))
# 	for existing_pic in $existing_pics
# 	do
# 		$(shasum -a 256 "$existing_pic" | grep -o "^\w*\b" >> ~/.coppiedPics.txt)
# 	done
# fi

for pic in $pics
do
# sha=$(shasum -a 256 "$pic" | grep -o "^\w*\b")
# if [[ $existing == *"$sha"* ]]; then
# 	echo "skipping $pic"
# 	continue
# fi
pic_name=$(basename "$pic")
# 13m4s
# camera_model=$(exiftool -f -s3 -"Model" "$pic")
# if [ "$camera_model" != "Canon EOS REBEL T3i" ]; then
# 	rm "$pic"
# 	echo "$pic_name is from $camera_model, skipping"
# 	continue
# fi
timestamp=$(exiftool -f -s3 -"DateTimeOriginal" -d %F "$pic")
if [ "$timestamp" == '-' ]; then
    timestamp=$(exiftool -f -s3 -"MediaCreateDate" -d %F "$pic")
fi
move_dir="$to_dir/$timestamp"
if [ ! -s "$move_dir/$pic_name" ]; then
	echo "Copying $timestamp/$pic_name"
	result=$(mkdir -p "$move_dir" && cp "$pic" "$_")
	if [ "$?" -ne "0" ]; then
		echo "error copying $pic_name, Message: $result"
		continue
	fi
fi
# $(shasum -a 256 "$pic" | grep -o "^\w*\b" | cat >> ~/.coppiedPics.txt)
done