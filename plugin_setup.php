
<script>
var config = {};          // Plugin configuration

function CreateConfigFile() {
    var url = 'api/plugin/fpp-OpenVPN/config';
    $.ajax({
        url: url,
        type: 'POST',
        async: true,
        dataType: 'json',
        success: function (result) {
            if ( result.status == 'OK' ) {
                // Do something here
            } else {
                alert('Error creating config file: ' + result.message);
            }
        },
        error: function () {
            $.jGrowl('Error in ' + url, { themeState: 'danger' });
        }
    });

}

function OpenVPNmodeChanged() {
    modeChanged();
    CreateConfigFile();

    if (pluginSettings['mode'] == 'server') {
        $('.openvpnServerSetting').show();
        $('.openvpnClientSetting').hide();
    } else {
        $('.openvpnServerSetting').hide();
        $('.openvpnClientSetting').show();
    }
}

function OpenVPNautostartChanged() {
    autostartChanged();

    var action = 'enable';
    if (pluginSettings['autostart'] == 'no')
        action = 'disable';

    $.ajax({
        url: 'api/plugin/fpp-OpenVPN/daemon/' + action,
        type: 'POST',
        async: true,
        dataType: 'json',
        success: function (result) {
            if ( result.status == 'OK' ) {
                $.jGrowl('OpenVPN server auto-start ' + action + 'ed');
            } else {
                alert('Error creating config file: ' + result.message);
            }
        },
        error: function () {
            $.jGrowl('Error in ' + url, { themeState: 'danger' });
        }
    });
}

function OpenVPNserverChanged() {
    serverChanged();
    CreateConfigFile();
}

function OpenVPNportChanged() {
    portChanged();
    CreateConfigFile();
}

function OpenVPNprotocolChanged() {
    protocolChanged();
    CreateConfigFile();
}

function OpenVPNpoolChanged() {
    poolChanged();
    CreateConfigFile();
}

function ControlDaemon(action) {
    var url = 'api/plugin/fpp-OpenVPN/daemon/' + action;
    $.ajax({
        url: url,
        type: 'POST',
        async: true,
        dataType: 'json',
        success: function (result) {
            if ( result.status == 'OK' ) {
                // Do something here
            } else {
                alert('Error controlling daemon: ' + result.message);
            }
        },
        error: function () {
            $.jGrowl('Error in ' + url, { themeState: 'danger' });
        }
    });
}

function ShowConnectionStatus() {
}

function GenerateKeysDone() {
    $('#generateKeysCloseButton').prop('disabled', false);
    EnableModalDialogCloseButton('generateKeysDialog');

    setTimeout(function() {alert("Remember to regenerate all client configs to populate the new server key info.  You will then need to copy the client's new config to each client.");}, 250);
}

function GenerateServerKeys(regen = false) {
    if (regen) {
        if (!confirm('Regenerating the server key will require regenerating all client configs. Do you wish to continue?'))
            return;
    }

   var options = {
        id: 'generateKeysDialog',
        title: regen ? 'Generate Server Key' : 'Regenerate Server Key',
        body: "<textarea style='width: 99%; height: 500px;' disabled id='generateKeysText'></textarea>",
        noClose: true,
        keyboard: false,
        backdrop: 'static',
        footer: '',
        buttons: {
            'Close': {
                id: 'generateKeysCloseButton',
                click: function() { CloseModalDialog('generateKeysDialog'); },
                disabled: true,
                class: 'btn-success'
            }
        }
    };

    $('#generateKeysCloseButton').prop('disabled', true);
    DoModalDialog(options);

    StreamURL('runEventScript.php?plugin=fpp-OpenVPN&scriptName=generate-server-keys.sh&nohtml=1', 'generateKeysText', 'GenerateKeysDone');
}

function SaveClientConfig() {
    var config = $('#clientovpn').val();

    $.ajax({
        url: 'api/file/config/openvpn-client.ovpn',
        type: 'POST',
        async: true,
        dataType: 'text',
        data: config,
        success: function (result) {
            if ( result.status == 'OK' ) {
                $.jGrowl('.ovpn config saved');
            } else {
                alert('Error saving .ovpn config: ' + result.message);
            }
        },
        error: function () {
            $.jGrowl('Error saving .ovpn config', { themeState: 'danger' });
        }
    });

}

function DeleteSelectedClient() {
    if (clientTableInfo.selected >= 0) {
        var id = $('#clientsBody .fppTableSelectedEntry').find('.id').html();

        if (id != '') {
            $.ajax({
                url: 'api/plugin/fpp-OpenVPN/client/' + id,
                type: 'DELETE',
                async: true,
                dataType: 'json',
                success: function (result) {
                    if ( result.status == 'OK' ) {
                        $('#clientsBody .fppTableSelectedEntry').remove();
                        clientTableInfo.selected = -1;
                        SetButtonState("#btnDeleteClient", "disable");
                    } else {
                        alert('Error deleting client config: ' + result.message);
                    }
                },
                error: function () {
                    $.jGrowl('Error in ' + url, { themeState: 'danger' });
                }
            });
        }
    }
}

function RegenerateClientConfig(row) {
    var id = $(row).find('.id').html();

    $('html,body').css('cursor', 'wait');
    $.ajax({
        url: 'api/plugin/fpp-OpenVPN/client/' + id,
        type: 'POST',
        async: true,
        dataType: 'json',
        success: function (result) {
            $('html,body').css('cursor', 'auto');
            if ( result.status == 'OK' ) {
                ViewFile('config', 'openvpn-client-' + id + '.ovpn');
            } else {
                alert('Error creating client config: ' + result.message);
            }
        },
        error: function () {
            $('html,body').css('cursor', 'auto');
            $.jGrowl('Error in ' + url, { themeState: 'danger' });
        }
    });

}

