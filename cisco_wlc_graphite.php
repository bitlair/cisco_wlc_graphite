<?php
/**
 * Get CLI stats from Cisco WLC and insert into Graphite/Carbon.
 *
 * @author: Arjan Koopen <arjan@koopen.net>
 */

error_reporting(E_ALL ^ E_NOTICE);
$ip = "127.0.0.1";	// graphite IP
$port = 2003;		// graphite port
define("SNMP_COMMUNITY", "###");	// Cisco WLC SNMP community
define("SNMP_IP", "a.b.c.d");		// Cisco WLC IP address
define("PREFIX", "cisco.air.");

$fsock = fsockopen($ip, $port);

function getApTable() {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.2.1.1.3");
        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-6); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);
                $name = str_replace("STRING: ", "", str_replace("\"", "", $value));

                $table[$id] = str_replace(".", "_", $name);
        }
        return $table;
}

function getRadioTable($apTable) {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.2.2.1.2");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);
                $id2 = substr($id, 0, -2);

                // Defined in Cisco MIB .1.3.6.1.4.1.14179.2.2.2.1.2
                if ($value == "INTEGER: 1") $type = "11g";
                else $type = "11a";

                $table[$id] = $apTable[$id2] . "." . $type;

        }

        return $table;

}

function getNoOfClientsPerRadio($radioTable) {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.2.2.1.15");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);

                $table[$radioTable[$id]] = str_replace("Counter32: ", "", $value);

        }

        return $table;

}

function getEssTable() {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.1.1.1.2");

        foreach ($temp as $key => $value) {
                $tmp = explode(".",$key);
                $table[$tmp[count($tmp)-1]] = str_replace("STRING: ", "", str_replace("\"", "", $value));
        }

        return $table;
}

function getClientsPerEss($essTable) {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.1.4.1.6");

        foreach ($temp as $key => $value) {
                $ess_id = str_replace("INTEGER: ", "", $value);
                $table[$essTable[$ess_id]]++;
        }

        return $table;

}

function getChannelPerRadio($radioTable) {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.2.2.1.4");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);

                $table[$radioTable[$id]] = str_replace("INTEGER: ", "", $value);

        }

        return $table;

}

function getNoisePerRadio($radioTable, $chanTable) {

        foreach ($radioTable as $key => $radioName) {
                $noise = str_replace("INTEGER: ", "", snmp2_get(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.2.15.1.21." . $key . "." . $chanTable[$radioName]));
                $table[$radioName] = $noise;
        }

        return $table;
}

function getUtilPerRadio($radioTable) {
        $table = array();
        $temp = snmp2_real_walk(SNMP_IP, SNMP_COMMUNITY, ".1.3.6.1.4.1.14179.2.2.13.1.3");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);

                $table[$radioTable[$id]] = str_replace("INTEGER: ", "", $value);

        }

        return $table;

}


/**
 * Clients per radio
 */

$aps = getApTable();
$radios = getRadioTable($aps);
$clientsPerRadio = getNoOfClientsPerRadio($radios);
$clientsPerRadio["bogus.bogus"] = 0;

$total = 0;
$assoc_band = array();

$prev_ap = "";
$prev_no = 0;
$send = "";
foreach ($clientsPerRadio as $radio => $no) {
	$tmp = explode(".",$radio);

	if ($prev_ap != "" && $prev_ap != $tmp[0]) {
		$send .= PREFIX . "assoc.ap." . $prev_ap . ".total " . $prev_no . " " . time() . "\n";
		$prev_no = 0;
	}

	if ($tmp[0] != "bogus") {
		$total += $no;
		$assoc_band[$tmp[1]] += $no;
		$prev_no += $no;

		$send .= PREFIX . "assoc.ap." . $radio . " " . $no . " " . time() . "\n";
		$prev_ap = $tmp[0];
	}
}
$send .= PREFIX . "assoc.total " . $total . " " . time() . "\n";

foreach ($assoc_band as $band => $no) {
	$send .= PREFIX . "assoc.band." . $band . " " . $no . " " . time() . "\n";
}

echo $send;
fwrite($fsock, $send, strlen($send));

/**
 * Clients per ESS
 */
$ess = getEssTable();
$send = "";
$clientsPerEss = getClientsPerEss($ess);

foreach ($clientsPerEss as $ess => $no) {
	$send .= PREFIX . "assoc.ess." . $ess . " " . $no . " " . time() . "\n";
}

echo $send;
fwrite($fsock, $send, strlen($send));

/**
 * Radio info
 */

// channel info
$chan = getChannelPerRadio($radios);
$send = "";
foreach ($chan as $radio => $ch) {
	$send .= PREFIX . "radio_info." . $radio . ".channel " . $ch . " " . time() . "\n";
}
echo $send;
fwrite($fsock, $send, strlen($send));

// util
$util = getUtilPerRadio($radios);

$send = "";
foreach ($util as $radio => $ut) {
	$send .= PREFIX . "radio_info." . $radio . ".util " . $ut . " " . time() . "\n";
}
echo $send;
fwrite($fsock, $send, strlen($send));

// noise
$noise = getNoisePerRadio($radios, $chan);
$send = "";
foreach ($noise as $radio => $ns) {
	$send .= PREFIX . "radio_info." . $radio . ".noise " . $ns . " " . time() . "\n";
}

fwrite($fsock, $send, strlen($send));

fclose($fsock);
?>