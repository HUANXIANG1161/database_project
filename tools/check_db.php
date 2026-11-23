<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$pdo = db();
$tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='travel_share' AND table_type='BASE TABLE'")->fetchColumn();
$views = $pdo->query("SELECT COUNT(*) FROM information_schema.views WHERE table_schema='travel_share'")->fetchColumn();
$procs = $pdo->query("SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='travel_share' AND routine_type='PROCEDURE'")->fetchColumn();
$trigs = $pdo->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='travel_share'")->fetchColumn();
echo "tables={$tables} views={$views} procedures={$procs} triggers={$trigs}\n";
