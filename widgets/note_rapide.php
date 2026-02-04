<?php
// widgets/note_rapide.php
$utente_id = $_SESSION['user_id'] ?? 0;

// QUERY: Recupera le note e la foto associata (FO_TIAT='N')
$sql_notes = "SELECT N.*, 
              (SELECT FO_NOME FROM AT_FOTO WHERE FO_ATT = N.NW_ID AND FO_TIAT = 'N' LIMIT 1) as FO_NOME
              FROM CF_NOTE_WIDGET N
              WHERE N.NW_UTENTE_ID = 0 OR N.NW_UTENTE_ID = $utente_id 
              ORDER BY N.NW_DATA DESC";

$res_notes = $conn->query($sql_notes);
$note_list = [];
if ($res_notes) {
    while ($row = $res_notes->fetch_assoc()) { $note_list[] = $row; }
}
?>

<style>
    /* Stile per l'area di drop attiva */
    .note-drag-over {
        border: 2px dashed #28a745 !important;
        background-color: rgba(40, 167, 69, 0.1) !important;
        position: relative;
    }
    .note-drag-over::after {
        content: 'Rilascia qui la foto!';
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        color: #28a745;
        font-weight: bold;
        pointer-events: none;
        background: rgba(255,255,255,0.8);
        padding: 5px 10px;
        border-radius: 4px;
        z-index: 10;
    }
    .note-img-container {
        margin-top: 12px; 
        border-top: 1px dashed #ccc; 
        padding-top: 8px;
    }
    .note-img-thumb {
        height: 80px; 
        width: auto; 
        border-radius: 6px; 
        border: 2px solid white; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
        transition: transform 0.2s;
    }
    .note-img-thumb:hover {
        transform: scale(1.05);
    }
</style>

<div class="widget-card draggable widget-postit" data-file="note_rapide.php" 
     style="position: absolute; width: <?php echo $w_width; ?>; height: <?php echo $w_height; ?>; left: <?php echo $w_x; ?>; top: <?php echo $w_y; ?>; display: flex !important; flex-direction: column !important;">
    
    <div class="widget-header" style="flex-shrink: 0; margin-bottom: 0 !important;">
        <h3 style="font-size: 1.1em; text-align: left; margin: 0; flex-grow: 1;">NOTE</h3>
        <div style="display: flex; gap: 5px; align-items: center;">
            <button onclick="switchToAddNote(this)" class="widget-badge-arnia" title="Nuova">+</button>
            <button onclick="editCurrentNote(this)" class="widget-badge-arnia">✏️</button>
            <button onclick="deleteCurrentNote(this)" class="widget-badge-arnia" style="background: #e12326 !important; color: white !important;">✖</button>
            <span class="drag-handle">⠿</span>
        </div>
    </div>

    <div class="note-tabs-header">
        <?php foreach ($note_list as $index => $n): ?>
            <div class="note-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" 
                 id="tab-btn-<?php echo $n['NW_ID']; ?>"
                 data-id="<?php echo $n['NW_ID']; ?>"
                 data-titolo="<?php echo htmlspecialchars($n['NW_TITOLO']); ?>"
                 data-testo="<?php echo htmlspecialchars($n['NW_CONTENUTO']); ?>"
                 data-dest="<?php echo $n['NW_UTENTE_ID']; ?>"
                 onclick="showSpecificNote(this, 'note-body-<?php echo $n['NW_ID']; ?>')">
                <?php echo htmlspecialchars(mb_substr($n['NW_TITOLO'], 0, 6)) . (mb_strlen($n['NW_TITOLO']) > 6 ? '..' : ''); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="note-body-scroll">
        <div id="tab-note-form" class="note-body-content" style="display:none;">
            <input type="hidden" id="form_note_id" value="">
            <input type="text" id="form_note_title" placeholder="Titolo..." style="width:100%; font-weight:bold; background:transparent; border:none; border-bottom:1px solid #8b4513; margin-bottom:10px;">
            <textarea id="form_note_text" placeholder="Messaggio..." style="width:100%; height:100px; border:none; background:rgba(255,255,255,0.3); resize:vertical;"></textarea>
            <div style="display:flex; justify-content: space-between; margin-top:10px;">
                <select id="form_note_dest"><option value="<?php echo $utente_id; ?>">Privata</option><option value="0">Pubblica</option></select>
                <button onclick="salvaNotaDefinitiva()" class="widget-badge-arnia" style="background:#8b4513 !important; color:white !important;">SALVA</button>
            </div>
        </div>

        <?php foreach ($note_list as $index => $n): ?>
            <div id="note-body-<?php echo $n['NW_ID']; ?>" 
                 class="note-body-content drop-zone" 
                 data-note-id="<?php echo $n['NW_ID']; ?>"
                 style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>;">
                
                <span class="widget-badge-arnia note-badge-date"><?php echo date('d/m/Y H:i', strtotime($n['NW_DATA'])); ?></span>
                <div style="font-weight: bold; font-size: 1.1em; color: #8b4513; margin: 5px 0;"><?php echo htmlspecialchars($n['NW_TITOLO']); ?></div>
                <p class="note-text" style="white-space: pre-wrap;"><?php echo htmlspecialchars($n['NW_CONTENUTO']); ?></p>
                
                <?php if (!empty($n['FO_NOME'])): ?>
                    <div class="note-img-container">
                        <span style="font-size: 0.75em; color: #666; display:block; margin-bottom:4px;">Allegato:</span>
                        <a href="immagini/<?php echo htmlspecialchars($n['FO_NOME']); ?>" target="_blank" title="Clicca per ingrandire">
                            <img src="immagini/<?php echo htmlspecialchars($n['FO_NOME']); ?>" class="note-img-thumb" alt="Foto Nota">
                        </a>
                    </div>
                <?php else: ?>
                    <div style="margin-top:20px; font-size:0.8em; color:#999; text-align:center; border:1px dashed #ccc; padding:5px; border-radius:4px; pointer-events: none;">
                        Trascina qui una foto per allegarla
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// --- GESTIONE INTERFACCIA ---
function showSpecificNote(btn, bodyId) {
    const widget = btn.closest('.widget-card');
    widget.querySelectorAll('.note-body-content').forEach(c => c.style.display = 'none');
    widget.querySelectorAll('.note-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(bodyId).style.display = 'block';
    btn.classList.add('active');
}

function switchToAddNote(btn) {
    const widget = btn.closest('.widget-card');
    widget.querySelectorAll('.note-body-content').forEach(c => c.style.display = 'none');
    document.getElementById('form_note_id').value = ""; 
    document.getElementById('form_note_title').value = "";
    document.getElementById('form_note_text').value = "";
    document.getElementById('tab-note-form').style.display = 'block';
}

function editCurrentNote(btn) {
    const widget = btn.closest('.widget-card');
    const activeTab = widget.querySelector('.note-tab-btn.active');
    if (!activeTab) return;
    widget.querySelectorAll('.note-body-content').forEach(c => c.style.display = 'none');
    document.getElementById('form_note_id').value = activeTab.getAttribute('data-id');
    document.getElementById('form_note_title').value = activeTab.getAttribute('data-titolo');
    document.getElementById('form_note_text').value = activeTab.getAttribute('data-testo');
    document.getElementById('form_note_dest').value = activeTab.getAttribute('data-dest');
    document.getElementById('tab-note-form').style.display = 'block';
}

function deleteCurrentNote(btn) {
    const activeTab = btn.closest('.widget-card').querySelector('.note-tab-btn.active');
    if (!activeTab) return;
    if (confirm("Eliminare la nota selezionata? (La foto rimarrà in archivio)")) {
        fetch('includes/ajax/save_new_note.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: activeTab.getAttribute('data-id') })
        }).then(() => location.reload()); 
    }
}

