<?php

require('../../includes/csrf.php');
include_once('../../includes/functions.php');

$gwconn = new GatewayConnection();

if (isset($_POST['interface'])) {
    $int = preg_replace('/[^a-z0-9]/', '', $_POST['interface']);
    $gwconn->run_exec_gateway('ip a s '.$int, $intOutput, $intResult);
    $intOutput = array_map('htmlentities', $intOutput);
    $jsonData = ['return'=>$intResult,'output'=>$intOutput];
    echo json_encode($jsonData);
} else {
    $jsonData = ['return'=>2,'output'=>['Error getting data']];
    echo json_encode($jsonData);
}
