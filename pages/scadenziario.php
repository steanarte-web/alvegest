<?php
// DEFINIZIONE VERSIONE FILE
define('FILE_VERSION', 'S.0.4 - Logica Originale Ripristinata');

require_once '../includes/config.php';
require_once TPL_PATH . 'header.php'; 

// Data odierna per il confronto
$oggi = date('Y-m-d');

$sql = "SELECT S.SC_ID, S.SC_DINIZIO, S.SC_DATAF, S.SC_AVA, A.AR_ID, A.AR_CODICE, A.AR_NOME, T.AT_DESCR
        FROM TR_SCAD S
        JOIN AP_Arnie A ON S.SC_ARNIA = A.AR_ID
        JOIN TA_Attivita T ON S.SC_TATT = T.AT_ID
        WHERE S.SC_CHIUSO = 0
        ORDER BY S.SC_DATAF ASC, A.AR_CODICE ASC";

$result = $conn->query($sql);
?>

<main class="main-content">
    <div class="left-column"></div>

    <div class="center-column"> 
        <h2>Scadenziario Trattamenti</h2>

        <div class="tabs-container" style="margin-top:0;">
            <ul class="tabs-menu">
                <li class="tab-link active" id="tab-link-attivi" onclick="openTab(event, 'attivi')">Trattamenti Attivi</li>
            </ul>
            
            <div id="attivi" class="tab-content active">
                <div class="table-container">
                    <?php if ($result && $result->num_rows > 0): ?>
                    <table class="selectable-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Arnia</th>
                                <th class="txt-center">Settimana</th>
                                <th class="col-auto">Tipo Trattamento</th>
                                <th>Scadenza</th>
                                <th class="txt-center">Stato</th>
                                <th class="txt-center">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): 
                                $data_scadenza = $row["SC_DATAF"];
                                $differenza = (strtotime($oggi) - strtotime($data_scadenza)) / 86400;

                                if ($data_scadenza < $oggi) {
                                    if ($differenza <= 4) {
                                        $status_text = "RITARDO < 4 GIORNI";
                                        $status_style = "background-color: #fff3cd; color: #856404;";
                                    } else {
                                        $status_text = "RITARDO > 4 GIORNI";
                                        $status_style = "background-color: #f8d7da; color: #721c24;";
                                    }
                                } else {
                                    $status_text = "IN CORSO";
                                    $status_style = "background-color: #d4edda; color: #155724;";
                                }
                            ?>
                            <tr>
                                <td><?php echo $row["SC_ID"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($row["AR_CODICE"] . " - " . $row["AR_NOME"]); ?></strong></td>
                                <td class="txt-center"><?php echo $row["SC_AVA"]; ?></td>
                                <td class="col-auto"><?php echo htmlspecialchars($row["AT_DESCR"]); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($data_scadenza)); ?></td>
                                <td class="txt-center" style="font-weight: bold; <?php echo $status_style; ?> border-radius: 4px;">
                                    <?php echo $status_text; ?>
                                </td>
                                <td class="txt-center">
                                    <a href="gesttratt.php?arnia_id=<?php echo $row['AR_ID']; ?>&tab=attivita" class="btn-tabella-modifica">Modifica</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="txt-center p-vuoto">Nessun trattamento attivo o scaduto.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="versione-info">Versione: <?php echo FILE_VERSION; ?></div>
    </div>

    <div class="right-column"></div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function openTab(evt, tabName) {
    $('.tab-content').hide().removeClass('active');
    $('.tab-link').removeClass('active');
    $('#' + tabName).show().addClass('active');
    if(evt) {
        $(evt.currentTarget).addClass('active');
    } else {
        $('#tab-link-' + tabName).addClass('active');
    }
}

$(document).ready(function() {
    openTab(null, 'attivi');
});
</script>

<?php require_once TPL_PATH . 'footer.php'; ?>