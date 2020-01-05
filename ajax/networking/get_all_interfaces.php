<?php

require('../../includes/csrf.php');

$GLOBALS["gwconn"]->run_exec_gateway("ls /sys/class/net | grep -v lo", $interfaces);
echo json_encode($interfaces);
