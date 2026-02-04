<?php
// Versione del file
$versione = "V.0.0.61 - Layout Arnia & Fix Storico"; 

// Forza l'encoding HTTP
header('Content-Type: text/html; charset=utf-8');

// Usa require_once per evitare l'errore "Cannot redeclare url()"
require_once 'includes/config.php'; 

// --- GESTIONE AJAX INTERNA PER CONTROLLO SCADENZA ---
if (isset($_GET['ajax_check_scadenza']) && isset($_GET['arnia_id'])) {
    header('Content-Type: application/json');
    $arnia_id = (int)$_GET['arnia_id'];
    $response = ['active' => false, 'sc_id' => 0, 'tipo_descr' => '', 'color' => '', 'days_diff' => 0];

    // Cerca una scadenza APERTA (SC_CHIUSO=0) per questa arnia
    $sql = "SELECT S.SC_ID, S.SC_DATAF, T.AT_DESCR 
            FROM TR_SCAD S 
            JOIN TA_Attivita T ON S.SC_TATT = T.AT_ID 
            WHERE S.SC_ARNIA = ? AND S.SC_CHIUSO = 0 
            ORDER BY S.SC_DATAF ASC LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $arnia_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $response['sc_id'] = $row['SC_ID'];
            $response['tipo_descr'] = $row['AT_DESCR'];
            $response['active'] = true; 
            
            $data_scadenza = new DateTime($row['SC_DATAF']);
            $oggi = new DateTime();
            $oggi->setTime(0, 0, 0);
            $data_scadenza->setTime(0, 0, 0);
            
            if ($oggi >= $data_scadenza) {
                $diff = $oggi->diff($data_scadenza);
                $response['days_diff'] = $diff->days;
                $response['color'] = ($diff->days >= 4) ? '#dc3545' : '#ffc107';
            } else {
                $response['color'] = '#28a745'; 
                $response['days_diff'] = 0;
            }
        }
        $stmt->close();
    }
    echo json_encode($response);
    exit();
}

// --- FUNZIONE AGGIUNTIVA: Trova il codice arnia ---
function get_arnia_codice($conn, $arnia_id) {
    if(!$arnia_id) return 0;
    $sql = "SELECT AR_CODICE FROM AP_Arnie WHERE AR_ID = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $arnia_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['AR_CODICE'] : 0;
    }
    return 0;
}

// --- LOGICA DI ELIMINAZIONE (CASCATA) ---
if (isset($_GET['elimina_id']) && is_numeric($_GET['elimina_id'])) {
    $id_da_eliminare = (int)$_GET['elimina_id'];
    $arnia_id_ref = $_GET['arnia_id'] ?? '';

    // 1. Elimina foto fisica
    $sql_foto = "SELECT FO_NOME FROM AT_FOTO WHERE FO_ATT = ?";
    $stmt_f = $conn->prepare($sql_foto);
    if ($stmt_f) {
        $stmt_f->bind_param("i", $id_da_eliminare);
        $stmt_f->execute();
        $res_f = $stmt_f->get_result();
        if ($row_f = $res_f->fetch_assoc()) {
            $percorso_file = 'immagini/' . $row_f['FO_NOME'];
            if (file_exists($percorso_file)) { unlink($percorso_file); }
        }
        $stmt_f->close();
    }
    $conn->query("DELETE FROM AT_FOTO WHERE FO_ATT = $id_da_eliminare");

    // 2. Elimina movimento Magazzino
    $search_tag = "%(IA_ID: $id_da_eliminare)%";
    $sql_del_mov = "DELETE FROM MA_MOVI WHERE MV_Descrizione LIKE ?";
    $stmt_dm = $conn->prepare($sql_del_mov);
    if ($stmt_dm) {
        $stmt_dm->bind_param("s", $search_tag);
        $stmt_dm->execute();
        $stmt_dm->close();
    }

    // 3. Elimina legami Fasi
    $conn->query("DELETE FROM TR_FFASE WHERE TF_CATT = $id_da_eliminare");

    // 4. Elimina record principale
    $sql_del_main = "DELETE FROM AT_INSATT WHERE IA_ID = ?";
    $stmt_m = $conn->prepare($sql_del_main);
    if ($stmt_m) {
        $stmt_m->bind_param("i", $id_da_eliminare);
        $stmt_m->execute();
        $stmt_m->close();
    }

    // Redirect mantenendo l'ID arnia e lo status del_success
    header("Location: mobile.php?status=del_success&arnia_id=" . $arnia_id_ref);
    exit();
}

