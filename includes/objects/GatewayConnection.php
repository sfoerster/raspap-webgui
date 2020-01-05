<?php

class GatewayConnection {
    var $conn;
    var $port = 22;

    function __construct() {
        $this->conn = ssh2_connect($this->get_ipv4_gateway(),
                                   $this->port, 
                                   array('hostkey'=>'ssh-rsa'));
        
        if (ssh2_auth_pubkey_file($this->conn, $this->get_user_host()[0],
                                  '/ssh/id_rsa.pub',
                                  '/ssh/id_rsa')) {

            echo "SSH authentication successful\n";
        } else {
            die("SSH authentication failed\nGateway: ".$this->get_ipv4_gateway()."\nUser: ".$this->get_user_host()[0]);
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

    function run_shell_gateway($cmd) {
        // initialize out
        $out = '';

        // set the stream
        $stream = ssh2_exec($this->conn, $cmd);
        
        // enabling blocking
        stream_set_blocking($stream, true);
        
        // iterate through the stream
        while($line = fgets($stream)) {
            flush();
            $out += trim(preg_replace('/\s+/', ' ', $line));
        }
        
        // close the stream
        fclose($stream);

        return $out;
    }

    function run_exec_gateway($cmd, &$out, &$ret) {
        // initialize out and err
        if (is_null($out)) {
            $out = [];
        }
        $err = [];

        $stream = ssh2_exec($this->conn, $cmd);
        $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

        // Enable blocking for both streams
        stream_set_blocking($errorStream, true);
        stream_set_blocking($stream, true);

        // iterate through the streams
        while($line = fgets($stream)) {
            flush();
            array_push($out, trim(preg_replace('/\s+/', ' ', $line)));
        }

        while($errline = fgets($errorStream)) {
            flush();
            array_push($err, trim(preg_replace('/\s+/', ' ', $errline)));
        }
        
        // Whichever of the two below commands is listed first will receive its appropriate output.  The second command receives nothing
        // echo "Output: " . stream_get_contents($stream);
        // echo "Error: " . stream_get_contents($errorStream);

        // Close the streams
        fclose($errorStream);
        fclose($stream);

        // return value
        if (empty($err)) {
            $ret = 0;
        } else {
            $ret = 1;
        }

        return $err;
    }
}

?>
