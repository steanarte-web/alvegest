<?php
// DEFINIZIONE VERSIONE FILE
define('FILE_VERSION', 'V.0.0.7 - Admin Only Delete');

require_once '../includes/config.php';

// --- 0. CONTROLLO PERMESSI ---
// Recupera il ruolo dalla sessione (Adatta 'AC_ruolo' se usi un nome diverso nel login)
$ruolo_sessione = $_SESSION['ruolo'] ?? $_SESSION['AC_ruolo'] ?? '';
// È admin se il ruolo è 'admin' o 'amministratore' (case insensitive)
$isAdmin = in_array(strtolower($ruolo_sessione), ['admin', 'amministratore']);

// --- 1. LOGICA CRUD IN ALTO ---

// GESTIONE ELIMINAZIONE FORZATA (CANCELLAZIONE A CASCATA)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['conferma_elimina_forzata'])) {
    
    // PROTEZIONE LATO SERVER: Se non è admin, blocca tutto.
    if (!$isAdmin) {
        header("Location: attivita.php?status=error_perm&tab=tab-lista");
        exit();
    }

    $id_del = (int)$_POST['id_da_eliminare'];
    
    $conn->begin_transaction();

    try {
        // 1. Elimina dalle Attività Registrate (Storico)
        $conn->query("DELETE FROM AT_INSATT WHERE IA_ATT = $id_del");

        // 2. Elimina dai Trattamenti Fasi (Dettagli fasi)
        $conn->query("DELETE FROM TR_FFASE WHERE TF_ATT = $id_del");

        // 3. Elimina dallo Scadenziario
        $conn->query("DELETE FROM TR_SCAD WHERE SC_TATT = $id_del");

        // 4. Infine, elimina la Tipologia di Attività (Padre)
        $stmt = $conn->prepare("DELETE FROM TA_Attivita WHERE AT_ID = ?");
        $stmt->bind_param("i", $id_del);
        $stmt->execute();

        $conn->commit();
        
        header("Location: attivita.php?status=del_success&tab=tab-lista");
        exit();

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $messaggio = "<p class='errore'>Errore durante l'eliminazione: " . $exception->getMessage() . "</p>";
    }
}

// SALVATAGGIO (Inserimento o Modifica)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salva_attivita'])) {
    $id = $_POST['id'] ?? null;
    $descrizione = trim($_POST["descrizione"] ?? '');
    $note = trim($_POST["note"] ?? '');
    $nr_ripetizioni = (int)$_POST["nr_ripetizioni"];
    $gg_validita = (int)$_POST["gg_validita"];
    $is_trattamento = isset($_POST["is_trattamento"]) ? 1 : 0;
    $at_mag_id = !empty($_POST["at_mag_id"]) ? (int)$_POST["at_mag_id"] : null;
    $at_scarico_fisso = isset($_POST["at_scarico_fisso"]) ? 1 : 0;
    $at_is_nota = isset($_POST["at_is_nota"]) ? 1 : 0;

    if ($id) {
        $sql = "UPDATE TA_Attivita SET AT_DESCR=?, AT_NOTE=?, AT_NR=?, AT_GG=?, AT_TRAT=?, AT_MAG_ID=?, AT_SCARICO_FISSO=?, AT_IS_NOTA=? WHERE AT_ID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiiii", $descrizione, $note, $nr_ripetizioni, $gg_validita, $is_trattamento, $at_mag_id, $at_scarico_fisso, $at_is_nota, $id);
    } else {
        $sql = "INSERT INTO TA_Attivita (AT_DESCR, AT_NOTE, AT_NR, AT_GG, AT_TRAT, AT_MAG_ID, AT_SCARICO_FISSO, AT_IS_NOTA) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiii", $descrizione, $note, $nr_ripetizioni, $gg_validita, $is_trattamento, $at_mag_id, $at_scarico_fisso, $at_is_nota);
    }

    if ($stmt->execute()) {
        $status = $id ? "update_success" : "insert_success";
        header("Location: attivita.php?status=$status&tab=tab-lista");
        exit();
    }
}

require_once TPL_PATH . 'header.php';

