<?php
// FILE: includes/ajax/save_new_note.php

$base_path = dirname(__DIR__); 
require_once $base_path . '/config.php'; 

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? 'save';

// --- AZIONE: ELIMINA ---
if ($action === 'delete') {
    $id = $input['id'] ?? 0;
    if ($id) {
        // Elimina SOLO la nota. La foto rimane orfana in AT_FOTO (Richiesta specifica)
        $stmt = $conn->prepare("DELETE FROM CF_NOTE_WIDGET WHERE NW_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

// --- AZIONE: SALVA / AGGIORNA ---
$titolo = $input['titolo'] ?? '';
$testo = $input['testo'] ?? '';
$dest = $input['dest'] ?? 0;
$id = $input['id'] ?? '';

if (!$titolo || !$testo) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

if ($id) {
    // UPDATE
    $stmt = $conn->prepare("UPDATE CF_NOTE_WIDGET SET NW_TITOLO=?, NW_CONTENUTO=?, NW_UTENTE_ID=? WHERE NW_ID=?");
    $stmt->bind_param("ssii", $titolo, $testo, $dest, $id);
} else {
    // INSERT
    $stmt = $conn->prepare("INSERT INTO CF_NOTE_WIDGET (NW_TITOLO, NW_CONTENUTO, NW_UTENTE_ID, NW_DATA) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("ssi", $titolo, $testo, $dest);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
?>