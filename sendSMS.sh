#!/bin/bash

url="http://textbelt.com/text";
result=$(curl POST $url --silent -d "message=$2" -d "number=$1");
