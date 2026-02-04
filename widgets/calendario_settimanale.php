<?php
// widgets/calendario_settimanale.php
define('WIDGET_VERSION', 'V.FINAL.HANDLE.RESTORED');

// 1. CONFIGURAZIONE E CONNESSIONE
// -----------------------------------------------------------
error_reporting(0); 
ini_set('display_errors', 0);

$paths = [__DIR__ . '/../includes/config.php', dirname(__DIR__) . '/includes/config.php'];
foreach ($paths as $p) { if (file_exists($p)) { include_once $p; break; } }

// -----------------------------------------------------------
// 2. GESTIONE SALVATAGGIO NOTE (CHIAMATA AJAX POST)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'save_note') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    
    $data_nota = $_POST['data'];
    $testo_nota = isset($_POST['note']) ? trim($_POST['note']) : '';
    $azione = $_POST['mode']; 

    if ($azione == 'delete') {
        $stmt = $conn->prepare("DELETE FROM WI_NOTE WHERE data = ?");
        $stmt->bind_param("s", $data_nota);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("REPLACE INTO WI_NOTE (data, note) VALUES (?, ?)");
        $testo_clean = substr($testo_nota, 0, 256);
        $stmt->bind_param("ss", $data_nota, $testo_clean);
        $stmt->execute();
    }
    
    echo "OK";
    exit();
}

// -----------------------------------------------------------
// 3. LOGICA CALENDARIO
// -----------------------------------------------------------
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$params = $_GET; 
$params['offset'] = $offset - 1; $link_prev = '?' . http_build_query($params);
$params['offset'] = $offset + 1; $link_next = '?' . http_build_query($params);
$params['offset'] = 0;           $link_today = '?' . http_build_query($params);

$lunedi = new DateTime();
$lunedi->modify('monday this week');
if ($offset != 0) {
    $mod = ($offset > 0 ? "+" : "") . $offset . " weeks";
    $lunedi->modify($mod);
}

$iter_date = clone $lunedi;
$path_eventi = __DIR__ . '/cal_events/';
$moduli_disponibili = is_dir($path_eventi) ? glob($path_eventi . "*.php") : [];

// 4. OUTPUT INTERFACCIA
// -----------------------------------------------------------
$ws_w = $w_width ?? '100%'; $ws_h = $w_height ?? '350px'; 
$ws_x = $w_x ?? '0px'; $ws_y = $w_y ?? '0px';

echo '<link rel="stylesheet" href="' . (defined('TPL_URL') ? TPL_URL : '') . 'widget_style.css?v=' . time() . '">';
?>

