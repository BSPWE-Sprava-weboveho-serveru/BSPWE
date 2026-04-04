# BSPWE
Cílem předmětu je seznámit studenty s problematikou správy webového serveru. Studenti se seznámí s technologickým rámcem webového prostředí a s jeho hlavními komponentami.

## Spuštění
V kořenovém adresáři `docker compose up` (`-d` pro spuštění na pozadí; `--build` může pomoct, pokud něco nefunguje).
## Ukončení
V kořenovém adresáři `docker compose down`.

## Přístup
Aplikace je dostupná na `localhost:8080/`, defaultní aplikace je ve složce `/milanovohosting.gg/`. Nová "doména" se přidá jako složka v `/webserver/`, její koncovka musí být zapsaná v `/docker/vhost.conf` a dostupná bude na adrese `localhost:8080/adresa`.

## Ukládání databáze
V admineru bez vybrané databáze pomocí tlačítka export na levo. Neexportovat databáze sys a mysql. Výstupem nahradit obsah `/db/initdb.sql`. Pro projevení je potřeba smazat volume s aktuálními daty pomocí `docker compose down --volumes` nebo `docker volume rm dbdata`.

## Příklady
Doména `example.gg` je vlastněna uživatelem `test` heslo `1234`.