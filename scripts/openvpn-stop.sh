#!/usr/bin/bash

MODE=$(grep mode /home/fpp/media/config/plugin.fpp-OpenVPN | cut -f2 -d\")

if [ "${MODE}" = "server" ]
then
    systemctl stop openvpn-server@server.service
else
    if [ "${MODE}" = "client" ]
    then
        systemctl stop openvpn-client@client.service
    else
        echo "ERROR, mode is not set, is the plugin configured?"
    fi
fi

