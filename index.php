<?php


# Skript zum Auswerten der Freifunk-Json
# 1. Lese Node-ID aus nodes.txt
# 2. Lade json
# 3. Suche eigene Nodes in json
# 4. Zeige Infos zu eigenen Nodes in Tabelle

##### MAIN #####

# Einlesen der Router
$router_list = read_router_file();

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


##### AUSGABE #####

# HTML HEAD
print_html_head($now);

# Zeige Tabelle Offline
print_table($router_offline,"Offline");

# Zeige Tabelle Uplink-Router
print_table($router_uplink,"Online mit Uplink");

# Zeige Tabelle Online
print_table($router_online,"Online");

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
		$uplink = $flags['uplink']; # WICHTIG
		$inf_node = array("lastseen" => $lastseen, "hostname" => $hostname, "node_id" => $node_id,"ipv6" => $ipv6,"online" => $online,"uplink" => $uplink);

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

function print_table($router_info, $titel)

{
	# Style
	echo "<h2>$titel</h2>";
?>

	<TABLE>
	<tr><th>Hostname</th><th>Node-ID</th><th>Letze Nachricht vor</th><th>IP</th><tr>
<?php
	# Tabelle füllen 
	foreach ($router_info as $router){
		echo "<tr>";
		echo "<td>".$router['hostname']."</td>";
		echo "<td>".$router['node_id']."</td>";
		echo "<td>".$router['lastseen']."</td>";
		$ip = $router['ipv6'];
		echo "<td> <a href=\"http://[".$ip."]\">".$ip."</a> </td>";
		echo "</tr>";

	}
	echo "</TABLE><br />";
}

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
	echo "Quellcode (nicht immer aktuell):"; 
	$adresse = "https://github.com/GitNorb/freifunk-myk_monitoring_page";
	echo "<td> <a href=\"".$adresse."\">".$adresse."</a> </td>";
	echo "</body>\n</html>";
}

# List die Datei mit den Node-IDs ein und gibt ein Array mit dienen zurück
function read_router_file()
{
	return explode("\n", file_get_contents('nodes.txt'));
}

?>
