<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Stats of the tracker</title>
	<link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.10/css/jquery.dataTables.min.css">
	<script type="text/javascript" src="http://code.jquery.com/jquery.min.js"></script>
	<script type="text/javascript" src="//cdn.datatables.net/1.10.10/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript">

		$(document).ready(function() {

			var table = $("#myTable").DataTable({
				paging: true,
				responsive: true,
				"columnDefs": [
					{ "visible": false, "targets": 0 }
				],
				"order": [[0, "asc"]],
				"drawCallback": function(settings) {

					var api = this.api();
					var rows = api.rows({ page: "current" }).nodes();
					var last = null;

					api.column(0, { page: "current" }).data().each(function(group, i) {

						if(last !== group) {

							$(rows).eq(i).before(
								'<tr class="group" style="background-color:#DDDDDD;"><td colspan="10">'+group+'</td></tr>'
							);

							last = group;

						}

					});

				},
				"initComplete": function(settings, json) {
					$(this).show();
				}
			});

			$("#myTable tbody").on("click", "tr.group", function() {

				var currentOrder = table.order()[0];

				if(currentOrder[0] === 0 && currentOrder[1] === "asc") {
					table.order([0, "desc"]).draw();
				} else {
					table.order([0, "asc"]).draw();
				}

			});

		});

	</script>

	<style>

		body {
			font-family: Verdana, Arial, Helvetica, sans-serif;
			font-size: 8pt;
			font-style: normal;
			line-height: normal;
			font-weight: normal;
			font-variant: normal;
			text-transform: none;
			color: #000000;
			text-decoration: none;
			background-color: #EEEEEE;
		}

	</style>
</head>
<body>
	<div>
		<table id="myTable" class="display" style="display:none;">
			<thead>
				<tr>
					<th>Info_Hash</th>
					<th>Peer_ID</th>
					<th>Client</th>
					<th>IP</th>
					<th>Port</th>
					<th>Seed</th>
					<th>DL</th>
					<th>UL</th>
					<th>Left</th>
					<th>Update</th>
				</tr>
			</thead>
			<tbody>
<?php

	function formatBytes($size, $precision = 0) {

		$unit = ['Byte', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];

		for($i = 0; $size >= 1024 && $i < count($unit)-1; $i++) {
			$size /= 1024;
		}

		return round($size, $precision)." ".$unit[$i];

	}

	function formatUpdate($timeago) {

		if($timeago < 60) {
			$value = $timeago.'s';
		}
		elseif($timeago >= 60 && $timeago < 3600) {
			$value = floor((($timeago)/60)).' Min.';
		}

		return $value;

	}

	function formatClient($useragent) {

    	preg_match("/^([^;]*).*$/", $useragent, $client);

		return $client[1];

	}

	$r = new Redis();
	$r->connect("127.0.0.1",6379);

	if($r) {

		$data = "";

		$torrents = $r->sMembers("torrents");

		if(count($torrents) > 0) {

			foreach($torrents AS $info_hash) {

				$peers = $r->sMembers($info_hash);

				if(count($peers) == 0) {

					$r->sRem("torrents", $info_hash);
					$r->delete($info_hash);

				} else {

					foreach($peers AS $peer_id) {

						$temp = $r->hMGet($info_hash.":".$peer_id, array("ip", "port", "seed", "downloaded", "uploaded", "left", "date", "useragent"));

						if(!$temp["ip"]) {

							$r->sRem($info_hash, $peer_id);

						} else {

							$peerid = bin2hex($peer_id);
							$downloaded = formatBytes($temp["downloaded"]);
							$uploaded = formatBytes($temp["uploaded"]);
							$left = formatBytes($temp["left"]);
							$update = time()-$temp["date"];

							$data .= "<tr>\n";
							$data .= "<td>".bin2hex($info_hash)."</td>\n".
								"<td data-search=\"".$peerid."\" data-order=\"".$peerid."\">&bull; ".$peerid."</td>\n".
								"<td>".formatClient($temp["useragent"])."</td>\n".
								"<td>".$temp["ip"]."</td>\n".
								"<td>".$temp["port"]."</td>\n".
								"<td>".$temp["seed"]."</td>\n".
								"<td data-search=\"".$downloaded."\" data-order=\"".$temp["downloaded"]."\">".$downloaded."</td>\n".
								"<td data-search=\"".$uploaded."\" data-order=\"".$temp["uploaded"]."\">".$uploaded."</td>\n".
								"<td data-search=\"".$left."\" data-order=\"".$temp["left"]."\">".$left."</td>\n".
								"<td data-search=\"".$update."\" data-order=\"".$update."\">".formatUpdate($update)."</td>\n";
							$data .= "</tr>\n";

						}

					}

				}

			}

		}

		$r->close();

		echo $data;

	}

?>
			</tbody>
		</table>
	</div>
</body>
</html>