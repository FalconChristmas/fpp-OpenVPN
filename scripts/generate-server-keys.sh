#!/bin/bash

export BASEDIR=/etc/openvpn/keys
export PASSWD=fppfpp

sudo mkdir -p ${BASEDIR} || exit 1

sudo systemctl stop openvpn-server@server.service > /dev/null 2>&1

echo "Creating server key -"
sudo openssl genrsa -passout pass:${PASSWD} -des3 -out ${BASEDIR}/server.key 2048 2>&1
echo

echo "Removing passphrase on server key -"
sudo openssl rsa -passin pass:${PASSWD} -in ${BASEDIR}/server.key -out ${BASEDIR}/server.key 2>&1
echo

echo "Creating signing request."
sudo openssl req -sha256 -new -key ${BASEDIR}/server.key -out ${BASEDIR}/server.csr -subj '/CN=fpp-server' 2>&1

echo "Creating CA key -"
sudo openssl req -passout pass:${PASSWD} -x509 -sha256 -days 3650 -newkey rsa:2048 -keyout ${BASEDIR}/ca.key -out ${BASEDIR}/ca.crt -subj '/CN=fpp-ca' 2>&1
echo

echo "Removing passphrase on CA key -"
sudo openssl rsa -passin pass:${PASSWD} -in ${BASEDIR}/ca.key -out ${BASEDIR}/ca.key 2>&1
echo

echo "Creating server key and signing request -"
sudo openssl req -newkey rsa:2048 -nodes -keyout ${BASEDIR}/server.key -out ${BASEDIR}/server.csr -subj '/CN=fpp-server' 2>&1

# This would create a self-signed cert without the CA involved
#openssl x509 -signkey ${BASEDIR}/server.key -in ${BASEDIR}/server.csr -req -days 3650 -out ${BASEDIR}/server.crt

# input file for signing by CA
sudo tee ${BASEDIR}/server.ext > /dev/null <<-EXTEOF
authorityKeyIdentifier = keyid, issuer
keyUsage               = critical, nonRepudiation, digitalSignature, keyEncipherment, keyAgreement
extendedKeyUsage       = critical, serverAuth
basicConstraints       = CA:FALSE
subjectAltName         = @alt_names

[alt_names]
DNS.1 = fpp-server
EXTEOF

echo "Signing the certificate request -"
sudo openssl x509 -req -CA ${BASEDIR}/ca.crt -CAkey ${BASEDIR}/ca.key -in ${BASEDIR}/server.csr -out ${BASEDIR}/server.crt -days 3650 -CAcreateserial -extfile ${BASEDIR}/server.ext 2>&1
echo

echo "Generating Diffie-Helman file -"
echo "NOTE: This may take a long time on slower systems"
sudo openssl dhparam -out ${BASEDIR}/dh2048.pem 2048 2>&1
echo

echo "Generating tls-auth key:"
sudo openvpn --genkey secret ${BASEDIR}/ta.key 2>&1
echo

echo "Copying files to /etc/openvpn/server:"
sudo cp -v ${BASEDIR}/dh2048.pem ${BASEDIR}/ca.crt ${BASEDIR}/server.crt ${BASEDIR}/server.key ${BASEDIR}/ta.key /etc/openvpn/server/ 2>&1
echo

echo "========================"
echo "Generated Server Certificate:"
sudo openssl x509 -text -noout -in ${BASEDIR}/server.crt 2>&1
echo

echo "========================"
echo "Complete."
