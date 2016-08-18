<?php
function sendGraphite($field, $value) {
        global $graphite_send, $graphite_prefix, $c_name, $fsock;

        $send = $graphite_prefix . $c_name . "." . $field . " " . $value . " " . time() . "\n";

        if ($graphite_send) {
                 fwrite($fsock, $send, strlen($send));
        }

        echo $send;
}

function get_snmp($oid, $type = "Gauge32") {
        global $ip, $community;

        return $value = sanatize_snmp($type, snmp2_get($ip, $community, $oid));
}

function sanatize_snmp($type, $value) {
        switch ($type) {
                case "Gauge32":
                        $value = str_replace("Gauge32: ", "", $value);
                        break;

                case "STRING":
                        $value = str_replace("\"", "", str_replace("STRING: ", "", $value));
                        break;

                case "INTEGER":
                        $value = str_replace("INTEGER: ", "", $value);
                        break;

                case "Counter32":
                        $value = str_replace("Counter32: ", "", $value);
                        break;

                case "Counter64":
                        $value = str_replace("Counter64: ", "", $value);
                        break;
        }
        return $value;
}

function getApTable() {
		global $ip, $community;

        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.2.1.1.3");
        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-6); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);
                $name = sanatize_snmp("STRING", $value);

                $table[$id] = str_replace(".", "_", $name);
        }
        return $table;
}

function getRadioTable($apTable) {
		global $ip, $community;

        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.2.2.1.2");

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
		global $ip, $community;

        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.2.2.1.15");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);

                $table[$radioTable[$id]] = sanatize_snmp("Counter32", $value);

        }

        return $table;

}

function getEssTable() {
		global $ip, $community;

        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.1.1.1.2");

        foreach ($temp as $key => $value) {
                $tmp = explode(".",$key);
                $table[$tmp[count($tmp)-1]] = str_replace(" ", "_", str_replace(".", "_", sanatize_snmp("STRING", $value)));
        }

        return $table;
}

function getClientsPerEss($essTable) {
		global $ip, $community;

        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.1.4.1.6");

        foreach ($temp as $key => $value) {
                $ess_id = sanatize_snmp("INTEGER", $value);
                $table[$essTable[$ess_id]]++;
        }

        return $table;

}

function getChannelPerRadio($radioTable) {
		global $ip, $community;

        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.2.2.1.4");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);

                $table[$radioTable[$id]] = sanatize_snmp("INTEGER", $value);

        }

        return $table;

}

function getNoisePerRadio($radioTable, $chanTable) {
		global $ip, $community;

        foreach ($radioTable as $key => $radioName) {
                $noise = get_snmp(".1.3.6.1.4.1.14179.2.2.15.1.21." . $key . "." . $chanTable[$radioName], "INTEGER");
                $table[$radioName] = $noise;
        }

        return $table;
}

function getUtilPerRadio($radioTable) {
		global $ip, $community;
	
        $table = array();
        $temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.2.13.1.3");

        foreach ($temp as $key => $value) {
                $tmp = explode(".", $key);
                $id = array();
                for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                        $id[] = $tmp[$i];
                }
                $id = implode(".",$id);

                $table[$radioTable[$id]] = sanatize_snmp("INTEGER", $value);

        }

        return $table;

}

function getRadioCounters($radioTable) {
		global $ip, $community;
	
        $table = array();
		$counters = array (
			1 => "TransmittedFragmentCount",
			10 => "MulticastReceivedFrameCount",
			11 => "FCSErrorCount",
			12 => "TransmittedFrameCount",
			2 => "MulticastTransmittedFrameCount",
			3 => "RetryCount",
			33 => "FailedCount",
			4 => "MultipleRetryCount",
			5 => "FrameDuplicateCount",
			6 => "RTSSuccessCount",
			7 => "RTSFailureCount",
			8 => "ACKFailureCount",
			9 => "ReceivedFragmentCount"
		);
		
		foreach ($counters as $index => $counter_field) {
			$temp = snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.14179.2.2.6.1.{$index}");
			
        	foreach ($temp as $key => $value) {
                	$tmp = explode(".", $key);
                	$id = array();
                	for ($i = (count($tmp)-7); $i < count($tmp); $i++) {
                    	    $id[] = $tmp[$i];
                	}
                	$id = implode(".",$id);

            	    $table[$radioTable[$id]][$counter_field] = sanatize_snmp("Counter32", $value);

        	}		
		}
		
		return $table;
}
?>