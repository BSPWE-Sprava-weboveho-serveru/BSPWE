const decideBtn = document.getElementById('decide-btn');
const optionsInput = document.getElementById('options-input');
const resultEl = document.getElementById('result');
const optionsList = document.getElementById('options-list');   
const historyList = document.getElementById('history-list');
const clearHistoryBtn = document.getElementById('clear-history-btn');
const pocetOpakovani = document.getElementById('target-count');

// Načíst historii a počty z localStorage
let history = JSON.parse(localStorage.getItem('history')) || [];
let counts = JSON.parse(localStorage.getItem('counts')) || {};

renderHistory();

document.addEventListener("DOMContentLoaded", () => {
  renderHistory();
});

// Funkce pro náhodný výběr až do dosažení požadovaného počtu výběrů
decideBtn.addEventListener('click', () => {

document.getElementById('options-card').classList.add('hidden');
document.getElementById('result-card').classList.add('hidden');

  document.getElementById('options-card').classList.remove('hidden'); // Zobrazit kartu s možnostmi
  document.getElementById('result-card').classList.remove('hidden'); // Zobrazit kartu s výsledkem
  const input = optionsInput.value.trim(); // Načtení hodnoty z textFieldu 
  const targetCount = parseInt(pocetOpakovani.value, 10) || 1; // v zakladu je 1 proto || 
  if (input) {
    const moznosti = input.split(',').map(moznost => moznost.trim()).filter(moznost => moznost);

    if (moznosti.length > 0) {
      // Reset počtu výběrů pro všechny možnosti před začátkem nového výběru
      const initialCounts = moznosti.reduce((acc, moznost) => {
        acc[moznost] = 0;  // Reset počtu pro každou možnost
        return acc;
      }, {});

      counts = { ...initialCounts };


      optionsList.innerHTML = '';
      moznosti.forEach(moznost => {
        const li = document.createElement('li');
        li.textContent = `${moznost} (Počet: 0)`;
        li.className = 'p-2 rounded bg-white shadow-sm hover:bg-gray-200';
        optionsList.appendChild(li);
      });

      let vybranaMoznost = '';
      let jeVybrano = false;

      // Opakovat výběr až do dosažení cílového počtu pro jednu možnost
      const interval = setInterval(() => {
        // Náhodný výběr
        const randomIndex = Math.floor(Math.random() * moznosti.length); // Náhodné číslo pro vybírání 
        vybranaMoznost = moznosti[randomIndex]; // ranodm možnost

        // Zvýšení počtu výběrů pro tuto možnost
        counts[vybranaMoznost] = (counts[vybranaMoznost] || 0) + 1;

        // Pokud některá možnost dosáhla požadovaného počtu výběrů, zastavit
        if (counts[vybranaMoznost] >= targetCount) {
          clearInterval(interval); // Zastavit výběr
          jeVybrano = true;

          // Aktualizovat seznam s posledním výběrem
          optionsList.innerHTML = '';
          moznosti.forEach(moznost => {
            const li = document.createElement('li');
            li.textContent = `${moznost} (Počet: ${counts[moznost] || 0})`;
            li.className = 'p-2 rounded bg-white shadow-sm hover:bg-gray-200';

            // Zvýraznění vybrané možnosti
            if (moznost === vybranaMoznost) {
              li.classList.add('bg-green-100'); // Zvýraznění vybrané možnosti
            }

            optionsList.appendChild(li);
          });

          resultEl.textContent = `Program skončil! byla vybrána možnost '${vybranaMoznost}'`;

          // Přidat výběr do historie
          const historyEntry = `${moznosti.map(moznost => `${moznost} (Počet: ${counts[moznost] || 0})`).join(', ')} - Vybráno: ${vybranaMoznost}`;
          history.unshift(historyEntry); // Přidá poslední 
          if (history.length > 5) history.pop(); // Omezit historii na 5 položek

          // Uložit historii a počty do localStorage
          localStorage.setItem('history', JSON.stringify(history));
          localStorage.setItem('counts', JSON.stringify(counts));

          // Renderovat historii
          renderHistory();
          return;
        }

        resultEl.textContent = `Vybráno: ${vybranaMoznost}`;

        // Zobrazit aktualizované počty v seznamu možností
        optionsList.innerHTML = '';
        moznosti.forEach(moznost => {
          const li = document.createElement('li');
          li.textContent = `${moznost} (Počet: ${counts[moznost] || 0})`;
          li.className = 'p-2 rounded bg-white shadow-sm hover:bg-gray-200';

          // Zvýraznění možnosti s nejvíce výběry
          if (jeVybrano && counts[moznost] > 0 && counts[moznost] === Math.max(...Object.values(counts))) {
            li.classList.add('bg-green-100');  // Zvýraznění možnosti s nejvíce výběry
          }

          optionsList.appendChild(li);
        });

      }, 500); // Interval 500 ms pro náhodné výběry ať to vypadá jako hra
    }
  }
});

// Funkce pro vykreslení historie
function renderHistory() {
  historyList.innerHTML = '';
  history.forEach((item, index) => {
    const li = document.createElement('li');
    li.textContent = item;
    
    // Přidání tlačítka pro odstranění položky z historie
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Smazat';
    deleteBtn.className = 'ml-4 text-red-500 hover:text-red-700';
    deleteBtn.addEventListener('click', () => {
      deleteHistoryItem(index);
    });

    li.className = 'p-2 rounded bg-white shadow-sm hover:bg-gray-200 flex justify-between items-center';
    li.appendChild(deleteBtn);
    historyList.appendChild(li);
  });
}

// Funkce pro odstranění položky z historie
function deleteHistoryItem(index) {
  // Odstranit položku z pole historie
  history.splice(index, 1);

  // Uložit aktualizovanou historii do localStorage
  localStorage.setItem('history', JSON.stringify(history));
  renderHistory();
}

// Funkce pro vymazání historie
clearHistoryBtn.addEventListener('click', () => {
  // Vymazání historie z localStorage
  localStorage.removeItem('history');
  localStorage.removeItem('counts');
  
  // Vymazání historie na stránce
  history = [];
  counts = {};
  renderHistory();
});
