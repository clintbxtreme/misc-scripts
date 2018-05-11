#!/bin/bash

HOME="/home/user"
OUTPUT="$HOME/Documents/mysqlbak"

mkdir -p "$OUTPUT"

#rm "$OUTPUTDIR/*gz" > /dev/null 2>&1

ExcludeDatabases="Database|information_schema|performance_schema|mysql"

databases=$($HOME/private/mysql/bin/mysql --socket=$HOME/private/mysql/socket -e "SHOW DATABASES;" | tr -d "| " | egrep -v $ExcludeDatabases)

for db in $databases; do
	FILE=$OUTPUT/$(date +%Y%m%d)."$db".sql
	echo "Dumping database: $db"
	$HOME/private/mysql/bin/mysqldump --socket=$HOME/private/mysql/socket --databases "$db" > "$FILE"
	gzip -f "$FILE"
done
chmod -R go-rwx "$OUTPUT"
rm -f $OUTPUT/$(date -d '-7day' +%Y%m%d)*
