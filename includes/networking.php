<?php

require_once 'includes/status_messages.php';

/**
 *
 *
 */
function DisplayNetworkingConfig()
{

    $status = new StatusMessages();

    $GLOBALS["gwconn"]->run_exec_gateway("ls /sys/class/net | grep -v lo", $interfaces);

    foreach ($interfaces as $interface) {
        $GLOBALS["gwconn"]->run_exec_gateway("ip a show $interface", $$interface);
    }
    echo renderTemplate("networking", compact("status", "interfaces"));
}
