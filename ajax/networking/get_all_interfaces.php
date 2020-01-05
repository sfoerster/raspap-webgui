<?php

require('../../includes/csrf.php');
include_once('../../includes/functions.php');

$gwconn = new GatewayConnection();

$gwconn->run_exec_gateway("ls /sys/class/net | grep -v lo", $interfaces);
echo json_encode($interfaces);
