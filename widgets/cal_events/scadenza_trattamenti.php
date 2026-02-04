<?php
// widgets/cal_events/scadenza_trattamenti.php

// 1. CONTROLLO VARIABILI
// Il calendario ci passa $conn e $CURRENT_DATE_ISO. 
// Se mancano, usciamo.
if (!isset($conn) || !isset($CURRENT_DATE_ISO)) {
    return;
}

$data_ricerca = $CURRENT_DATE_ISO; // La data del riquadro che stiamo processando

// 2. QUERY
// Cerchiamo trattamenti che scadono ESATTAMENTE in questa data e non sono chiusi
$sql = "SELECT T.AT_DESCR, A.AR_CODICE 
        FROM TR_SCAD S
        JOIN TA_Attivita T ON S.SC_TATT = T.AT_ID
        JOIN AP_Arnie A ON S.SC_ARNIA = A.AR_ID
        WHERE S.SC_DATAF = '$data_ricerca'  
        AND S.SC_CHIUSO = 0";

$res = $conn->query($sql);

// 3. OUTPUT (Fondamentale: usare ECHO)
if ($res && $res->num_rows > 0) {
    // Apro un contenitore se vuoi raggrupparli, oppure stampo div separati
    while ($row = $res->fetch_assoc()) {
        
        // Stile "Trattamento" (Rosso chiaro come da tuo vecchio codice)
        echo '<div style="background-color: #ffebee; color: #c62828; border: 1px solid #e57373; padding: 3px; border-radius: 4px; font-size: 0.85em; margin-bottom: 2px;">';
        
        // Icona o testo grassetto per l'Arnia
        echo '<b>[' . $row['AR_CODICE'] . ']</b> ';
        
        // Descrizione trattamento
        echo $row['AT_DESCR'];
        
        echo '</div>';
    }
}
?>