function salvaNotaDefinitiva() {
    const data = {
        id: document.getElementById('form_note_id').value,
        titolo: document.getElementById('form_note_title').value,
        testo: document.getElementById('form_note_text').value,
        dest: document.getElementById('form_note_dest').value
    };
    if (!data.titolo || !data.testo) return;
    fetch('includes/ajax/save_new_note.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(() => location.reload());
}

// --- GESTIONE DRAG & DROP ---
document.querySelectorAll('.drop-zone').forEach(zone => {
    // 1. Entrata
    zone.addEventListener('dragover', function(e) {
        e.preventDefault(); e.stopPropagation();
        this.classList.add('note-drag-over');
    });

    // 2. Uscita
    zone.addEventListener('dragleave', function(e) {
        e.preventDefault(); e.stopPropagation();
        this.classList.remove('note-drag-over');
    });

    // 3. Rilascio
    zone.addEventListener('drop', function(e) {
        e.preventDefault(); e.stopPropagation();
        this.classList.remove('note-drag-over');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            const noteId = this.getAttribute('data-note-id');

            if (!file.type.startsWith('image/')) {
                alert("Puoi caricare solo immagini!");
                return;
            }
            uploadNoteImage(noteId, file);
        }
    });
});

function uploadNoteImage(noteId, file) {
    const formData = new FormData();
    formData.append('note_id', noteId);
    formData.append('image', file);

    console.log("Inizio upload per Nota ID:", noteId);

    fetch('includes/ajax/upload_note_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Controlla se la risposta è valida
        if (!response.ok) {
            throw new Error("Errore di rete o server: " + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            console.log("Upload completato!");
            location.reload();
        } else {
            console.error("Errore server:", data.error);
            alert("Errore caricamento: " + (data.error || "Sconosciuto"));
        }
    })
    .catch(error => {
        console.error('Errore Fetch:', error);
        alert("Errore di connessione durante l'upload. Controlla la console (F12) per i dettagli.");
    });
}
</script>