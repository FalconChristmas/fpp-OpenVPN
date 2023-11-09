#!/bin/bash

# fpp-OpenVPN install script

BASEDIR=$(dirname $0)
cd $BASEDIR
cd ..

apt-get update
apt-get -y install openvpn

# Allow openvpn to log to /home/fpp/media/logs
sed -i -e 's/ProtectHome=true/ProtectHome=false/' /lib/systemd/system/openvpn-server@.service
sed -i -e 's/ProtectHome=true/ProtectHome=false/' /lib/systemd/system/openvpn-client@.service
systemctl daemon-reload

cp scripts/openvpn-*.sh /home/fpp/media/scripts/
chown fpp.fpp /home/fpp/media/scripts/openvpn-*sh

