<?php

class GatewayConnection {
    var $conn;
    var $port = 22;

    function __construct() {
        $this->conn = ssh2_connect($this-get_ipv4_gateway(), 
                                   $this->port, 
                                   array('hostkey'=>'ssh-rsa'));
        
        if (ssh2_auth_pubkey_file($this->conn, $this->get_user_host()[0],
                                  '/ssh/id_rsa.pub',
                                  '/ssh/id_rsa')) {

            echo "SSH authentication successful\n";
        } else {
            die('SSH authentication failed\n');
        }
    }

    function __destruct() {
        return ssh2_disconnect($this->conn);
    }

    function get_user_host() {
        $userhost_str = shell_exec("cat /ssh/id_rsa.pub | awk '{print $3}'");
        return explode("@", $userhost_str);
    }

    function get_ipv4_interface() {
        return shell_exec("ip -o -4 route show to default | awk '{print $5}'");
    }

    function get_ipv4_gateway() {
        return shell_exec("ip -o -4 route show to default | awk '{print $3}'");
    }

    function get_ipv4_address() {
        $iface = $this->get_ipv4_interface();
        $ip_sub = shell_exec("ip -4 addr show $iface | grep 'inet ' | awk '{print \$2}'");
        return explode("/", $ip_sub);
    }

    function run_cmd_gateway($cmd) {
        return ssh2_exec($this->conn, $cmd);
    }
}

?>