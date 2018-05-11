#!/bin/bash

icon_urls=(
	"noip:::http://www.raspberrypihelp.net/plaatjes/noip.jpg"
	"node:::http://nodejs-cloud.com/img/128px/nodejs.png"
	"web:::http://png-3.findicons.com/files/icons/115/pulse_pack/512/web_v3.png"
	"mysql:::http://orig15.deviantart.net/d72a/f/2010/175/7/4/simple_mysql_icon_by_xaleph.png"
)

icon_url=""
for index in "${icon_urls[@]}" ; do
    key="${index%%:::*}"
    value="${index##*:::}"
	if [ "$key" == "$2" ]; then
		icon_url=$value
	fi
done
text="${3//&/and}"
text="${text//\\[/[}"
text="${text//\\]/]}"
text="${text//
/\\n}"
data="{\"text\":\"$text\",\"channel\":\"#$1\",\"username\":\"$2\",\"icon_url\":\"$icon_url\",\"mrkdwn\":true}"
curl -X POST -H 'Content-type: application/json' --data "$data" https://hooks.slack.com/services/rest-of-url >/dev/null 2>&1

# slackChannel="&channel=#$1"
# slackUsername="&username=$2"
# slackText="&text=$text"
# slackIcon="&icon_url=$icon_url"
# slackToken="slack-token"
# slackCurlUrl="https://slack.com/api/chat.postMessage"
# slackCurlParams="$slackToken$slackUsername$slackText$slackChannel$slackIcon"
# curl --silent -d "$slackCurlParams"  $slackCurlUrl -k >/dev/null 2>&1

