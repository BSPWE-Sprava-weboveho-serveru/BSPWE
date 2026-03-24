// Odkaz na HTML root element a tlačítko pro změnu motivu
const root = document.documentElement;
const toggle = document.getElementById("themeToggle");

if (toggle) {
  const savedTheme = localStorage.getItem("theme");

  // Pokud byl dříve zvolen tmavý režim, nastaví se při načtení stránky
  if (savedTheme === "dark") {
    root.classList.add("dark");
    toggle.textContent = "☀️";
  }

  // Přepínání mezi světlým a tmavým režimem
  toggle.addEventListener("click", () => {
    root.classList.toggle("dark");

    const isDark = root.classList.contains("dark");

    if (isDark) {
      localStorage.setItem("theme", "dark");
      toggle.textContent = "☀️";
    } else {
      localStorage.setItem("theme", "light");
      toggle.textContent = "🌙";
    }
  });
}