$messaggio_display = "";
if (isset($_GET['status'])) {
    $st = $_GET['status'];
    if ($st == 'insert_success') $messaggio_display = "<p class='successo'>Attività inserita correttamente!</p>";
    if ($st == 'update_success') $messaggio_display = "<p class='successo'>Dati aggiornati!</p>";
    if ($st == 'del_success') $messaggio_display = "<p class='successo txt-danger font-bold'>Attività ed elementi collegati rimossi definitivamente.</p>";
    if ($st == 'error_perm') $messaggio_display = "<p class='errore'>⛔ Azione non consentita. Solo gli amministratori possono eliminare.</p>";
}
if(isset($messaggio)) $messaggio_display = $messaggio;

$edit_id = $_GET['edit'] ?? null;
$row_edit = ['AT_ID'=>'','AT_DESCR'=>'','AT_NOTE'=>'','AT_NR'=>0,'AT_GG'=>0,'AT_TRAT'=>0,'AT_MAG_ID'=>'','AT_SCARICO_FISSO'=>0, 'AT_IS_NOTA'=>0];
if ($edit_id) {
    $res = $conn->query("SELECT * FROM TA_Attivita WHERE AT_ID = $edit_id");
    if ($row = $res->fetch_assoc()) $row_edit = $row;
}
?>

