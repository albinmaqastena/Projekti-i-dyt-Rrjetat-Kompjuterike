# UDP Server & Client – Test Guide

Ky dokument përshkruan startimin dhe testimin e komandave të serverit dhe klientëve të UDP-së.

# ⚙️ Nisja e Serverit

Niseni serverin me:

- php udp_server.php

🖥️ Nisja e Klientëve (në terminale të ndryshme)<br>
- Terminal 2 – Klienti 1 (Admin)<br>
- php udp_client.php
<br><br>
- Terminal 3 – Klienti 2<br>
- php udp_client.php


(Mund të hapet edhe një klient më shumë sipas nevojës.)

# 🧪 Testet e Komandave
📁 1. Krijimi i fajllave fillestarë në server<br>
- echo "This is test file 1" > test1.txt<br>
- mkdir test_folder

📜 2. Komanda /list<br>
- /list
- /list test_folder

📖 3. Komanda /read<br>
- /read test1.txt

⬆️ 4. Komanda /upload<br>
- Krijoni një fajll në client:
- echo "This is a local file" > local_file.txt

Pastaj kryeni upload:
/upload local_file.txt

⬇️ 5. Komanda /download<br>
- /download test1.txt

🗑️ 6. Komanda /delete<br>
- /delete local_file.txt

🔍 7. Komanda /search<br>
- /search test


Expected: Duhet të shfaqen të gjitha fajllat që përmbajnë fjalën test (p.sh. document.txt).

ℹ️ 8. Komanda /info<br>
- /info test1.txt
