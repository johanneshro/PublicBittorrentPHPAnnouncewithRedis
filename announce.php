<?php

function track($list, $complete=0, $incomplete=0, $compact=false) {

	if(is_string($list)) {
		return "d14:failure reason".strlen($list).":".$list."e";
	}

	$peers = $peers6 = array();

	foreach($list AS $peer_id => $peer) {

		if($compact) {

			$longip = ip2long($peer["ip"]);

			if($longip) {
				$peers[] = pack("Nn", sprintf("%d", $longip), $peer["port"]);
			} else {
				$peers6[] = pack("H32n", $peer["ip"], $peer["port"]);
			}

		} else {

			$pid = (!isset($_GET["no_peer_id"])) ? "7:peer id".strlen($peer_id).":".$peer_id : "";

			$peers[] = "d2:ip".strlen($peer["ip"]).":".$peer["ip"].$pid."4:porti".$peer["port"]."ee";

		}

	}

	$peers = (count($peers) > 0) ? @implode($peers) : "";
	$peers6 = (count($peers6) > 0) ? @implode($peers6) : "";

	$response = "d8:intervali".INTERVAL."e12:min intervali".INTERVAL_MIN."e8:completei".$complete."e10:incompletei".$incomplete."e5:peers".($compact ? strlen($peers).":".$peers."6:peers6".strlen($peers6).":".$peers6 : "l".$peers."e")."e";

	return $response;

}

function getIP() {

    if(!empty($_SERVER["HTTP_CLIENT_IP"])) {

		$ip = $_SERVER["HTTP_CLIENT_IP"];

    }
    elseif(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {

        $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);

        $ip = trim($ips[0]);

    } else {

        $ip = $_SERVER["REMOTE_ADDR"];

    }

    return $ip;

}

function checkGET($key, $strlen_check=false) {

	if(!isset($_GET[$key])) {
		die(track("Missing key: $key"));
	}
	elseif(!is_string($_GET[$key])) {
		die(track("Invalid types on one or more arguments"));
	}
	elseif($strlen_check && strlen($_GET[$key]) != 20) {
		die(track("Invalid length on ".$key." argument"));
	}
	elseif(strlen($_GET[$key]) > 128) {
		die(track("Argument ".$key." is too large to handle"));
	}

	return $_GET[$key];

}

function isBlocked($ip) {

    $ip_list = file("blocked_ips.txt", FILE_SKIP_EMPTY_LINES);

    if(count($ip_list) > 0) {

		foreach($ip_list AS $value) {

			if(trim($value) == $ip) {
				return true;
			}

		}

    }

    return false;

}

if(empty($_GET)) {

	header("Location: stats.php");
	exit;

}

header("Content-type: Text/Plain");
header("Pragma: no-cache");

define("INTERVAL", 1800);
define("INTERVAL_MIN", 300);
define("CLIENT_TIMEOUT", 60);

$ip = getIP();
$useragent = $_SERVER["HTTP_USER_AGENT"];

if(isBlocked($ip)) {
	die(track("You don't have permission to access this server."));
}

$r = new Redis();
$r->connect("127.0.0.1", 6379);

if(!$r) {
	die(track("Database failure"));
}

$info_hash = checkGET("info_hash", true);
$peer_id = checkGET("peer_id", true);
$port = checkGET("port");

if(!ctype_digit($port) || $port < 1 || $port > 65535) {
	die(track("Invalid client port"));
}

$map = $info_hash.":".$peer_id;

if(isset($_GET["event"]) && $_GET["event"] === "stopped") {

	$r->sRem($info_hash, $peer_id);
	$r->delete($map);

	if(count($r->sMembers($info_hash)) == 0) {

		$r->sRem("torrents", $info_hash);
		$r->delete($info_hash);

	}

	die(track(array()));

}

$r->sAdd("torrents", $info_hash);
$r->sAdd($info_hash, $peer_id);

$downloaded = (isset($_GET["downloaded"])) ? intval($_GET["downloaded"]) : 0;
$uploaded = (isset($_GET["uploaded"])) ? intval($_GET["uploaded"]) : 0;
$left = (isset($_GET["left"])) ? intval($_GET["left"]) : 0;
$is_seed = ($left == 0) ? 1 : 0;
$numwant = (isset($_GET["numwant"])) ? intval($_GET["numwant"]) : 50;
$compact = (isset($_GET["compact"]) && intval($_GET["compact"]) == 1) ? true : false;

$r->hMSet($map, array("ip" => $ip, "port" => $port, "seed" => $is_seed, "downloaded" => $downloaded, "uploaded" => $uploaded, "left" => $left, "date" => time(), "useragent" => $useragent));
$r->expire($map, INTERVAL+CLIENT_TIMEOUT);

$pid_list = $r->sMembers($info_hash);

$peers = array();
$count = $seeder = $leecher = 0;
foreach($pid_list AS $pid) {

	if($pid == $peer_id) continue;

	$temp = $r->hMGet($info_hash.":".$pid, array("ip", "port", "seed"));

	if(!$temp["ip"]) {

		$r->sRem($info_hash, $pid);

	} else {

		if($temp["seed"]) {
			$seeder++;
		} else {
			$leecher++;
		}

		if($temp["seed"] && $is_seed) continue;

		if($count < $numwant) {

			$peers[$pid] = array("ip" => $temp["ip"], "port" => $temp["port"]);

			$count++;

		}

	}

}

$r->close();

if($is_seed) {
	$seeder++;
} else {
	$leecher++;
}

echo track($peers, $seeder, $leecher, $compact);
exit;

?>