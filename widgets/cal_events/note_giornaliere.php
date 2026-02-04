<?php
// widgets/cal_events/note_giornaliere.php

// Controllo sicurezza
if (!isset($conn) || !isset($CURRENT_DATE_ISO)) return;

// Cerchiamo se c'è una nota per la data corrente
$sql = "SELECT note FROM WI_NOTE WHERE data = '$CURRENT_DATE_ISO'";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $nota_completa = $row['note'];
    
    // Se c'è una nota, la mostriamo
    if (!empty($nota_completa)) {
        
        // STILE CSS AVANZATO:
        // font-size: 0.95em; -> Carattere più grande
        // white-space: nowrap; -> Obbliga il testo a stare su una riga
        // overflow: hidden; -> Nasconde quello che esce dal bordo
        // text-overflow: ellipsis; -> Aggiunge i puntini "..." se il testo è troppo lungo
        
        echo '<div style="
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba; 
            padding: 4px 6px; 
            border-radius: 4px; 
            font-size: 0.95em; 
            margin-top: 3px;
            font-weight: 500;
            
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            max-width: 100%;
        ">';
        
        // Stampiamo l'icona e il testo intero (ci pensa il CSS a tagliarlo)
        echo '📝 ' . htmlspecialchars($nota_completa);
        
        echo '</div>';
    }
}
?>