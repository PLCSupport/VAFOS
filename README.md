Stažení změn z GitHubu:
git pull origin PRO


Nahrání změn na GitHub:
git push origin PRO


Manuální synchronizace
Při každé změně musíš provést následující příkazy v příkazové řádce VS Code na serveru TST nebo PRO:

git add .
git commit -m "Popis změny"
git push origin PRO

git add .: Přidá všechny upravené nebo nové soubory do Gitu.
git commit -m "Popis změny": Uloží změny s komentářem popisujícím, co bylo změněno.
git push origin PRO: Nahraje změny do větve PRO na GitHubu.
