<?php

require '../../includes/csrf.php';

$gwconn = new GatewayConnection();

$gwconn->run_exec_gateway("ls /sys/class/net | grep -v lo", $interfaces);
echo json_encode($interfaces);
