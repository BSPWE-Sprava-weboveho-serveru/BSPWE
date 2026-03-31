<?php
// Úvodní stránka
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milanovo Hosting - Registrace</title>

    <!-- Připojení CSS souboru -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <!-- Přepínač světlého a tmavého režimu -->
        <div class="theme-toggle">
            <button id="themeToggle" type="button" aria-label="Přepnout režim">🌙</button>
        </div>

        <main class="hero">
            <!-- Levá informační část stránky -->
            <section class="hero-copy">
                <div class="eyebrow">Moderní webhosting</div>

                <h1>Vytvořte si svůj vlastní hosting</h1>

                <p class="hero-text">
                    Jednoduché založení hostingu, správa FTP účtu a vlastní doména v moderním a přehledném rozhraní.
                </p>

                <div class="hero-points">
                    <div class="point">
                        <span class="point-icon">✓</span>
                        <span>Rychlá registrace</span>
                    </div>

                    <div class="point">
                        <span class="point-icon">✓</span>
                        <span>Vlastní doména a FTP</span>
                    </div>

                    <div class="point">
                        <span class="point-icon">✓</span>
                        <span>Čisté a moderní prostředí</span>
                    </div>
                </div>
            </section>

            <!-- Pravá část s registračním formulářem -->
            <section class="form-wrap">
                <div class="form-card">
                    <div class="card-top">
                        <div>
                            <div class="card-badge">Registrace</div>
                            <h2>Založit hosting</h2>
                            <p>Vyplňte základní údaje a vytvořte si nový účet.</p>
                        </div>
                    </div>

                    <!-- Registrační formulář -->
                    <form action="register.php" method="POST" class="register-form">
                        <div class="form-group">
                            <label for="username">Uživatelské jméno pro FTP</label>
                            <input type="text" id="username" name="username" placeholder="Zadejte uživatelské jméno" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Heslo</label>
                            <input type="password" id="password" name="password" placeholder="Zadejte bezpečné heslo" required>
                        </div>

                        <div class="form-group">
                            <label for="domain">Název vaší domény</label>
                            <input type="text" id="domain" name="domain" placeholder="moje-firma.cz" required>
                        </div>

                        <button type="submit" class="primary-btn">Založit hosting</button>
                    </form>

                    <!-- Odkaz na přihlášení -->
                    <p class="bottom-text">
                        Máte účet?
                        <a href="login.php">Přihlásit se</a>
                    </p>
                </div>
            </section>
        </main>
    </div>

<script src="theme.js"></script>
</body>
</html>