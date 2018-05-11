#!/bin/bash

C_TOKEN="token-for-c"
H_L_TOKEN="token-for-hl"
H_R_TOKEN="token-for-hr"

PLEX_CLOUD_URL=$(wget -qO- "https://plex.tv/users/cpms?X-Plex-Token=$C_TOKEN" | grep -o -m 1 "user-string.*services")
if [[ -z  $PLEX_CLOUD_URL ]]; then
	~/sendSlackMessage error_logs plex "failed to find Plex Cloud URL"
fi
PLEX_C_URL="$PLEX_CLOUD_URL:80"

nas_ip=$(cat '/home/user/.ip_nas')
if [ "$nas_ip" == '' ]; then
	~/sendSlackMessage error_logs plex "failed to get NAS IP"
	exit 1;
fi
PERSONAL_C_URL="$nas_ip:32400"

exec 5>&1
date

# SYNC MOVIES
result=$(plex-sync $C_TOKEN@$PERSONAL_C_URL/1 $C_TOKEN@$PLEX_C_URL/1 $H_L_TOKEN@$PERSONAL_C_URL/1 $H_R_TOKEN@$PLEX_C_URL/1 |& tee -a /dev/fd/5)
if [[ ! $result =~ .*Sync\ completed.* ]]; then
	~/sendSlackMessage error_logs plex "failed to sync Movies"
fi

# SYNC TV SHOWS
result=$(plex-sync $C_TOKEN@$PERSONAL_C_URL/2 $C_TOKEN@$PLEX_C_URL/6 $H_L_TOKEN@$PERSONAL_C_URL/2 $H_R_TOKEN@$PLEX_C_URL/6 |& tee -a /dev/fd/5)
if [[ ! $result =~ .*Sync\ completed.* ]]; then
	~/sendSlackMessage error_logs plex "failed to sync TV Shows"
fi