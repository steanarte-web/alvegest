<?php
// Versione: H.1.6 - Fix Scrollbar Dashboard
include 'includes/config.php'; 
include TPL_PATH . 'header.php'; 

$utente_id = $_SESSION['user_id'] ?? 0;
?>

<div class="main-content flex-layout">
    <div class="left-column"></div>
    <div class="center-column">
        <main>
            <h2>Dashboard Apiario</h2>
            <div class="widget-grid" id="grid-container" style="position: relative; width: 100%; min-height: 85vh; padding: 20px; border: 1px dashed #ccc; overflow: auto; -webkit-overflow-scrolling: touch; background-color: #f3f3f3;">
                <?php
                $config_widgets = [];
                $res_pos = $conn->query("SELECT * FROM CF_WIDGET_POS WHERE WP_UTENTE_ID = $utente_id");
                if ($res_pos) {
                    while($row = $res_pos->fetch_assoc()){
                        $config_widgets[$row['WP_WIDGET_NAME']] = $row;
                    }
                }

                $widget_folder = __DIR__ . '/widgets/';
                if (is_dir($widget_folder)) {
                    $all_files = glob($widget_folder . "*.php");
                    foreach ($all_files as $file) {
                        $p_name = basename($file);
                        
                        // Controllo Visibilità: Mostra se è 1 o se non è ancora nel database
                        $visibile = isset($config_widgets[$p_name]) ? (int)$config_widgets[$p_name]['WP_VISIBILE'] : 1;
                        
                        if ($visibile === 1) {
                            $w_width  = $config_widgets[$p_name]['WP_WIDTH'] ?? '300px';
                            $w_height = $config_widgets[$p_name]['WP_HEIGHT'] ?? '250px';
                            $w_x      = $config_widgets[$p_name]['WP_X'] ?? '10px';
                            $w_y      = $config_widgets[$p_name]['WP_Y'] ?? '10px';
                            
                            include($file);
                        }
                    }
                }
                ?>
            </div>
        </main>
    </div>
    <div class="right-column"></div>
</div>

<script>
    // 1. Assegnazione data-file (Invariata)
    document.querySelectorAll('.widget-card').forEach((card, index) => {
        const widgetFiles = <?php 
            $visibili_files = [];
            foreach(glob($widget_folder . "*.php") as $f) {
                $bn = basename($f);
                if (!isset($config_widgets[$bn]) || $config_widgets[$bn]['WP_VISIBILE'] == 1) $visibili_files[] = $bn;
            }
            echo json_encode($visibili_files); 
        ?>;
        if(widgetFiles[index]) {
            card.setAttribute('data-file', widgetFiles[index]);
        }
    });

    const grid = document.getElementById('grid-container');
    let activeWidget = null;
    let initialX, initialY, initialLeft, initialTop;

    // 2. INIZIO TRASCINAMENTO
    document.addEventListener('mousedown', function(e) {
        const handle = e.target.closest('.drag-handle');
        if (handle) {
            activeWidget = handle.closest('.widget-card');
            activeWidget.style.zIndex = 1000;
            initialX = e.clientX; 
            initialY = e.clientY;
            
            // Fix: Se non ha left/top definiti, usa la posizione calcolata corrente
            const computedStyle = window.getComputedStyle(activeWidget);
            initialLeft = parseInt(activeWidget.style.left) || parseInt(computedStyle.left) || 0;
            initialTop = parseInt(activeWidget.style.top) || parseInt(computedStyle.top) || 0;
            
            e.preventDefault();
        }
    });

    // 3. MOVIMENTO (CON SCROLL AUTOMATICO)
    document.addEventListener('mousemove', function(e) {
        if (!activeWidget) return;

        // Calcolo la nuova posizione teorica
        let newLeft = initialLeft + (e.clientX - initialX);
        let newTop = initialTop + (e.clientY - initialY);

        // --- INIZIO LOGICA CONFINI (CONTAINMENT) ---
        // Recupero dimensioni contenitore e widget
        // Usa scrollWidth/Height per considerare l'area scrollabile totale
        const containerWidth = grid.scrollWidth; 
        const containerHeight = grid.scrollHeight;
        const widgetWidth = activeWidget.offsetWidth;
        const widgetHeight = activeWidget.offsetHeight;

        // Limiti minimi
        if (newLeft < 0) newLeft = 0;
        if (newTop < 0) newTop = 0;
        
        // Limiti massimi: permettiamo di andare oltre la vista attuale per attivare lo scroll
        // Ma non oltre dimensioni irragionevoli se necessario.
        // Per ora lasciamo libero verso destra/basso per permettere l'espansione.
        
        // --- FINE LOGICA CONFINI ---

        // Applico la posizione corretta
        activeWidget.style.left = newLeft + 'px';
        activeWidget.style.top = newTop + 'px';
    });

    // 4. FINE TRASCINAMENTO
    document.addEventListener('mouseup', function() {
        if (activeWidget) {
            salvaStato(activeWidget);
            activeWidget.style.zIndex = 100; // Ripristina z-index normale
            activeWidget = null;
        }
    });

    // 5. SALVATAGGIO STATO
    function salvaStato(widget) {
        const data = {
            file: widget.getAttribute('data-file'),
            width: widget.style.width,
            height: widget.style.height,
            x: widget.style.left,
            y: widget.style.top
        };
        // Controllo che il file esista per evitare errori console
        if(data.file) {
            fetch('includes/ajax/save_widget_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }
    }

    // 6. FUNZIONE DI EMERGENZA (RESET)
    // Richiamata dal bottone rosso aggiunto prima
    function ResetWidgets() {
        if(!confirm("Vuoi riportare tutti i widget in alto a sinistra?")) return;
        
        const widgets = document.querySelectorAll('.widget-card');
        let currentTop = 10;
        
        widgets.forEach((w) => {
            w.style.left = '10px';
            w.style.top = currentTop + 'px';
            currentTop += 60; // Li impila uno sotto l'altro per non sovrapporli tutti
            salvaStato(w); // Salva la nuova posizione resettata
        });
        
        alert("Widget ripristinati! Ricarica la pagina se necessario.");
    }

    // 7. OBSERVER PER RESIZE (Invariato)
    const resizeObserver = new ResizeObserver(entries => {
        for (let entry of entries) {
            if (window.resizeInitDone) {
                clearTimeout(window.resizeTimeout);
                window.resizeTimeout = setTimeout(() => { salvaStato(entry.target); }, 500);
            }
        }
    });
    document.querySelectorAll('.widget-card').forEach(w => resizeObserver.observe(w));
    setTimeout(() => { window.resizeInitDone = true; }, 1000);
</script>

<?php include TPL_PATH . 'footer.php'; ?>