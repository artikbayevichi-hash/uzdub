<?php
require 'config/db.php';
$r = $pdo->query("DESCRIBE categories");
while ($row = $r->fetch()) echo $row['Field'] . " | ";
echo "\n";
$r = $pdo->query("SELECT * FROM categories");
while ($row = $r->fetch(PDO::FETCH_ASSOC)) print_r($row);