<style>
    /* STILI BASE */
    #calendario_settimanale_box .weekly-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); gap: 6px; height: 100%; padding: 5px; box-sizing: border-box; }
    #calendario_settimanale_box .day-card { border-radius: 6px; border-width: 2px; border-style: solid; display: flex; flex-direction: column; overflow: hidden; background: white; min-height: 0; }
    #calendario_settimanale_box .day-header { text-align: center; font-weight: bold; font-size: 0.85em; padding: 3px 0; border-bottom: 2px solid; text-transform: uppercase; font-family: sans-serif; cursor: pointer; transition: background 0.2s; }
    #calendario_settimanale_box .day-header:hover { filter: brightness(0.95); text-decoration: underline; }
    #calendario_settimanale_box .day-content { flex-grow: 1; display: flex; flex-direction: column; background: white; }
    
    #calendario_settimanale_box .cal-slot { flex: 1; border-bottom: 1px solid #eee; display: flex; align-items: center; padding: 4px; font-size: 0.75em; overflow: hidden; white-space: nowrap; cursor: pointer; min-height: 20px; }
    #calendario_settimanale_box .cal-slot:last-child { border-bottom: none; }
    #calendario_settimanale_box .cal-slot:hover { background-color: #f9f9f9; }
    
    /* NAVIGAZIONE */
    .cal-nav-container { display: flex; align-items: center; gap: 5px; }
    .cal-btn-arrow { text-decoration: none; font-size: 1.2em; color: #e12326; padding: 0 5px; cursor: pointer; font-weight: bold; }
    .cal-btn-today { text-decoration: none; font-size: 0.75em; text-transform: uppercase; background: #eee; color: #333; padding: 2px 8px; border-radius: 4px; font-weight: bold; border: 1px solid #ccc; }
    .cal-btn-today:hover { background: #e12326; color: white; border-color: #e12326; }

    /* MODALE */
    dialog::backdrop { background: rgba(0, 0, 0, 0.5); }
    dialog { border: none; border-radius: 8px; padding: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 350px; max-width: 90%; }
    .modal-header { padding: 15px; background: #f5f5f5; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 15px; background: #f5f5f5; border-top: 1px solid #ddd; text-align: right; display: flex; justify-content: flex-end; gap: 10px; }
    
    .btn-modal { padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; }
    .btn-cancel { background: #ccc; color: #333; }
    .btn-save { background: #28a745; color: white; }
    .btn-delete { background: #dc3545; color: white; }
    
    @media (max-width: 768px) { #calendario_settimanale_box .weekly-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="widget-card draggable" data-filename="calendario_settimanale.php" id="calendario_settimanale_box" 
     style="position: absolute !important; width: <?php echo $ws_w; ?>; height: <?php echo $ws_h; ?>; left: <?php echo $ws_x; ?>; top: <?php echo $ws_y; ?>;">
    
    <div class="widget-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin:0; color:#333; font-size: 0.9em;">
            PLANNING 
            <span style="font-weight:normal; font-size:0.9em; color:#e12326; margin-left:5px;"><?php echo date('d/m/Y'); ?></span>
            <?php if($offset != 0): ?>
                <span style="font-weight:normal; font-size:0.8em; color:gray;">(Sett. <?php echo ($offset > 0 ? "+$offset" : $offset); ?>)</span>
            <?php endif; ?>
        </h4>
        
        <div class="cal-nav-container">
            <a href="<?php echo $link_prev; ?>" class="cal-btn-arrow" onclick="CalSett_SoftLoad(event, this.href)">◀</a>
            <a href="<?php echo $link_today; ?>" class="cal-btn-today" onclick="CalSett_SoftLoad(event, this.href)">Oggi</a>
            <a href="<?php echo $link_next; ?>" class="cal-btn-arrow" onclick="CalSett_SoftLoad(event, this.href)">▶</a>
            
            <span class="drag-handle" style="cursor: move; font-size: 1.2em; color: #999; margin-left: 8px;" title="Sposta widget">⠿</span>
        </div>
    </div>

    <div style="flex-grow: 1; overflow-y: auto; position: relative;">
        <div class="weekly-grid">
            <?php
            $config_giorni = [
                ['label' => 'Lunedì', 'c' => '#e74c3c'], ['label' => 'Martedì', 'c' => '#e67e22'],
                ['label' => 'Mercoledì', 'c' => '#f1c40f'], ['label' => 'Giovedì', 'c' => '#27ae60'],
                ['label' => 'Venerdì', 'c' => '#8e44ad'], ['label' => 'Sabato', 'c' => '#ff69b4'],
                ['label' => 'Domenica', 'c' => '#8d6e63'], ['label' => 'Note', 'c' => '#16a085']
            ];

            for ($i = 0; $i < 8; $i++) {
                $conf = $config_giorni[$i];
                $CURRENT_DATE_ISO = ($i < 7) ? $iter_date->format('Y-m-d') : "note";
                
                $existing_note = "";
                if ($i < 7) {
                    $q_note = "SELECT note FROM WI_NOTE WHERE data = '$CURRENT_DATE_ISO'";
                    $r_note = $conn->query($q_note);
                    if ($r_note && $r_note->num_rows > 0) {
                        $row_n = $r_note->fetch_assoc();
                        $existing_note = htmlspecialchars($row_n['note'], ENT_QUOTES);
                    }
                }

                $boxTitle = $conf['label'];
                if ($i < 7) {
                    $boxTitle .= " <span style='color:#333; font-weight:normal;'>(" . $iter_date->format('d/m') . ")</span>";
                }
                
                $jsAction = "onclick=\"CalSett_OpenModal('$CURRENT_DATE_ISO', '{$conf['label']}', '$existing_note')\"";

                $slotsHTML = "";

                if ($i < 7) {
                    for ($r = 0; $r < 4; $r++) {
                        $contenuto = "";
                        $id_slot = "slot_" . $CURRENT_DATE_ISO . "_" . $r;
                        $conta_file = 0;

                        if (!empty($moduli_disponibili)) {
                            foreach ($moduli_disponibili as $file_modulo) {
                                if ($conta_file == $r) {
                                    $OUTPUT_OBJ = ""; 
                                    ob_start();
                                    include $file_modulo; 
                                    $out_buffer = ob_get_clean();

                                    if (!empty($OUTPUT_OBJ)) {
                                        $contenuto = (is_array($OUTPUT_OBJ) && isset($OUTPUT_OBJ['text'])) ? $OUTPUT_OBJ['text'] : $OUTPUT_OBJ;
                                    } elseif (!empty($out_buffer)) {
                                        $contenuto = $out_buffer;
                                    }
                                    $conta_file++; 
                                    break; 
                                }
                                $conta_file++; 
                            }
                        }

                        if (!empty($contenuto) && trim($contenuto) !== '') {
                            $slotsHTML .= '<div class="cal-slot" id="'.$id_slot.'">&nbsp;' . $contenuto . '</div>';
                        }
                    }
                    $iter_date->modify('+1 day');
                } else {
                    $slotsHTML .= '<div class="cal-slot" style="border:none;">&nbsp;</div>';
                }

                echo '<div class="day-card" style="border-color: '.$conf['c'].'">
                        <div class="day-header" '.$jsAction.' style="color:'.$conf['c'].'; border-bottom-color:'.$conf['c'].'">'.$boxTitle.'</div>
                        <div class="day-content">'.$slotsHTML.'</div>
                      </div>';
            }
            ?>
        </div>
    </div>
</div>

<dialog id="CalSett_Modal">
    <div class="modal-header">
        <strong id="modal_title">Gestione Giorno</strong>
        <span onclick="document.getElementById('CalSett_Modal').close()" style="cursor:pointer; font-size:1.2em;">&times;</span>
    </div>
    <div class="modal-body">
        <p style="margin-top:0; color:#666;">Data: <b id="modal_date_display"></b></p>
        
        <label style="display:block; margin-bottom:5px; font-weight:bold;">Nota:</label>
        <textarea id="modal_note_input" rows="4" maxlength="256" style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:sans-serif;" placeholder="Scrivi qui..."></textarea>
        <div style="text-align:right; font-size:0.8em; color:#999;">Max 256 caratteri</div>
        
        <input type="hidden" id="modal_date_iso">
    </div>
    <div class="modal-footer">
        <button id="btn_delete" type="button" class="btn-modal btn-delete" style="display:none;" onclick="CalSett_SaveNote('delete')">Elimina</button>
        
        <button type="button" class="btn-modal btn-cancel" onclick="document.getElementById('CalSett_Modal').close()">Annulla</button>
        <button type="button" class="btn-modal btn-save" onclick="CalSett_SaveNote('insert')">Inserisci</button>
    </div>
</dialog>

<script>
var CalSett_BaseURL = '<?php echo $_SERVER['PHP_SELF']; ?>'; 

function CalSett_OpenModal(isoDate, label, existingNote) {
    var modal = document.getElementById('CalSett_Modal');
    document.getElementById('modal_title').innerText = label;
    document.getElementById('modal_date_display').innerText = isoDate;
    document.getElementById('modal_date_iso').value = isoDate;
    document.getElementById('modal_note_input').value = existingNote;
    
    var btnDelete = document.getElementById('btn_delete');
    if (existingNote && existingNote.trim() !== "") {
        btnDelete.style.display = 'inline-block';
    } else {
        btnDelete.style.display = 'none';
    }
    modal.showModal();
}

function CalSett_SaveNote(mode) {
    var isoDate = document.getElementById('modal_date_iso').value;
    var noteText = document.getElementById('modal_note_input').value;
    
    var formData = new FormData();
    formData.append('ajax_action', 'save_note');
    formData.append('data', isoDate);
    formData.append('note', noteText);
    formData.append('mode', mode);

    fetch(CalSett_BaseURL, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if(result.includes("OK")) {
            document.getElementById('CalSett_Modal').close();
            CalSett_SoftLoad(new Event('click'), window.location.href);
        } else {
            alert("Errore salvataggio: " + result);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Errore di connessione.");
    });
}

function CalSett_SoftLoad(e, url) {
    if(e) e.preventDefault(); 
    var box = document.getElementById('calendario_settimanale_box');
    box.style.opacity = '0.5'; 
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newContent = doc.getElementById('calendario_settimanale_box').innerHTML;
            box.innerHTML = newContent;
            box.style.opacity = '1';
            if(url !== window.location.href) {
                window.history.pushState({}, '', url);
            }
        })
        .catch(err => {
            console.error('Errore soft load', err);
            window.location.href = url; 
        });
}
</script>