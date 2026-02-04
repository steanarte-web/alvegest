<?php
// widgets/scad_tratt.php

// Caricamento CSS specifico per i widget (se non già caricato dalla dashboard principale)
echo '<link rel="stylesheet" href="' . TPL_URL . 'widget_style.css?v=' . time() . '">';

// 1. Recupero le scadenze aperte (SC_CHIUSO = 0)
// Ordinate per data di scadenza crescente (dalle più urgenti alle più lontane)
$sql_scadenze = "
    SELECT 
        S.SC_DATAF, 
        A.AR_CODICE, 
        A.AR_NOME, 
        T.AT_DESCR
    FROM TR_SCAD S
    JOIN AP_Arnie A ON S.SC_ARNIA = A.AR_ID
    JOIN TA_Attivita T ON S.SC_TATT = T.AT_ID
    WHERE S.SC_CHIUSO = 0
    ORDER BY S.SC_DATAF ASC
    LIMIT 10";

$res_scad = $conn->query($sql_scadenze);
?>

<div class="widget-card draggable" data-filename="scad_tratt.php" id="scad_tratt" 
     style="position: absolute !important; width: <?php echo $w_width; ?>; height: <?php echo $w_height; ?>; left: <?php echo $w_x; ?>; top: <?php echo $w_y; ?>;">
    
    <div class="widget-header">
        <div style="display: flex; flex-direction: column;">
            <span style="font-size: 0.75em; color: #666; font-weight: bold; text-transform: uppercase;">Trattamenti Attivi</span>
            <h4 style="margin:0; color:#333; font-size: 0.9em;">Scadenziario</h4>
        </div>
        <span class="drag-handle">⠿</span>
    </div>

    <div style="flex-grow: 1; overflow-y: auto; padding: 5px;">
        <?php if ($res_scad && $res_scad->num_rows > 0): ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85em;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid #eee; color: #666;">
                        <th style="padding: 4px;">Scadenza</th>
                        <th style="padding: 4px;">Arnia</th>
                        <th style="padding: 4px;">Trattamento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $oggi = new DateTime();
                    $oggi->setTime(0, 0, 0);

                    while ($row = $res_scad->fetch_assoc()): 
                        $scadenza = new DateTime($row['SC_DATAF']);
                        $scadenza->setTime(0, 0, 0);
                        
                        // Calcolo differenza giorni per il colore
                        $diff = $oggi->diff($scadenza);
                        $giorni_diff = (int)$diff->format("%r%a"); // %r mette il segno - se passato

                        // Logica colori (Coerente con mobile.php)
                        $style_data = "font-weight: bold;";
                        $bg_row = "";

                        if ($giorni_diff < 0) {
                            // SCADUTO (Rosso)
                            $style_data .= " color: #dc3545;"; // Rosso
                            $bg_row = "background-color: #fff5f5;";
                        } elseif ($giorni_diff <= 3) {
                            // IN SCADENZA < 4 gg (Giallo/Arancio)
                            $style_data .= " color: #e67e22;"; 
                            $bg_row = "background-color: #fffbf2;";
                        } else {
                            // FUTURO (Verde/Normale)
                            $style_data .= " color: #28a745;"; 
                        }
                    ?>
                        <tr style="border-bottom: 1px solid #f9f9f9; <?php echo $bg_row; ?>">
                            <td style="padding: 6px 4px; <?php echo $style_data; ?>">
                                <?php echo date('d/m', strtotime($row['SC_DATAF'])); ?>
                            </td>
                            
                            <td style="padding: 6px 4px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span class="widget-badge-arnia" style="background-color: #472014 !important; color: white !important; border: none !important; padding: 2px 6px; border-radius: 4px; font-size: 0.9em;">
                                        <?php echo htmlspecialchars($row['AR_CODICE']); ?>
                                    </span>
                                    <span style="font-size: 0.9em; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80px;">
                                        <?php echo htmlspecialchars($row['AR_NOME']); ?>
                                    </span>
                                </div>
                            </td>

                            <td style="padding: 6px 4px; color: #333;">
                                <?php echo htmlspecialchars($row['AT_DESCR']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #999; margin-top: 20px; font-size: 0.9em;">
                Nessun trattamento in scadenza.
            </p>
        <?php endif; ?>
    </div>

    <div style="margin-top: 10px; text-align: right;">
        <a href="mobile.php" style="font-size: 0.75em; color: #e12326; text-decoration: none; font-weight: bold;">Vai all'App Mobile &rarr;</a>
    </div>
</div>