function InsertClientRow() {
    var id = prompt('New Client ID:', '');
    if ((id != null) && (id != '')) {
        $('html,body').css('cursor', 'wait');
        $.ajax({
            url: 'api/plugin/fpp-OpenVPN/client/' + id,
            type: 'POST',
            async: true,
            dataType: 'json',
            success: function (result) {
                $('html,body').css('cursor', 'auto');
                if ( result.status == 'OK' ) {
                    $('#clientsBody').append("<tr><td valign='middle'>  <div class='rowGrip'> <i class='rowGripIcon fpp-icon-grip'></i> </div> </td>" +
                        "<td class='id'>" + id + "</td>" +
                        "<td><input type='button' class='buttons' value='View' onClick='ViewClientConfig($(this).parent().parent());'> <input type='button' class='buttons' value='Download' onClick='GetClientConfig($(this).parent().parent());'> <input type='button' class='buttons' value='Regenerate' onClick='RegenerateClientConfig($(this).parent().parent());'></td>" + 
                        "</tr>");
                    ViewFile('config', 'openvpn-client-' + id + '.ovpn');
                } else {
                    alert('Error creating client config: ' + result.message);
                }
            },
            error: function () {
                $('html,body').css('cursor', 'auto');
                $.jGrowl('Error in ' + url, { themeState: 'danger' });
            }
        });
    }
}

function ViewClientConfig(row) {
    var id = $(row).find('.id').html();
    ViewFile('config', 'openvpn-client-' + id + '.ovpn');
}

function GetClientConfig(row) {
    var id = $(row).find('.id').html();
    location.href='api/file/config/openvpn-client-' + id + '.ovpn';
}

var clientTableInfo = {
    tableName: "clientsTable",
    selected:  -1,
    enableButtons: [ "btnDeleteClient" ],
    disableButtons: [],
    sortable: 1
};

$(document).ready(function() {
    SetupSelectableTableRow(clientTableInfo);
    $(document).tooltip();
});

</script>


<div id="warningsRow" class="alert alert-danger"><div id="warningsTd"><div id="warningsDiv"></div></div></div>
<div id="global" class="settings">
    <fieldset>
<?
PrintSettingGroup('openvpnsettings', '', '', '', 'fpp-OpenVPN');

if ($pluginSettings['mode'] == 'client') {
?>
        <div class="row tablePageHeader openvpnClientSetting">
            <div class="col-md"><h3>Client .ovpn config</h3></div>
            <div class="col-md-auto ms-lg-auto">
                <div class="form-actions">
                    <button class='buttons btn-success' onClick='SaveClientConfig();'>Save</button>
                </div>
            </div>
        </div>

        <textarea id='clientovpn' rows='10' cols='100'><?
$data = file_get_contents('/home/fpp/media/config/openvpn-client.ovpn');
printf( "%s", $data);
?></textarea><br>
    <br>
<?
} else {
// Mode == 'server'
?>
        <div class="row tablePageHeader">
            <div class="col-md"><h3>VPN Client Configurations</h3></div>
            <div class="col-md-auto ms-lg-auto">
                <div class="form-actions">
                    <input type=button value='Delete' onClick='DeleteSelectedClient();' data-btn-enabled-class="btn-outline-danger" id='btnDeleteClient' class='disableButtons'>
                    <button class='buttons btn-outline-success' value='Add' onClick='InsertClientRow();'><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>
        </div>

        <div class='fppTableWrapper fppTableWrapperAsTable'>
            <div class='fppTableContents'>
                <table id='clientsTable' class='fppSelectableRowTable'>
                    <thead>
                        <tr class='tblheader'>
                            <th></th>
                            <th title='ID'>ID</th>
                            <th title='Actions'>Actions</th>
                        </tr>
                    </thead>
                    <tbody id='clientsBody' class='ui-sortable'>
<?
foreach (scandir('/home/fpp/media/config') as $fileName) {
    if (preg_match('/^openvpn-client-.*\.ovpn$/', $fileName)) {
        $id = preg_replace('/^openvpn-client-/', '', $fileName);
        $id = preg_replace('/\.ovpn$/', '', $id);
        printf("<tr><td valign='middle'>  <div class='rowGrip'> <i class='rowGripIcon fpp-icon-grip'></i> </div> </td><td class='id'>%s</td>" .
            "<td><input type='button' class='buttons' value='View' onClick='ViewClientConfig($(this).parent().parent());'> <input type='button' class='buttons' value='Download' onClick='GetClientConfig($(this).parent().parent());'> <input type='button' class='buttons' value='Regenerate' onClick='RegenerateClientConfig($(this).parent().parent());'></td>" .
            "</tr>", $id);
    }
}
?>
                    </tbody>
                </table>
            </div>
        </div>

<?
    if (file_exists('/etc/openvpn/server/server.conf')) {
        echo "<input type='button' class='buttons btn-success' value='Regenerate Server Keys' onClick='GenerateServerKeys(true);'>\n";
    } else {
        echo "<input type='button' class='buttons btn-success' value='Generate Server Keys' onClick='GenerateServerKeys();'>\n";
    }
// Mode == 'server'
}
?>
        <input type='button' class='buttons btn-success' value='Start Daemon' onClick="ControlDaemon('start');">
        <input type='button' class='buttons btn-success' value='Stop Daemon' onClick="ControlDaemon('stop');">
        <br>
        <b>Logs are visible in the <a href='uploadfile.php'>File Manager</a>.</b>

    </fieldset>
</div>

