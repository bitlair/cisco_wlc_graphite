<?php
/**
 * Get SNMP stats from Cisco WLC and insert into Graphite/Carbon.
 *
 * @author: Arjan Koopen <arjan@koopen.net>
 */

include("config.php");
include("common.php");

if ($graphite_send) $fsock = fsockopen($graphite_ip, $graphite_port);

foreach ($controllers as $c_name => $ip) {
    /**
     * WLC Temperature
     */
	$temp = explode(" ", get_snmp("1.3.6.1.4.1.14179.2.3.1.13.0", "INTEGER"));
	sendGraphite("wlc_temparature", $temp[0]);

	 /**
     * WLC Memory
     */
	$total_memory = explode(" ", get_snmp("1.3.6.1.4.1.14179.1.1.5.2.0", "INTEGER"));
	sendGraphite("wlc_total_memory", $total_memory[0]);
	$free_memory = explode(" ", get_snmp("1.3.6.1.4.1.14179.1.1.5.3.0", "INTEGER"));
	sendGraphite("wlc_free_memory", $free_memory[0]);
  
    /**
     * WLC CPU - Overall
     */
	$wlc_cpu = explode(" ", get_snmp("1.3.6.1.4.1.14179.1.1.5.1.0", "INTEGER"));
	sendGraphite("wlc_cpu", $wlc_cpu[0]);

	 /**
     * WLC Connected APs
     */
	$wlc_total_ap = explode(" ", get_snmp("1.3.6.1.4.1.9.9.618.1.8.4.0", "Gauge32"));
	sendGraphite("wlc_total_ap", $wlc_total_ap[0]);

}

foreach ($controllers as $c_name => $ip) {
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
	foreach ($clientsPerRadio as $radio => $no) {
		$tmp = explode(".",$radio);

		if ($prev_ap != "" && $prev_ap != $tmp[0]) {
			sendGraphite("assoc.ap." . $prev_ap . ".total", $prev_no);
			$prev_no = 0;
		}

		if ($tmp[0] != "bogus") {
			$total += $no;
			$assoc_band[$tmp[1]] += $no;
			$prev_no += $no;

			sendGraphite("assoc.ap." . $radio , $no);
			$prev_ap = $tmp[0];
		}
	}
	
	sendGraphite("assoc.total", $total);

	foreach ($assoc_band as $band => $no) {
		sendGraphite("assoc.band." . $band, $no);
	}

	/**
 	 * Clients per ESS
 	 */
	$ess = getEssTable();

	$clientsPerEss = getClientsPerEss($ess);

	foreach ($clientsPerEss as $ess => $no) {
		sendGraphite("assoc.ess." . $ess, $no);
	}

	/**
 	 * Radio info
 	 */

	// channel info
	$chan = getChannelPerRadio($radios);
	foreach ($chan as $radio => $ch) {
		sendGraphite("radio." . $radio . ".channel", $ch);
	}

	// util
	$util = getUtilPerRadio($radios);
	foreach ($util as $radio => $ut) {
		sendGraphite("radio." . $radio . ".util", $ut);
	}

	// noise
	$noise = getNoisePerRadio($radios, $chan);
	foreach ($noise as $radio => $ns) {
		sendGraphite("radio." . $radio . ".noise", $ns);
	}
	
	// counters
	$counters = getRadioCounters($radios);
	foreach ($counters as $radio => $cnt) {
		foreach ($cnt as $field => $value) {
			sendGraphite("radio." . $radio . "." . $field, $value);
		}
	}
}

if ($graphite_send) fclose($fsock);
?>