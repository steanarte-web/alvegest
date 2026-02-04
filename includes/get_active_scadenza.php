<?php
// QUESTO MESSAGGIO SERVE PER IL DEBUG VISIVO
// Se il file viene letto, lo vedrai nella risposta della rete
// echo "SONO ENTRATO NEL FILE GET_ACTIVE_SCADENZA"; 

require_once 'config.php';

$arnia_id = $_GET['arnia_id'] ?? null;
$response = ['active' => false, 'log' => 'SONO ENTRATO NEL FILE'];

if ($arnia_id) {
    $sql = "SELECT S.SC_ID, S.SC_DATAF, S.SC_AVA, T.AT_DESCR, T.AT_GG, T.AT_NR
            FROM TR_SCAD S
            JOIN TA_Attivita T ON S.SC_TATT = T.AT_ID
            WHERE S.SC_ARNIA = ? AND S.SC_CHIUSO = 0
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $arnia_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $oggi = new DateTime();
            $data_scadenza = new DateTime($row['SC_DATAF']);
            $diff = $data_scadenza->diff($oggi);
            $giorni_ritardo = (int)$diff->format("%r%a"); 

            $colore = ''; 
            if ($oggi > $data_scadenza) {
                $colore = ($giorni_ritardo > 4) ? 'red' : 'yellow';
            }

            $response = [
                'active' => true,
                'sc_id' => $row['SC_ID'],
                'tipo_descr' => $row['AT_DESCR'],
                'color' => $colore,
                'giorni' => $giorni_ritardo,
                'log' => 'SONO ENTRATO E HO TROVATO UNA SCADENZA'
            ];
        } else {
            $response['log'] = 'SONO ENTRATO MA NON CI SONO SCADENZE APERTE';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);