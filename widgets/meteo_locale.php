<?php
// widgets/meteo_locale.php

// 1. RECUPERO APIARI DAL DATABASE (Usa i nuovi campi AI_LAT e AI_LON)
// Selezioniamo solo gli apiari che hanno le coordinate compilate
$sql_apiari = "SELECT AI_LUOGO, AI_LAT, AI_LON FROM TA_Apiari WHERE AI_LAT IS NOT NULL AND AI_LAT != '' AND AI_LON IS NOT NULL AND AI_LON != ''";
$res_api = $conn->query($sql_apiari);

$apiari = [];

if ($res_api && $res_api->num_rows > 0) {
    while($row = $res_api->fetch_assoc()) {
        $apiari[] = [
            'nome' => $row['AI_LUOGO'],
            'lat'  => $row['AI_LAT'],
            'lon'  => $row['AI_LON']
        ];
    }
}

// Fallback: Se non hai ancora inserito coordinate nel DB, usa un default per non rompere il widget
if (empty($apiari)) {
    $apiari[] = [
        'nome' => 'Magliano Alpi (Default)',
        'lat'  => '44.44', 
        'lon'  => '7.80'  
    ];
}

// 2. STILI WIDGET
echo '<link rel="stylesheet" href="' . (defined('TPL_URL') ? TPL_URL : '') . 'widget_style.css?v=' . time() . '">';
?>

