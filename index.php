<?php
# Skript zum Auswerten der Freifunk-Json
# 1. Lese Node-ID aus nodes.txt
# 2. Lade json
# 3. Suche eigene Nodes in json
# 4. Zeige Infos zu eigenen Nodes in Tabelle

##### MAIN #####

# Einlesen der Router
$router_list = read_router_url();
#$router_list = read_router_file();


# Einlesen der JSON in ein String
$data_as_string = file_get_contents('https://map.freifunk-myk.de/hopglass/nodes.json');

# Decode JSON
$data = json_decode($data_as_string,true);

# Zeit ermitteln
$now = strtotime($data['timestamp']);

# Suche die eigenen Router aus den Daten
$nodes = $data['nodes'];
$own_router_index_list = search_own_router($nodes,$router_list);

# Iteriere über eigene Router und sammel Informationen
$router_offline = catch_information($nodes,$own_router_index_list,"offline"); 
$router_uplink = catch_information($nodes,$own_router_index_list,"uplink");
$router_online = catch_information($nodes,$own_router_index_list,"online");

# Sortiere die drei Listen nach Hostnamen
usort($router_offline, "compare_host");
usort($router_uplink, "compare_host");
usort($router_online, "compare_host");

##### AUSGABE #####

# HTML HEAD
print_html_head($now);

# Zeige Tabelle Kopf
print_table_head();

# Zeige Nodes Offline
print_table_data($router_offline);

# Zeige Nodes Uplink-Router
print_table_data($router_uplink);

# Zeige Nodes Online
print_table_data($router_online);

# Zeige Tabelle Abschluss
print_table_bot();

# Formular zum Hinzufügen neuer Nodes
print_form($router_list);

# HTML BOT
print_html_bot();


##### FUNKTIONEN #####

# Suche relevante Router
# Eingabe: Array, Liste eigener Router
# Ausgabe: Indizes relevanter Router
function search_own_router($all_nodes,$own_nodes)
{
	# Deklariere Array
	$own_router_index = array();
	$i = 0;
	foreach ($all_nodes as $node)
	{
		$nodeinfo = $node['nodeinfo'];
		$node_id = $nodeinfo['node_id'];
		foreach ($own_nodes as $own_id)
		{
			if ($own_id == $node_id)
			{
				array_push($own_router_index,$i);
			}
		}
		$i=$i+1;
	}
	return $own_router_index;
}

# Extrahiere Routerinformationen
# Eingabe Liste Nodes, Index eigene Nodes
# Ausgabe Routerliste mit Infors zum Router
function catch_information($nodes,$index_own_nodes,$status)
{
	$own_nodes_list = array();
	foreach ($index_own_nodes as $index)
	{
		$push = false;
		$node = $nodes[$index];
		$lastseen = format_date($node['lastseen']); # WICHTIG
		# Nodeinfo
		$nodeinfo = $node['nodeinfo'];
		$hostname = $nodeinfo['hostname']; # WICHTIG
		$node_id = $nodeinfo['node_id']; # WICHTIG
		# Nodeinfo - Network
		$network = $nodeinfo['network'];
		$addresses = $network['addresses'];

		# Nodeinfo - Software - Firmware - Release
		$base = $nodeinfo['software']['firmware']['base'];
		# lösche folgende substrings in base
		$hw = array("gluon-",);
		$base = str_replace($hw, '', $base);
		$release = $nodeinfo['software']['firmware']['release'];
		
		# Nodeinfo - Hardware - model
		$model = $nodeinfo['hardware']['model'];
		# lösche folgende substrings in model
		$hw = array("TP-Link", "TP-LINK", "ALFA NETWORK", "N/ND");
		$model = str_replace($hw, '', $model);

		# statistics - gateway/gateway_nexthop
		$gateway = $node['statistics']['gateway'];
		$gateway_nexthop = $node['statistics']['gateway_nexthop'];
		# Uplink (Workaround, da Uplink direkt nicht mehr in der JSON steht)
                $uplink = ($gateway == $gateway_nexthop); # WICHTIG

		# Richtige IP wählen
		if (startsWith($addresses['0'],"fe80:"))
		{
			$ipv6 = $addresses['1']; # WICHTIG
		}
		else
		{
			$ipv6 = $addresses['0']; # WICHTIG
		}
		# Flags
		$flags = $node['flags'];
		$online = $flags['online']; # WICHTIG
		// $uplink = $flags['uplink']; # WICHTIG (kaputt)
		$inf_node = array(
				"lastseen" => $lastseen,
				"hostname" => $hostname,
				"node_id" => $node_id,
				"ipv6" => $ipv6,
				"online" => $online,
				"uplink" => $uplink,
				"release" => $release,
				"base" => $base,
				"model" => $model);

		# Hier Entscheide, ob offline, uplink oder online
		if ($status == "offline")
		{
			if (! $online)
			{
				$push = true;
			}
		} 
		else
		{
			if ($status == "uplink")
			{
				if ($uplink and $online)
				{
					$push = true;
				}
			}
			else
			{
				if ($status == "online")
				{
					if ($online and ! $uplink)
					{
						$push = true;
					}
				}
			}
		}
		if ($push)
		{
			array_push($own_nodes_list,$inf_node);
		}	
	}	
	return $own_nodes_list;
}

