<?
function getEndpointsfppOpenVPN() {
    $result = array();

    $ep = array(
        'method' => 'POST',
        'endpoint' => 'server',
        'callback' => 'fppOpenVPNServer');
    array_push($result, $ep);

    $ep = array(
        'method' => 'POST',
        'endpoint' => 'client/:id',
        'callback' => 'fppOpenVPNClient');
    array_push($result, $ep);

    $ep = array(
        'method' => 'DELETE',
        'endpoint' => 'client/:id',
        'callback' => 'fppOpenVPNClient');
    array_push($result, $ep);

    $ep = array(
        'method' => 'POST',
        'endpoint' => 'daemon/:action',
        'callback' => 'fppOpenVPNDaemon');
    array_push($result, $ep);

    $ep = array(
        'method' => 'POST',
        'endpoint' => 'config',
        'callback' => 'fppOpenVPNConfig');
    array_push($result, $ep);

    return $result;
}

function CreateLogFile($file) {
    exec("sudo touch $file; sudo chown fpp:fpp $file; sudo chmod 644 $file");
}

// POST /api/plugin/fpp-OpenVPN/config
// Upload .ovpn file to client
function fppOpenVPNConfig() {
    global $settings;
    global $pluginSettings;

    LoadPluginSettings('fpp-OpenVPN');
}

// POST /api/plugin/fpp-OpenVPN/server
// Configure server
function fppOpenVPNServer() {
    global $settings;
    global $pluginSettings;

    LoadPluginSettings('fpp-OpenVPN');

    $verbosity = 3;
    if ($settings['LogLevel_Plugin'] == 'warn') {
        $verbosity = 0;
    } else if ($settings['LogLevel_Plugin'] == 'debug') {
        $verbosity = 6;
    }

    $logFile = '/home/fpp/media/logs/openvpn.log';
    CreateLogFile($logFile);

    $statusFile = '/home/fpp/media/logs/openvpn-status.log';
    CreateLogFile($statusFile);

    $config = sprintf("#OpenVPN Server configuration
user nobody
group nogroup
port %d
proto %s
dev tun
ca ca.crt
cert server.crt
key server.key
dh dh2048.pem
server %s 255.255.255.0
ifconfig-pool-persist /var/log/openvpn/ipp.txt
keepalive 10 120
tls-auth [inline] 0
topology subnet
data-ciphers-fallback AES-256-CBC
persist-key
persist-tun
log %s
status %s
verb %d
mute 5
explicit-exit-notify 1
",
    $pluginSettings['port'],
    $pluginSettings['protocol'],
    $pluginSettings['pool'],
    $logFile,$statusFile, $verbosity);

    $tmpCfgFile = '/home/fpp/media/config/openvpn-server.conf';
    file_put_contents($tmpCfgFile, $config);
    exec("sudo cp $tmpCfgFile /etc/openvpn/server/server.conf");

    $service = "openvpn-server@server.service";

    if ($pluginSettings['autostart'] == 'yes') {
        exec("sudo systemctl enable $service $log");
    } else {
        exec("sudo systemctl disable $service $log");
    }

    $result = array();
    $result['status'] = 'OK';

    return json($result);
}

