<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

### Klassen/Interfaces vom Framework
class WireData{}

interface module{}

interface ConfigurableModule{}

### Laden der Module
include_once("monitoring.module");

# Laden der Nodeliste aus der URL
# Lade sie in Objekt Page, damit monitoring darauf Zugreifen kann
# Nimmt die Router aus der URL
$node_list=explode(";", $_GET["nodeid"]); # Als Liste

# Anzeigen der Tabelle
$monitoring = new monitoring();
$monitoring->build_table($node_list);

# Anzeigen des Node-Formulars
?>
<br>
<form method="GET" action="index.php">
<b>Nodeliste: <input name="nodeid" value="<?php echo implode(";",$node_list); ?>" > <input type=submit name=submit value="Exekutieren">
</form>
<br>
<br>
<?php
?>
