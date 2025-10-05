<?php
$statusFile = "status.json";
if (!file_exists($statusFile)) file_put_contents($statusFile, "{}");

$statuses = json_decode(file_get_contents($statusFile), true);

// Define o código e o novo status
$codigo = "86728844";
$novoStatus = "preparando";

// Atualiza e salva
$statuses[$codigo] = $novoStatus;
file_put_contents($statusFile, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Status do pedido $codigo atualizado para '$novoStatus'.";
?>