function fppOpenVPNClient() {
    global $settings;
    global $pluginSettings;
    $result = array();

    LoadPluginSettings('fpp-OpenVPN');

    $id = params('id');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        # Create/Update a client
        $verbosity = 3;
        if ($settings['LogLevel_Plugin'] == 'warn') {
            $verbosity = 0;
        } else if ($settings['LogLevel_Plugin'] == 'debug') {
            $verbosity = 6;
        }

        $logFile = '/home/fpp/media/logs/openvpn.log';
        CreateLogFile($logFile);

        $statusFile = '/home/fpp/media/logs/openvpn-status.log';
        CreateLogFile($logFile);

        $passwd = "fppfpp";
        $basedir = "/etc/openvpn/keys";
        $log = ">> /home/fpp/media/logs/openvpn-signing.log 2>&1";

        # Generate the client key
        exec("echo '=======================================================' $log ; date $log ; echo 'Signing log for client ID: \"$id\"' $log ; echo '---------' $log");
        exec("sudo openssl genrsa -passout pass:$passwd -des3 -out $basedir/$id.key 2048 $log");
        # Remove passphrase from client key
        exec("sudo openssl rsa -passin pass:$passwd -in $basedir/$id.key -out $basedir/$id.key $log && sudo chmod 644 $basedir/*.key");
        # Generate CSR
        exec("sudo openssl req -sha256 -new -key $basedir/$id.key -out $basedir/$id.csr -subj '/CN=fpp-$id' $log");
        # Generate .ext file for signing the CSR
        exec("/bin/echo -e \"authorityKeyIdentifier=keyid,issuer\nkeyUsage=critical,nonRepudiation,digitalSignature,keyEncipherment\nextendedKeyUsage=critical,clientAuth\nbasicConstraints=CA:FALSE\nsubjectAltName = @alt_names\n[alt_names]\nDNS.1 = fpp-$id\n\" | sudo tee $basedir/$id.ext > /dev/null");
        # Sign the cert
        exec("sudo openssl x509 -req -CA $basedir/ca.crt -CAkey $basedir/ca.key -in $basedir/$id.csr -out $basedir/$id.crt -days 3650 -CAcreateserial -extfile $basedir/$id.ext $log");

        $config = sprintf("#OpenVPN Client configuration
client
user nobody
group nogroup
remote %s %d
proto %s
dev tun
resolv-retry infinite
nobind
ca [inline]
cert [inline]
key [inline]
remote-cert-tls server
tls-auth [inline] 1
cipher AES-256-CBC
persist-key
persist-tun
#log %s
#status %s
verb 3
mute 5

",
        $pluginSettings['server'], $pluginSettings['port'],
        $pluginSettings['protocol'],
        $logFile, $statusFile, $verbosity);

        $data = file_get_contents('/etc/openvpn/server/ca.crt');
        $config .= "<ca>\n$data</ca>\n\n";

        $data = file_get_contents($basedir . '/' . $id . '.crt');
        $config .= "<cert>\n$data</cert>\n\n";

        $data = file_get_contents($basedir . '/' . $id . '.key');
        $config .= "<key>\n$data</key>\n\n";

        $data = file_get_contents($basedir . '/ta.key');
        $config .= "<tls-auth>\n$data</tls-auth>\n\n";

        $cfgFile = '/home/fpp/media/config/openvpn-client-' . $id . '.ovpn';
        file_put_contents($cfgFile, $config);

        $result['status'] = 'OK';

        $service = "openvpn-client@client.service";

        if ($pluginSettings['autostart'] == 'yes') {
            exec("sudo systemctl enable $service $log");
        } else {
            exec("sudo systemctl disable $service $log");
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        exec('sudo rm /etc/openvpn/keys/' . $id . '.*');
        unlink('/home/fpp/media/config/openvpn-client-' . $id . '.ovpn');

        $result['status'] = 'OK';
    }

    return json($result);
}

function fppOpenVPNDaemon() {
    global $settings;
    global $pluginSettings;

    LoadPluginSettings('fpp-OpenVPN');

    $result = array();
    $result['status'] = 'OK';
    $result['message'] = '';

    $action = params('action');

    if ($pluginSettings['mode'] == 'server') {
        $service = 'openvpn-server@server.service';
        exec('sudo cp /home/fpp/media/config/openvpn-server.conf /etc/openvpn/server/server.conf $log');
    } else {
        $service = 'openvpn-client@client.service';
        exec('sudo cp /home/fpp/media/config/openvpn-client.ovpn /etc/openvpn/client/client.conf $log');
    }

    $log = ">> /home/fpp/media/logs/openvpn-service.log 2>&1";

    if ($action == 'start') {
        exec("sudo systemctl start $service $log");
    } else if ($action == 'stop') {
        exec("sudo systemctl stop $service $log");
    } else if ($action == 'enable') {
        exec("sudo systemctl enable $service $log");
    } else if ($action == 'disable') {
        exec("sudo systemctl disable $service $log");
    } else {
        $result['status'] = 'Error';
        $result['message'] = 'Unknown action: ' . $action;
    }

    return json($result);
}

function fppOpenVPNKeys() {
    global $settings;
    global $pluginSettings;

    LoadPluginSettings('fpp-OpenVPN');


}

?>