<main class="main-content">
    <div class="left-column"></div>
    <div class="center-column">
        <h2 class="titolo-arnie">Gestione Attività</h2>
        <?php echo $messaggio_display; ?>

        <div class="tabs-container">
            <ul class="tabs-menu">
                <li class="tab-link <?php echo !$edit_id ? 'active' : ''; ?>" id="link-tab-lista" onclick="openTab(event, 'tab-lista')">ELENCO</li>
                <li class="tab-link <?php echo $edit_id ? 'active' : ''; ?>" id="link-tab-form" onclick="openTab(event, 'tab-form')">
                    <?php echo $edit_id ? "MODIFICA" : "NUOVA"; ?>
                </li>
            </ul>

            <div id="tab-lista" class="tab-content <?php echo !$edit_id ? 'active' : ''; ?>">
                <div class="table-container">
                    <table class="selectable-table">
                        <thead>
                            <tr>
                                <th>DESCRIZIONE</th>
                                <th class="txt-center">TRATT.</th>
                                <th class="txt-center">NOTA</th>
                                <th>MAGAZZINO</th>
                                <th class="txt-center">AZIONI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $lista = $conn->query("SELECT T.*, M.TM_Descrizione FROM TA_Attivita T LEFT JOIN TA_MAG M ON T.AT_MAG_ID = M.ID ORDER BY AT_DESCR ASC");
                            while ($r = $lista->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="font-bold"><?php echo htmlspecialchars($r['AT_DESCR']); ?></td>
                                <td class="txt-center"><?php echo ($r['AT_TRAT'] == 1) ? '✅' : ''; ?></td>
                                <td class="txt-center"><?php echo (isset($r['AT_IS_NOTA']) && $r['AT_IS_NOTA'] == 1) ? '📝' : ''; ?></td>
                                <td class="txt-small txt-muted"><?php echo htmlspecialchars($r['TM_Descrizione'] ?? 'Nessuno'); ?></td>
                                <td class="txt-center">
                                    <a href="attivita.php?edit=<?php echo $r['AT_ID']; ?>&tab=tab-form" class="btn-tabella-modifica">Modifica</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-form" class="tab-content <?php echo $edit_id ? 'active' : ''; ?>">
                <div class="form-container">
                    <form action="attivita.php" method="post">
                        <input type="hidden" name="id" value="<?php echo $row_edit['AT_ID']; ?>">
                        
                        <div class="form-group">
                            <label>Descrizione attività:</label>
                            <input type="text" name="descrizione" value="<?php echo htmlspecialchars($row_edit['AT_DESCR']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Sottomastro (Magazzino):</label>
                            <select name="at_mag_id">
                                <option value="">-- Nessun collegamento --</option>
                                <?php
                                $sm_res = $conn->query("SELECT ID, TM_Mastro, TM_SMastro, TM_Descrizione FROM TA_MAG WHERE TM_SMastro > 0 ORDER BY TM_Mastro, TM_SMastro");
                                while($sm = $sm_res->fetch_assoc()) {
                                    $s = ($row_edit['AT_MAG_ID'] == $sm['ID']) ? 'selected' : '';
                                    echo "<option value='{$sm['ID']}' $s>[{$sm['TM_Mastro']}.{$sm['TM_SMastro']}] {$sm['TM_Descrizione']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group-checkbox">
                            <input type="checkbox" name="at_is_nota" id="chk_nota" value="1" <?php echo (isset($row_edit['AT_IS_NOTA']) && $row_edit['AT_IS_NOTA'] == 1) ? 'checked' : ''; ?>>
                            <label for="chk_nota">È una nota</label>
                        </div>

                        <div class="form-group-checkbox">
                            <input type="checkbox" name="at_scarico_fisso" id="chk_f" value="1" <?php echo ($row_edit['AT_SCARICO_FISSO'] == 1) ? 'checked' : ''; ?>>
                            <label for="chk_f">Scarico fisso (1 unità)</label>
                        </div>

                        <div class="btn-group-flex">
                            <div class="form-group btn-flex-1">
                                <label>N. Ripetizioni:</label>
                                <input type="number" name="nr_ripetizioni" value="<?php echo $row_edit['AT_NR']; ?>">
                            </div>
                            <div class="form-group btn-flex-1">
                                <label>Validità (giorni):</label>
                                <input type="number" name="gg_validita" value="<?php echo $row_edit['AT_GG']; ?>">
                            </div>
                        </div>

                        <div class="form-group-checkbox">
                            <input type="checkbox" name="is_trattamento" id="chk_t" value="1" <?php echo ($row_edit['AT_TRAT'] == 1) ? 'checked' : ''; ?>>
                            <label for="chk_t">È un trattamento sanitario</label>
                        </div>

                        <div class="form-group">
                            <label>Note aggiuntive:</label>
                            <textarea name="note" rows="3"><?php echo htmlspecialchars($row_edit['AT_NOTE']); ?></textarea>
                        </div>

                        <div class="btn-group-flex">
                            <button type="submit" name="salva_attivita" class="btn btn-salva btn-flex-2">
                                <?php echo $edit_id ? "Salva Modifiche" : "Inserisci Attività"; ?>
                            </button>
                            
                            <?php if($edit_id): ?>
                                <?php if($isAdmin): ?>
                                    <button type="button" class="btn btn-elimina btn-flex-1" onclick="apriModalElimina(<?php echo $edit_id; ?>)">Elimina</button>
                                <?php endif; ?>
                                <a href="attivita.php" class="btn btn-annulla btn-flex-1">Annulla</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="versione-info">Versione: <?php echo FILE_VERSION; ?></div>
    </div>
    <div class="right-column"></div>
</main>

<div id="modalElimina" class="modal-custom" style="display:none;">
    <div class="modal-content-small">
        <h3 class="font-bold txt-danger">⚠️ ATTENZIONE</h3>
        <p>Stai per eliminare una Tipologia di Attività.</p>
        <p>Questa operazione <strong>ELIMINERÀ TUTTE LE ATTIVITÀ REGISTRATE</strong> nello storico, i trattamenti e le scadenze collegati a questa tipologia.</p>
        <p class="font-bold">Sei sicuro di voler procedere?</p>
        
        <form action="attivita.php" method="POST">
            <input type="hidden" name="conferma_elimina_forzata" value="1">
            <input type="hidden" name="id_da_eliminare" id="input_id_da_eliminare" value="">
            
            <div class="btn-group-flex">
                <button type="submit" class="btn btn-elimina btn-flex-2">SÌ, ELIMINA TUTTO</button>
                <button type="button" class="btn btn-annulla btn-flex-1" onclick="$('#modalElimina').hide();">NO</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function openTab(evt, tabName) {
    $('.tab-content').hide().removeClass('active');
    $('.tab-link').removeClass('active');
    $('#' + tabName).show().addClass('active');
    if (evt) $(evt.currentTarget).addClass('active');
}

function apriModalElimina(id) {
    $('#input_id_da_eliminare').val(id);
    $('#modalElimina').show();
}

$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if(tabParam === 'tab-form' || <?php echo $edit_id ? 'true' : 'false'; ?>) {
        openTab(null, 'tab-form');
    } else {
        openTab(null, 'tab-lista');
    }
});
</script>

<?php require_once TPL_PATH . 'footer.php'; ?>