function compare_host($a, $b)
{
$hostname1 = $a['hostname'];
$hostname2 = $b['hostname'];

return strcmp ($hostname1,$hostname2);


}

function format_date($zeit)
{
	global $now; # Nimm die Globale now
	$in_time = strtotime($zeit);

	$now = time(); # TODO Hier Serverzeit

	$differenz = $now - $in_time;
	$tag  = floor($differenz / (3600*24));
	$std  = floor($differenz / 3600 % 24);
	$min  = floor($differenz / 60 % 60);
	$sek  = floor($differenz % 60);


	if ($tag > 0)
	{
		return "$tag Tag(e)";
	}
	if ($std > 0)
	{
		return "$std Stunde(n)";
	}
	if ($min > 0)
	{
		return "$min Minute(n)";
	}
	if ($sek > 0)
	{
		return "$sek Sekunde(n)";
	}
	if ($sek < 0 or $sek = 0)
	{
		return "einige Sekunden";

	}
}

function print_table_head()
{
?>
<TABLE>
	<tr><th>Status</th><th>Hostname/Kartenlink</th><th>Hardware</th><th>Software</th><th>IP</th><tr>
<?php
}

function print_table_data($router_info)
{
	# Tabelle füllen 
	foreach ($router_info as $router){
		echo "<tr>";
		if ($router['online']) {$status = "<schwarz>online</schwarz>";}else{$status = "<rot>offline ".$router['lastseen']."</rot>";}
		if ($router['online'] && $router['uplink']) {$status = "<gruen>online/uplink</gruen>";}
		echo "<td>".$status."</td>";
		echo "<td> <a href=\"https://map.freifunk-myk.de/#!v:m;n:".$router['node_id']."\">".$router['hostname']."</a></td>";
		echo "<td>".$router['model']."</td>";
		echo "<td>".$router['base']."</td>";
		$ip = $router['ipv6'];
		echo "<td> <a href=\"http://[".$ip."]\">".$ip."</a> </td>";
		echo "</tr>";

	}
}

function print_table_bot()
{
	echo "</TABLE><br />";
}

# Hilfsfunktion
# Gibt 1 zurück, wenn String mit Substring startet
function startsWith($haystack, $needle)
{
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

# Gibt oberer Teil der Seite aus
function print_html_head($now)
{
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset='UTF-8' />
		<title> Freifunk</title>
		<link rel=stylesheet type="text/css" href="css/style.css">
	</head>
	<body>
<?php
	$datum = date("d.m.y",$now);
	$uhrzeit = date("G:i",$now);
	echo "<h1>Status Nodes $datum um $uhrzeit Uhr</h1> <br />";
}

# Gibt unterer Teil der Seite aus
function print_html_bot()
{
	echo "Quellcode:"; 
	$adresse = "https://github.com/GitNorb/freifunk-myk_monitoring_page";
	echo "<td> <a href=\"".$adresse."\">".$adresse."</a> </td>";
	echo "</body>\n</html>";
}

# List die Datei mit den Node-IDs ein und gibt ein Array mit dienen zurück
function read_router_file()
{
	return explode("\n", file_get_contents('nodes.txt'));
}

# Nimmt die Router aus der URL
function read_router_url()
{
	return explode(";", $_GET["nodeid"]);
}

function print_form($router_list)
{
?>
<br> 
<form method="GET" action="index.php">
<b>Nodeliste: <input name="nodeid" value="<?php echo implode(";",$router_list); ?>" > <input type=submit name=submit value="Exekutieren">
</form>
<br>
<br> 
<?php 
}

?>



