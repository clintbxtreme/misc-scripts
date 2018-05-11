#!/bin/bash

if [ "$EUID" -ne 0 ]; then
	echo "Please run as root"
	exit 7
fi

mkdir -p /shares/.ssh
chmod 700 /shares/.ssh

echo "
ssh-rsa rsa hash here user@user
" > /shares/.ssh/authorized_keys

chmod 600 /shares/.ssh/authorized_keys
chown username:users /shares
chmod 750 /shares

echo "RSAAuthentication yes
PubkeyAuthentication yes
PermitEmptyPasswords no
ChallengeResponseAuthentication no
PasswordAuthentication no
PubkeyAcceptedKeyTypes=+ssh-dss
UsePAM no" >> /etc/ssh/sshd_config

killall -HUP sshd