<style>
    /* Contenitore principale colorato in base al meteo */
    .meteo-box {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 10px;
        color: white;
        border-radius: 8px;
        background: linear-gradient(135deg, #4fc3f7 0%, #2196f3 100%);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: background 0.5s ease;
    }

    /* Parte Superiore: Meteo Attuale */
    .meteo-current {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .meteo-temp-big {
        font-size: 2.2em;
        font-weight: bold;
        line-height: 1;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    }
    .meteo-icon { font-size: 2.8em; margin-right: 10px; }
    .meteo-details { font-size: 0.85em; text-align: right; }
    .meteo-wind { background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; margin-top: 5px; display: inline-block; }

    /* Parte Inferiore: Previsioni 6 Giorni */
    .forecast-container {
        display: flex;
        justify-content: space-between;
        border-top: 1px solid rgba(255,255,255,0.3);
        padding-top: 8px;
    }
    .forecast-item {
        text-align: center;
        flex: 1;
        font-size: 0.75em;
    }
    .forecast-day { opacity: 0.9; margin-bottom: 2px; text-transform: uppercase; font-size: 0.9em; }
    .forecast-icon { font-size: 1.4em; margin: 2px 0; }
    .forecast-temp { font-weight: bold; }

    /* Bottoni Apiari (Tabs) - Stile Scrollabile Orizzontale */
    .location-tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 8px;
        overflow-x: auto; 
        padding-bottom: 4px;
        scrollbar-width: thin; 
        scrollbar-color: #ccc transparent;
    }
    .location-tabs::-webkit-scrollbar { height: 4px; }
    .location-tabs::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }

    .tab-btn {
        background: #eee;
        border: 1px solid #ccc;
        border-radius: 15px;
        padding: 4px 10px;
        font-size: 0.75em;
        cursor: pointer;
        white-space: nowrap;
        color: #555;
        transition: all 0.2s;
        flex-shrink: 0; 
    }
    .tab-btn:hover { background: #ddd; }
    .tab-btn.active {
        background: #e12326;
        color: white;
        border-color: #c01e21;
        font-weight: bold;
    }
</style>

<div class="widget-card draggable" data-filename="meteo_locale.php" id="meteo_locale_box" 
     style="position: absolute !important; width: <?php echo $w_width ?? '100%'; ?>; height: <?php echo $w_height ?? '240px'; ?>; left: <?php echo $w_x ?? '0px'; ?>; top: <?php echo $w_y ?? '0px'; ?>;">
    
    <div class="widget-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin:0; color:#333; font-size: 0.9em;">
            METEO <span id="header_loc_name" style="font-weight:normal; color:#666;">(...)</span>
        </h4>
        <span class="drag-handle" style="cursor: move; font-size: 1.2em; color: #999;">⠿</span>
    </div>

    <div style="flex-grow: 1; padding: 10px; display:flex; flex-direction:column;" id="meteo_wrapper">
        
        <div class="location-tabs">
            <?php foreach($apiari as $idx => $apiario): ?>
                <button class="tab-btn <?php echo ($idx === 0) ? 'active' : ''; ?>" 
                        onclick="loadWeather(this, '<?php echo $apiario['lat']; ?>', '<?php echo $apiario['lon']; ?>', '<?php echo addslashes($apiario['nome']); ?>')">
                    <?php echo htmlspecialchars($apiario['nome']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="meteo_content" class="meteo-box">
            <div style="text-align:center; padding: 20px;">Seleziona un apiario...</div>
        </div>
    </div>
</div>

<script>
// Funzione conversione codici Meteo (WMO)
function getWmoDescription(code) {
    if(code === 0) return { icon: '☀️', text: 'Sereno', bg: 'linear-gradient(135deg, #ffeb3b 0%, #fbc02d 100%)' };
    if(code >= 1 && code <= 3) return { icon: '⛅', text: 'Nuvoloso', bg: 'linear-gradient(135deg, #90caf9 0%, #42a5f5 100%)' };
    if(code >= 45 && code <= 48) return { icon: '🌫️', text: 'Nebbia', bg: 'linear-gradient(135deg, #cfd8dc 0%, #90a4ae 100%)' };
    if(code >= 51 && code <= 67) return { icon: '🌧️', text: 'Pioggia', bg: 'linear-gradient(135deg, #90a4ae 0%, #546e7a 100%)' };
    if(code >= 71) return { icon: '❄️', text: 'Neve', bg: 'linear-gradient(135deg, #eceff1 0%, #b0bec5 100%)' };
    if(code >= 95) return { icon: '⛈️', text: 'Temporale', bg: 'linear-gradient(135deg, #546e7a 0%, #263238 100%)' };
    return { icon: '❓', text: '...', bg: '#999' };
}

function loadWeather(btnElement, lat, lon, name) {
    // 1. Gestione stile Bottoni (Attiva/Disattiva)
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    if(btnElement) btnElement.classList.add('active');
    
    // Aggiorna titolo widget
    document.getElementById('header_loc_name').innerText = "(" + name + ")";
    
    // Effetto caricamento
    var container = document.getElementById('meteo_content');
    container.style.opacity = '0.6';

    // 2. Chiamata API
    var url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&daily=weathercode,temperature_2m_max,temperature_2m_min&forecast_days=7&timezone=auto`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            var current = data.current_weather;
            var daily = data.daily;
            
            var wmoCurr = getWmoDescription(current.weathercode);
            
            // HTML Meteo Attuale
            var html = `
                <div class="meteo-current">
                    <div style="display:flex; align-items:center;">
                        <div class="meteo-icon">${wmoCurr.icon}</div>
                        <div>
                            <div class="meteo-temp-big">${Math.round(current.temperature)}°</div>
                            <div style="font-weight:bold;">${wmoCurr.text}</div>
                        </div>
                    </div>
                    <div class="meteo-details">
                        <div>Min: <b>${Math.round(daily.temperature_2m_min[0])}°</b></div>
                        <div>Max: <b>${Math.round(daily.temperature_2m_max[0])}°</b></div>
                        <div class="meteo-wind">💨 ${current.windspeed} km/h</div>
                    </div>
                </div>
            `;

            // HTML Previsioni Future (6 giorni)
            html += `<div class="forecast-container">`;
            for(let i=1; i<7; i++) {
                let d = new Date(daily.time[i]);
                let dayName = d.toLocaleDateString('it-IT', {weekday: 'short'}).replace('.','');
                
                let wmoFut = getWmoDescription(daily.weathercode[i]);
                let tempMax = Math.round(daily.temperature_2m_max[i]);
                
                html += `
                    <div class="forecast-item">
                        <div class="forecast-day">${dayName}</div>
                        <div class="forecast-icon">${wmoFut.icon}</div>
                        <div class="forecast-temp">${tempMax}°</div>
                    </div>
                `;
            }
            html += `</div>`;

            // Aggiorna Contenuto e Colore Sfondo
            container.innerHTML = html;
            container.style.background = wmoCurr.bg;
            container.style.opacity = '1';
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div style="text-align:center; padding:20px;">Dati non disponibili</div>';
            container.style.background = '#666';
            container.style.opacity = '1';
        });
}

// Avvio automatico al caricamento della pagina (simula click sul primo bottone)
document.addEventListener("DOMContentLoaded", function() {
    var firstBtn = document.querySelector('.tab-btn');
    if(firstBtn) {
        firstBtn.click();
    }
});
</script>