// --- LOGICA DI INSERIMENTO E MODIFICA ---
$messaggio = "";
$is_manual_close_attempt = isset($_POST["scadenza_chiusura_manuale"]) && $_POST["scadenza_chiusura_manuale"] == "1";

if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST["conferma_registrazione"]) || $is_manual_close_attempt)) {
    
    $data = $_POST["data"] ?? date('Y-m-d');
    $arnia_id = $_POST["arnia_id_nascosto"] ?? null; 
    $tipo_attivita = $_POST["tipo_attivita"] ?? null; 
    $note = $_POST["note"] ?? '';
    $ia_peri = $_POST["ia_peri_hidden"] ?? 0;
    $ia_vreg = $_POST["ia_vreg_hidden"] ?? 0;
    $ia_op1 = $_POST["ia_op1_hidden"] ?? 0;
    $ia_op2 = $_POST["ia_op2_hidden"] ?? 0;
    $ia_id_modifica = $_POST["ia_id_modifica"] ?? null; 
    
    $last_id_ref = null;      
    $tipo_record_foto = 'A';  
    $codice_arnia_foto = 0;   

    $file_caricato = $_FILES['foto_attivita'] ?? null;
    $ha_foto = ($file_caricato && $file_caricato['error'] === UPLOAD_ERR_OK && $file_caricato['size'] > 0);

    // 1. CONTROLLO PRELIMINARE SE È UNA NOTA
    $is_nota_check = 0;
    if (!empty($tipo_attivita)) {
        $sql_check_nota = "SELECT AT_IS_NOTA FROM TA_Attivita WHERE AT_ID = ?";
        $stmt_cn = $conn->prepare($sql_check_nota);
        if ($stmt_cn) {
            $stmt_cn->bind_param("i", $tipo_attivita);
            $stmt_cn->execute();
            $res_cn = $stmt_cn->get_result();
            if ($row_cn = $res_cn->fetch_assoc()) {
                $is_nota_check = (int)$row_cn['AT_IS_NOTA'];
            }
            $stmt_cn->close();
        }
    }

    // =================================================================================
    // RAMO 1: È UNA NOTA
    // =================================================================================
    if ($is_nota_check == 1) {
        $tipo_record_foto = 'N';
        $codice_arnia_foto = 0;
        $titolo_nota = "VA " . date("d/m", strtotime($data));
        $search_date = $data . "%"; 
        
        $sql_check_exist = "SELECT NW_ID, NW_CONTENUTO FROM CF_NOTE_WIDGET WHERE NW_DATA LIKE ? AND NW_UTENTE_ID = 0 LIMIT 1";
        $stmt_chk = $conn->prepare($sql_check_exist);
        $stmt_chk->bind_param("s", $search_date);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        
        if ($row_chk = $res_chk->fetch_assoc()) {
            $last_id_ref = $row_chk['NW_ID'];
            $vecchio_contenuto = $row_chk['NW_CONTENUTO'];
            if (!empty($note)) {
                $nuovo_contenuto_totale = $vecchio_contenuto . "\n\n" . $note;
                $sql_upd_nota = "UPDATE CF_NOTE_WIDGET SET NW_CONTENUTO = ? WHERE NW_ID = ?";
                $stmt_u = $conn->prepare($sql_upd_nota);
                $stmt_u->bind_param("si", $nuovo_contenuto_totale, $last_id_ref);
                $stmt_u->execute();
                $stmt_u->close();
            }
        } else {
            $data_full = $data . " " . date("H:i:s");
            $sql_ins_note = "INSERT INTO CF_NOTE_WIDGET (NW_UTENTE_ID, NW_DATA, NW_TITOLO, NW_CONTENUTO) VALUES (0, ?, ?, ?)";
            $stmt_nw = $conn->prepare($sql_ins_note);
            if ($stmt_nw) {
                $stmt_nw->bind_param("sss", $data_full, $titolo_nota, $note);
                if ($stmt_nw->execute()) {
                    $last_id_ref = $conn->insert_id; 
                }
                $stmt_nw->close();
            }
        }
        $stmt_chk->close();
    }
    // =================================================================================
    // RAMO 2: È UN'ATTIVITÀ ARNIA
    // =================================================================================
    else {
        if (empty($arnia_id) || !is_numeric($arnia_id)) {
            $messaggio = "<p class='errore'>⚠️ Errore: Arnia non selezionata.</p>";
            goto fine_post;
        } 
        
        if ($is_manual_close_attempt) {
            $scad_id_to_close = (int)$_POST["scadenza_attiva_id"];
            if ($scad_id_to_close > 0) {
                $sql_manual_close = "UPDATE TR_SCAD SET SC_CHIUSO = 1 WHERE SC_ID = $scad_id_to_close";
                if ($conn->query($sql_manual_close)) {
                    header("Location: mobile.php?status=close_manual_success&arnia_id=" . $arnia_id);
                    exit();
                }
            }
            goto fine_post;
        }

        if (isset($_POST["conferma_registrazione"])) {
            $codice_arnia_foto = get_arnia_codice($conn, $arnia_id);
            if ($ia_peri == 1) { $tipo_record_foto = 'P'; } 
            elseif ($ia_vreg == 1) { $tipo_record_foto = 'R'; } 
            else { $tipo_record_foto = 'A'; }

            if (!empty($ia_id_modifica)) {
                $sql = "UPDATE AT_INSATT SET IA_DATA = ?, IA_NOTE = ?, IA_PERI = ?, IA_VREG = ?, IA_OP1 = ?, IA_OP2 = ? WHERE IA_ID = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ssiiiii", $data, $note, $ia_peri, $ia_vreg, $ia_op1, $ia_op2, $ia_id_modifica);
                    if ($stmt->execute()) {
                        $last_id_ref = $ia_id_modifica;
                        $sql_upd_fase = "UPDATE TR_FFASE f JOIN TR_PFASE p ON f.TF_PFASE = p.TP_ID SET p.TP_DAP = ? WHERE f.TF_CATT = ?";
                        $stmt_f = $conn->prepare($sql_upd_fase);
                        if($stmt_f) { $stmt_f->bind_param("si", $data, $last_id_ref); $stmt_f->execute(); }

                        $sql_check_mag = "SELECT AT_SCARICO_FISSO FROM TA_Attivita WHERE AT_ID = ?";
                        $stmt_check_mag = $conn->prepare($sql_check_mag);
                        if ($stmt_check_mag) {
                            $stmt_check_mag->bind_param("i", $tipo_attivita);
                            $stmt_check_mag->execute();
                            $res_check_mag = $stmt_check_mag->get_result();
                            if ($row_m = $res_check_mag->fetch_assoc()) {
                                $search_string = "%(IA_ID: $last_id_ref)%";
                                if ((int)$row_m['AT_SCARICO_FISSO'] === 0) {
                                    $nuova_qta = (float)str_replace(',', '.', filter_var($note, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                                    if ($nuova_qta > 0) {
                                        $sql_upd_mov = "UPDATE MA_MOVI SET MV_Scarico = ?, MV_Data = ? WHERE MV_Descrizione LIKE ?";
                                        $stmt_upd_mov = $conn->prepare($sql_upd_mov);
                                        if ($stmt_upd_mov) { $stmt_upd_mov->bind_param("dss", $nuova_qta, $data, $search_string); $stmt_upd_mov->execute(); }
                                    }
                                } else {
                                    $sql_upd_mov_date = "UPDATE MA_MOVI SET MV_Data = ? WHERE MV_Descrizione LIKE ?";
                                    $stmt_upd_mov_date = $conn->prepare($sql_upd_mov_date);
                                    if ($stmt_upd_mov_date) { $stmt_upd_mov_date->bind_param("ss", $data, $search_string); $stmt_upd_mov_date->execute(); }
                                }
                            }
                        }
                    }
                }
            } else {
                $sql = "INSERT INTO AT_INSATT (IA_DATA, IA_CodAr, IA_ATT, IA_NOTE, IA_PERI, IA_VREG, IA_OP1, IA_OP2) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("siissiii", $data, $arnia_id, $tipo_attivita, $note, $ia_peri, $ia_vreg, $ia_op1, $ia_op2);
                    if ($stmt->execute()) {
                        $last_id_ref = $conn->insert_id; 
                        $scad_id_attiva = (int)($_POST['scadenza_attiva_id'] ?? 0); 
                        $chiudi_premuto = $_POST['ia_op1_hidden'] ?? 0;

                        if ($scad_id_attiva > 0 && $chiudi_premuto == 1) {
                            $sql_update = "UPDATE TR_SCAD SET SC_CHIUSO = 1 WHERE SC_ID = $scad_id_attiva";
                            $conn->query($sql_update);
                        } else {
                            $sql_check_trat = "SELECT AT_TRAT, AT_GG FROM TA_Attivita WHERE AT_ID = ?";
                            $stmt_check = $conn->prepare($sql_check_trat);
                            if ($stmt_check) {
                                $stmt_check->bind_param("i", $tipo_attivita);
                                $stmt_check->execute();
                                $res_c = $stmt_check->get_result()->fetch_assoc();
                                if ($res_c && (int)$res_c['AT_TRAT'] == 1) {
                                    if ((int)$res_c['AT_GG'] > 0) {
                                        $sql_exists = "SELECT SC_ID FROM TR_SCAD WHERE SC_ARNIA = ? AND SC_TATT = ? AND SC_CHIUSO = 0";
                                        $stmt_exists = $conn->prepare($sql_exists);
                                        $stmt_exists->bind_param("ii", $arnia_id, $tipo_attivita);
                                        $stmt_exists->execute();
                                        $stmt_exists->store_result();
                                        if ($stmt_exists->num_rows == 0) {
                                            $intervallo = (int)$res_c['AT_GG'];
                                            $data_fine = date('Y-m-d', strtotime($data . " + $intervallo days"));
                                            $sql_ins_scad = "INSERT INTO TR_SCAD (SC_ARNIA, SC_TATT, SC_DINIZIO, SC_DATAF, SC_CHIUSO, SC_AVA) VALUES (?, ?, ?, ?, 0, 1)";
                                            $stmt_ins = $conn->prepare($sql_ins_scad);
                                            $stmt_ins->bind_param("iiss", $arnia_id, $tipo_attivita, $data, $data_fine);
                                            $stmt_ins->execute();
                                        }
                                        $stmt_exists->close();
                                    }
                                    $sql_phase = "SELECT TP_ID FROM TR_PFASE WHERE TP_DAP <= ? AND TP_CHIU IS NULL ORDER BY TP_DAP DESC LIMIT 1";
                                    $stmt_ph = $conn->prepare($sql_phase);
                                    if ($stmt_ph) {
                                        $stmt_ph->bind_param("s", $data);
                                        $stmt_ph->execute();
                                        $res_ph = $stmt_ph->get_result();
                                        if ($row_ph = $res_ph->fetch_assoc()) {
                                            $tp_id = $row_ph['TP_ID'];
                                            $sql_ins_ff = "INSERT INTO TR_FFASE (TF_PFASE, TF_ARNIA, TF_ATT, TF_CATT) VALUES (?, ?, ?, ?)";
                                            $stmt_ff = $conn->prepare($sql_ins_ff);
                                            if ($stmt_ff) {
                                                $stmt_ff->bind_param("iiii", $tp_id, $arnia_id, $tipo_attivita, $last_id_ref);
                                                $stmt_ff->execute();
                                                $stmt_ff->close();
                                            }
                                        }
                                        $stmt_ph->close();
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($last_id_ref && empty($ia_id_modifica)) {
                $sql_mag = "SELECT AT_MAG_ID, AT_SCARICO_FISSO, AT_DESCR FROM TA_Attivita WHERE AT_ID = ?";
                $stmt_mag = $conn->prepare($sql_mag);
                if ($stmt_mag) {
                    $stmt_mag->bind_param("i", $tipo_attivita); $stmt_mag->execute(); $res_mag = $stmt_mag->get_result();
                    if ($row_mag = $res_mag->fetch_assoc()) {
                        $mag_id = $row_mag['AT_MAG_ID'];
                        if (!empty($mag_id)) {
                            $at_descr = $row_mag['AT_DESCR']; $scarico_fisso = (int)$row_mag['AT_SCARICO_FISSO'];
                            $arnia_codice = get_arnia_codice($conn, $arnia_id);
                            $qta_scarico = ($scarico_fisso == 1) ? 1.00 : (float)str_replace(',', '.', filter_var($note, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                            if ($qta_scarico > 0) {
                                $causale_mov = "Scarico auto: " . $at_descr . " (Arnia: $arnia_codice) (IA_ID: $last_id_ref)";
                                $sql_ins_mov = "INSERT INTO MA_MOVI (MV_Data, MV_Descrizione, MV_MAG_ID, MV_Carico, MV_Scarico) VALUES (?, ?, ?, 0, ?)";
                                $stmt_mov = $conn->prepare($sql_ins_mov);
                                if ($stmt_mov) { $stmt_mov->bind_param("ssid", $data, $causale_mov, $mag_id, $qta_scarico); $stmt_mov->execute(); $stmt_mov->close(); }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($last_id_ref) {
        if ($ha_foto) {
            $ext = strtolower(pathinfo($file_caricato['name'], PATHINFO_EXTENSION));
            $prefix = $tipo_record_foto . "_";
            $id_part = str_pad($last_id_ref, 8, '0', STR_PAD_LEFT);
            $timestamp = date('Ymd_His');
            $nome_file_db = $prefix . $id_part . "_" . $timestamp . "." . $ext;
            
            $target_dir = __DIR__ . '/immagini/';
            if (!is_dir($target_dir)) {
                @mkdir($target_dir, 0755, true);
            }

            if (move_uploaded_file($file_caricato['tmp_name'], $target_dir . $nome_file_db)) {
                $sql_foto = "INSERT INTO AT_FOTO (FO_ATT, FO_NOME, FO_TIAT, FO_Arnia) VALUES (?, ?, ?, ?) 
                             ON DUPLICATE KEY UPDATE FO_NOME = VALUES(FO_NOME)";
                $stmt_foto = $conn->prepare($sql_foto);
                if ($stmt_foto) { 
                    $stmt_foto->bind_param("isss", $last_id_ref, $nome_file_db, $tipo_record_foto, $codice_arnia_foto); 
                    $stmt_foto->execute(); 
                    $stmt_foto->close(); 
                }
            }
        }
        $redir_params = ($tipo_record_foto != 'N') ? "&arnia_id=" . $arnia_id : "";
        header("Location: mobile.php?status=success" . $redir_params);
        exit();
    }
}
fine_post: 

$attivita_options = [];
$sql_attivita = "SELECT AT_ID, AT_DESCR, AT_IS_NOTA FROM TA_Attivita ORDER BY AT_DESCR";
$result_attivita = $conn->query($sql_attivita);
if ($result_attivita) { while ($row = $result_attivita->fetch_assoc()) { $attivita_options[] = $row; } }

$status_get = $_GET["status"] ?? "";
$successo = ($status_get == "success");
$chiusura_manuale_success = ($status_get == "close_manual_success");
$del_successo = ($status_get == "del_success");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title> AlveGest Mobile</title>
    <link rel="stylesheet" href="<?php echo TPL_URL; ?>mobile_app.css?v=<?php echo time(); ?>"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #alert_modifica {
            display:none; background:#fff3cd; color:#856404; padding:12px; 
            margin-bottom:15px; border-radius:8px; font-weight:bold; 
            border:1px solid #ffeeba; font-size: 0.9em;
        }
        .btn-cancel-edit {
            float:right; background:#dc3545; color:white; border:none; 
            border-radius:4px; padding: 2px 8px; cursor:pointer; font-size: 0.8em;
        }
        
        /* LAYOUT SCANNER MODIFICATO */
        .scanner-box {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        /* Colonna input molto più larga (65%) */
        .input-col {
            flex: 0 0 30%;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        /* Etichetta nome più stretta (35%) e non va a capo */
        .arnia-nome-label {
            flex: 1;
            font-size: 0.9em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
            line-height: 40px; /* Centra verticalmente rispetto all'input */
        }

        /* STILE BOTTONI MICROFONO */
        .mic-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 5px;
        }
        .mic-btn-small {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            min-width: 35px; /* Evita schiacciamento */
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .mic-btn {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .mic-btn.recording, .mic-btn-small.recording {
            background-color: #dc3545;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
    </style>
</head>
<body>

    <div class="header-mobile-bar">
        <div class="header-title-group">
            <h2 class="titolo-mobile">Registra Attività <?php if ($successo || $chiusura_manuale_success || $del_successo) echo "✅"; ?></h2>
        </div>
        <a href="index.php" class="btn-menu-header">MENU</a>
    </div>

    <div class="form-container-mobile">
        <?php echo $messaggio; ?>
        
        <div class="scanner-box">
            <div class="input-col">
                <input type="text" id="codice_arnia" name="codice_arnia" maxlength="4" autofocus required placeholder="Codice" inputmode="numeric" pattern="[0-9]*" style="flex-grow:1; width: 100%;">
                <button type="button" class="mic-btn-small" onclick="toggleGenericVoice('codice_arnia', 'text')" title="Detta Codice">🎤</button>
            </div>
            <div id="arnia_nome_display" class="arnia-nome-label">Nome Arnia</div>
        </div>

        <div class="tab-nav">
            <button class="tab-button active" onclick="openTab(event, 'inserimento')">NUOVA</button>
            <button class="tab-button" id="storico-tab-btn" onclick="openTab(event, 'storico'); loadLatestAttivita()">STORICO</button>
        </div>

        <form action="mobile.php" method="post" id="form_inserimento_attivita" enctype="multipart/form-data">
            <input type="hidden" name="conferma_registrazione" value="1">
            <input type="hidden" id="arnia_id_nascosto" name="arnia_id_nascosto" value="<?php echo $_GET['arnia_id'] ?? ''; ?>">
            <input type="hidden" id="scadenza_attiva_id" name="scadenza_attiva_id" value="0">
            <input type="hidden" id="scadenza_chiusura_manuale" name="scadenza_chiusura_manuale" value="0">
            <input type="hidden" id="ia_id_modifica" name="ia_id_modifica" value="">

            <div id="inserimento" class="tab-content-item active">
                
                <div id="alert_modifica">
                    MODIFICA RECORD ID: <span id="label_id_mod"></span>
                    <button type="button" class="btn-cancel-edit" onclick="cancelEdit()">ANNULLA</button>
                </div>

                <div class="form-row-mobile data-row">
                    <label for="data">Data:</label>
                    <input type="date" id="data" name="data" required value="<?php echo date('Y-m-d'); ?>">
                    
                    <div class="photo-col">
                        <button type="button" id="btn_foto">📸 FOTO</button>
                        <input type="file" id="foto_attivita" name="foto_attivita" accept="image/*" capture="camera" style="display: none;">
                    </div>
                </div>
                <p id="file_status" class="status-label"></p>

                <div class="form-group-mobile">
                    <label for="tipo_attivita">Attività:</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <select id="tipo_attivita" name="tipo_attivita" required style="flex-grow:1;">
                            <option value="" data-is-nota="0">Seleziona...</option>
                            <?php foreach ($attivita_options as $att): ?>
                                <option value="<?php echo $att['AT_ID']; ?>" data-is-nota="<?php echo $att['AT_IS_NOTA']; ?>">
                                    <?php echo htmlspecialchars($att['AT_DESCR']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="mic-btn-small" onclick="toggleGenericVoice('tipo_attivita', 'select')" title="Scegli Attività">🎤</button>
                    </div>
                </div>

                <div class="form-group-mobile">
                    <div class="mic-wrapper">
                        <label for="note">Note / Q.tà:</label>
                        <button type="button" class="mic-btn" id="btn_voice_note" onclick="toggleGenericVoice('note', 'textarea')" title="Detta Nota">🎤</button>
                    </div>
                    <textarea id="note" name="note" rows="4" placeholder="Note..."></textarea>
                </div>

                <div class="boolean-group">
                    <button type="button" class="btn-toggle" id="btn_peri">Pericolo</button>
                    <input type="hidden" name="ia_peri_hidden" id="ia_peri_hidden" value="0">
                    <button type="button" class="btn-toggle" id="btn_vreg">Vis Reg</button>
                    <input type="hidden" name="ia_vreg_hidden" id="ia_vreg_hidden" value="0">
                    <button type="button" class="btn-toggle" id="btn_op1">Scadenza</button>
                    <input type="hidden" name="ia_op1_hidden" id="ia_op1_hidden" value="0">
                    <button type="button" class="btn-toggle" id="btn_op2">Option 2</button>
                    <input type="hidden" name="ia_op2_hidden" id="ia_op2_hidden" value="0">
                </div>

                <div class="form-group-mobile">
                    <button type="button" id="btn_conferma_submit" class="btn-grande" disabled>REGISTRA</button>
                </div>
            </div>
        </form>

        <div id="storico" class="tab-content-item" style="display:none;">
            <div id="storico-attivita-content" class="table-bordered-scroll"><p>Caricamento...</p></div>
        </div>
    </div>

    <div class="version-footer"><?php echo $versione; ?></div>

<script>
// --- RICONOSCIMENTO VOCALE ---
let recognition;
let isRecording = false;
let currentTargetId = null;

function toggleGenericVoice(targetId, type) {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert("Browser non supportato per il vocale.");
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let btn;
    if (type === 'textarea') btn = document.getElementById('btn_voice_note');
    else btn = document.querySelector(`button[onclick="toggleGenericVoice('${targetId}', '${type}')"]`);

    if (isRecording && currentTargetId === targetId) {
        recognition.stop();
        return;
    }
    
    if (isRecording) { recognition.stop(); }

    recognition = new SpeechRecognition();
    recognition.lang = 'it-IT';
    recognition.continuous = false;
    recognition.interimResults = false;

    recognition.onstart = function() {
        isRecording = true;
        currentTargetId = targetId;
        if(btn) btn.classList.add('recording');
    };

    recognition.onend = function() {
        isRecording = false;
        currentTargetId = null;
        if(btn) btn.classList.remove('recording');
    };

    recognition.onresult = function(event) {
        let transcript = event.results[0][0].transcript.trim();
        const element = document.getElementById(targetId);

        if (type === 'text' || type === 'textarea') {
            if (element.value.length > 0) element.value += ' ' + transcript;
            else element.value = transcript;
            
            if (targetId === 'codice_arnia') {
                element.value = element.value.replace(/[^0-9a-zA-Z]/g, '');
                $(element).trigger('change');
            }
        } 
        else if (type === 'select') {
            let options = element.options;
            let found = false;
            transcript = transcript.toLowerCase();
            for (let i = 0; i < options.length; i++) {
                if (options[i].text.toLowerCase().includes(transcript)) {
                    element.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (found) { $(element).trigger('change'); }
            else { alert("Attività non trovata: " + transcript); }
        }
    };

    recognition.onerror = function(event) {
        console.error("Errore vocale:", event.error);
        isRecording = false;
        if(btn) btn.classList.remove('recording');
        if (event.error === 'not-allowed') alert("Microfono bloccato.");
    };

    recognition.start();
}

// --- FUNZIONI STANDARD ---
function editAttivita(id, data, note, peri, vreg, op1, op2, att_id) {
    openTab(null, 'inserimento');
    $('#ia_id_modifica').val(id);
    $('#label_id_mod').text(id);
    $('#data').val(data);
    $('#note').val(note);
    setToggleStatus('#btn_peri', '#ia_peri_hidden', peri);
    setToggleStatus('#btn_vreg', '#ia_vreg_hidden', vreg);
    setToggleStatus('#btn_op1', '#ia_op1_hidden', op1);
    setToggleStatus('#btn_op2', '#ia_op2_hidden', op2);
    $('#tipo_attivita').val(att_id).prop('disabled', true);
    if($('#tipo_attivita_hidden').length === 0) {
        $('#tipo_attivita').after('<input type="hidden" id="tipo_attivita_hidden" name="tipo_attivita" value="'+att_id+'">');
    }
    $('#alert_modifica').show();
    $('#btn_conferma_submit').text('AGGIORNA RECORD');
    window.scrollTo(0, 0);
}

function setToggleStatus(btnId, hiddenId, value) {
    if (parseInt(value) === 1) { $(btnId).addClass('active'); $(hiddenId).val('1'); } 
    else { $(btnId).removeClass('active'); $(hiddenId).val('0'); }
}

function cancelEdit() {
    $('#ia_id_modifica').val('');
    $('#alert_modifica').hide();
    $('#tipo_attivita').prop('disabled', false);
    $('#tipo_attivita_hidden').remove();
    $('#btn_conferma_submit').text('REGISTRA');
    const currentArniaId = $('#arnia_id_nascosto').val();
    $('#form_inserimento_attivita')[0].reset();
    $('#arnia_id_nascosto').val(currentArniaId);
    $('.btn-toggle').removeClass('active');
    $('input[type="hidden"][id$="_hidden"]').val('0');
}

function setupToggleButtons() {
    $('.boolean-group .btn-toggle').on('click', function() {
        const button = $(this);
        const hiddenInputId = '#' + button.attr('id').replace('btn', 'ia') + '_hidden';
        if (button.hasClass('active')) { button.removeClass('active'); $(hiddenInputId).val('0'); } 
        else { button.addClass('active'); $(hiddenInputId).val('1'); }
    });
    $('#btn_foto').on('click', function() { $('#foto_attivita').click(); });
    $('#foto_attivita').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        if (fileName) { $('#file_status').text('File: ' + fileName).css('color', 'red'); } else { $('#file_status').text(''); }
    });
}

function checkActiveScadenza(arniaId) {
    if ($('#ia_id_modifica').val() !== '') return;
    const btnOp1 = $('#btn_op1');
    btnOp1.removeClass('active').css('background-color', ''); 
    $('#ia_op1_hidden').val('0'); $('#scadenza_attiva_id').val('0');

    $.ajax({
        url: 'mobile.php', type: 'GET', dataType: 'json', 
        data: { ajax_check_scadenza: 1, arnia_id: arniaId },
        success: function(response) {
            if (response.active) { 
                $('#scadenza_attiva_id').val(response.sc_id); 
                btnOp1.text('CHIUDI: ' + response.tipo_descr);
                if(response.color) { btnOp1.css('background-color', response.color); }
            } else { 
                btnOp1.text('Scadenza'); btnOp1.css('background-color', '');
            }
        }
    });
}

function loadLatestAttivita() {
    const arniaId = $('#arnia_id_nascosto').val();
    if (!arniaId) return;
    $('#storico-attivita-content').html('<p>Caricamento...</p>');
    $.ajax({
        url: 'includes/load_attivita_mobile.php', 
        type: 'GET', data: { arnia_id: arniaId, limit: 20 },
        success: function(response) { $('#storico-attivita-content').html(response); }
    });
}

function openTab(evt, tabName) {
    $('.tab-content-item').hide(); $('.tab-button').removeClass('active');
    $('#' + tabName).show();
    if (evt) { $(evt.currentTarget).addClass('active'); } 
    else { if(tabName === 'storico') $('#storico-tab-btn').addClass('active'); else $('.tab-button').first().addClass('active'); }
}

function setupFormSubmit() {
    $('#tipo_attivita').on('change', function() {
        const isNota = $(this).find(':selected').data('is-nota');
        const arniaSelezionata = $('#arnia_id_nascosto').val() !== '';
        if (isNota == 1) {
            $('#btn_conferma_submit').prop('disabled', false); $('#codice_arnia').css('opacity', '0.5'); 
        } else {
            $('#codice_arnia').css('opacity', '1');
            if (!arniaSelezionata) { $('#btn_conferma_submit').prop('disabled', true); } else { $('#btn_conferma_submit').prop('disabled', false); }
        }
    });

    $('#btn_conferma_submit').on('click', function() {
        const isNota = $('#tipo_attivita').find(':selected').data('is-nota');
        if (isNota != 1 && $('#arnia_id_nascosto').val() === '') {
            alert("⚠️ Devi selezionare un'arnia valida."); return;
        }
        const textMsg = ($('#ia_id_modifica').val() !== '') ? "Aggiornare il record?" : "Registrare l'attività?";
        if (confirm(textMsg)) { $('#tipo_attivita').prop('disabled', false); $('#form_inserimento_attivita').submit(); } 
    });
}

$(document).ready(function() {
    setupToggleButtons(); setupFormSubmit();
    
    // --- NUOVA LOGICA: RIMANI SULLO STORICO DOPO ELIMINAZIONE ---
    const status = "<?php echo $status_get; ?>";
    const aidUrl = $('#arnia_id_nascosto').val();

    if (status === 'del_success' && aidUrl) {
        // Se arrivo da un'eliminazione, apro subito lo storico
        openTab(null, 'storico');
        loadLatestAttivita();
        // E poi carico il nome arnia in background
        $.ajax({
            url: 'search_arnia.php', type: 'GET', dataType: 'json', data: { id_diretto: aidUrl }, 
            success: function(response) {
                if (response.success) {
                    $('#arnia_nome_display').text(response.nome); $('#codice_arnia').val(response.codice);
                    $('#btn_conferma_submit').prop('disabled', false); checkActiveScadenza(aidUrl);
                }
            }
        });
    } 
    else if(aidUrl) {
        // Caricamento standard (es. dopo inserimento o refresh)
        $.ajax({
            url: 'search_arnia.php', type: 'GET', dataType: 'json', data: { id_diretto: aidUrl }, 
            success: function(response) {
                if (response.success) {
                    $('#arnia_nome_display').text(response.nome); $('#codice_arnia').val(response.codice);
                    $('#btn_conferma_submit').prop('disabled', false); checkActiveScadenza(aidUrl);
                    if(status === 'success') {
                        openTab(null, 'storico'); loadLatestAttivita();
                    }
                }
            }
        });
    }

    $('#codice_arnia').on('change', function() {
        const codice = $(this).val().trim();
        if (codice.length === 0) return;
        $.ajax({
            url: 'search_arnia.php', type: 'GET', dataType: 'json', data: { codice: codice },
            success: function(response) {
                if (response.success) {
                    $('#arnia_nome_display').text(response.nome); $('#arnia_id_nascosto').val(response.id);
                    const isNota = $('#tipo_attivita').find(':selected').data('is-nota');
                    if(isNota != 1) $('#btn_conferma_submit').prop('disabled', false); 
                    $('#data').focus(); checkActiveScadenza(response.id);
                } else { $('#arnia_nome_display').text('❌ NON TROVATA'); }
            }
        });
    });
});
</script>
</body>
</html>