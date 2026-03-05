# BSPWE
Cílem předmětu je seznámit studenty s problematikou správy webového serveru. Studenti se seznámí s technologickým rámcem webového prostředí a s jeho hlavními komponentami.

## Spuštění
V kořenovém adresáři `docker compose up` (`-d` pro spuštění na pozadí; `--build` může pomoct, pokud něco nefunguje).
## Ukončení
V kořenovém adresáři `docker compose down`.

## Přístup
Aplikace je dostupná na `localhost:8080/`, defaultní aplikace je ve složce `/milanovohosting.gg/`. Nová "doména" se přidá jako složka v `/webserver/`, její koncovka musí být zapsaná v `/docker/vhost.conf` a dostupná bude na adrese `localhost:8080/adresa`.