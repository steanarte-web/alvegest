<?php
// widgets/cal_events/inizio_fioriture.php

// Controllo sicurezza
if (!isset($conn) || !isset($CURRENT_DATE_ISO)) return;

// Cerchiamo le fioriture che INIZIANO esattamente in questa data
$sql = "SELECT F.FI_CODICE, A.AI_LUOGO
        FROM TA_APIFIO AF
        JOIN TA_Fioriture F ON AF.FA_CodFio = F.FI_id
        JOIN TA_Apiari A ON AF.FA_COD_API = A.AI_ID
        WHERE AF.FA_inizio = '$CURRENT_DATE_ISO'";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        
        // STILE "FESTA DELLA FIORITURA" 🌻
        // Colori vistosi per celebrare l'inizio del raccolto
        
        echo '<div style="
            background-color: #ffff00; /* Giallo Puro e Vistoso */
            color: #d50000; /* Rosso Acceso per il testo (o verde scuro se preferisci #1b5e20) */
            border: 2px solid #ff6d00; /* Bordo Arancione bello spesso */
            padding: 4px; 
            border-radius: 6px; 
            font-size: 0.95em; /* Scritta un po\' più grande */
            font-weight: bold; /* Grassetto per farsi notare */
            margin-bottom: 3px;
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2); /* Ombra per effetto 3D "Pop-up" */
        ">';
        
        // Icona fiore grande + Codice Fioritura
        echo '🌼 ' . htmlspecialchars($row['FI_CODICE']);
        
        // Luogo
        echo ' <span style="color:#333; font-weight:normal; font-size:0.85em;">@ ' . htmlspecialchars($row['AI_LUOGO']) . '</span>';
        
        echo '</div>';
    }
}
?>