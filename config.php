<?php
error_reporting(E_ALL ^ E_NOTICE);

$graphite_send = false; // set to false if you only want text output (for debugging)
$graphite_ip = "127.0.0.1";
$graphite_port = 2003;
$graphite_prefix = "wlan.cisco.";

// controller name => IP address
$controllers = array (
        "wlc" => "a.b.c.d"
);
// snmp community
$community = "public";
?>