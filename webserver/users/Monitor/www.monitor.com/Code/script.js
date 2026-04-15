const STORAGE_KEY = 'hmi-pro-layout';
const grid = document.getElementById('items-grid');
const lockBtn = document.getElementById('lockBtn');
const saveBtn = document.getElementById('saveBtn');

// Inicializace Sortable (Drag & Drop)
const sortable = new Sortable(grid, {
    animation: 300, 
    delay: 150, 
    delayOnTouchOnly: true, 
    touchStartThreshold: 10,
    ghostClass: 'sortable-ghost', 
    dragClass: 'sortable-drag',
    onEnd: () => { 
        saveBtn.classList.remove('saved');
        saveBtn.innerText = "ULOŽIT KONFIGURACI";
    }
});

// Načtení uloženého pořadí z LocalStorage
const saved = localStorage.getItem(STORAGE_KEY);
if (saved) {
    JSON.parse(saved).forEach(id => {
        const el = grid.querySelector(`[data-id="${id}"]`);
        if (el) grid.appendChild(el);
    });
}

// Funkce pro uložení pořadí
function manualSave() {
    const order = sortable.toArray();
    localStorage.setItem(STORAGE_KEY, JSON.stringify(order));
    
    saveBtn.innerText = "ULOŽENO!";
    saveBtn.classList.add('saved');
    
    setTimeout(() => {
        saveBtn.innerText = "ULOŽIT KONFIGURACI";
        saveBtn.classList.remove('saved');
    }, 2000);
}

// Reset do továrního nastavení
function resetDashboard() {
    if (confirm("Obnovit tovární rozložení?")) {
        localStorage.clear();
        window.location.reload();
    }
}

// Zámek editace plochy
function toggleLock() {
    const isLocked = !sortable.option("disabled");
    sortable.option("disabled", isLocked);
    lockBtn.innerText = isLocked ? "ODEMKNOUT" : "ZAMKNOUT";
    lockBtn.classList.toggle('locked', isLocked);
}

// Konfigurace a inicializace grafů Chart.js
const gCfg = { 
    type: 'line', 
    data: { 
        labels: [1,2,3,4,5,6], 
        datasets: [{ 
            data: [65,72,68,85,92,90], 
            borderColor: '#0091ff', 
            borderWidth: 3, 
            pointRadius: 0, 
            fill: true, 
            backgroundColor: 'rgba(0,145,255,0.1)', 
            tension: 0.4 
        }] 
    }, 
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: false }, 
        scales: { x: { display: false }, y: { display: false } } 
    } 
};

// Vykreslení konkrétních grafů
new Chart(document.getElementById('c1'), gCfg);
new Chart(document.getElementById('c2'), {
    ...gCfg, 
    type: 'bar', 
    data: { 
        labels: [1,2,3,4,5,6], 
        datasets: [{ 
            data: [40,55,60,45,80,60], 
            backgroundColor: '#32d74b', 
            borderRadius: 4 
        }] 
    } 
});