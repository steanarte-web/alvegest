<?php
// FILE: includes/ajax/upload_note_image.php

// Percorso assoluto per evitare errori di inclusione
$base_path = dirname(__DIR__); // Arriva alla cartella /includes/
require_once $base_path . '/config.php'; 

header('Content-Type: application/json');

// 1. Verifica Metodo e Dati
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$note_id = $_POST['note_id'] ?? 0;
if (!$note_id || !isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

$file = $_FILES['image'];

// 2. Verifica Errori Upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Errore PHP Upload: ' . $file['error']]);
    exit;
}

// 3. Controllo Estensione
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Formato non valido']);
    exit;
}

// 4. GENERAZIONE NOME (Standard mobile.php)
// Formato: N_000000XX_YYYYMMDDHHMMSS.ext
$prefisso = "N_";
$id_part = str_pad($note_id, 8, '0', STR_PAD_LEFT);
$timestamp = date('Ymd_His');
$nome_file_db = $prefisso . $id_part . "_" . $timestamp . "." . $ext;

// 5. Percorso di Salvataggio (Cartella 'immagini' nella root)
// dirname(__DIR__, 2) risale di 2 livelli: da includes/ajax/ -> root/
$root_path = dirname(__DIR__, 2);
$target_dir = $root_path . '/immagini/';

// Crea la cartella se non esiste
if (!is_dir($target_dir)) {
    if (!@mkdir($target_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Errore permessi cartella immagini']);
        exit;
    }
}

// 6. Spostamento File e Salvataggio DB
if (move_uploaded_file($file['tmp_name'], $target_dir . $nome_file_db)) {
    
    // Inserimento in AT_FOTO (FO_TIAT='N', FO_Arnia=0)
    $sql = "INSERT INTO AT_FOTO (FO_ATT, FO_NOME, FO_TIAT, FO_Arnia) VALUES (?, ?, 'N', 0) 
            ON DUPLICATE KEY UPDATE FO_NOME = VALUES(FO_NOME)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $note_id, $nome_file_db);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Errore DB: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Errore SQL Prepare']);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Fallito spostamento file (Verifica permessi cartella immagini)']);
}
?>