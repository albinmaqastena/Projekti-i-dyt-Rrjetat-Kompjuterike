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

# 🔐 Testet për Klientët Admin<br>
📢 1. Mesazhe BROADCAST<br>
- BROADCAST: Hello all clients!

📬 2. Komanda /messages<br>
- /messages

🚫 3. Testimi i kufizimeve të përdoruesve jo-admin<br>
- /delete test1.txt
- /upload somefile.txt
- /messages
- BROADCAST
- ...

Expected: ERROR: Admin privileges required

# 🛠️ Server Management Tests<br>
📊 1. Komanda STATS<br>
- STATS

🔌 2. Testimi i limitit të lidhjeve<br>
- Terminal 5 – Klienti 4
- php udp_client.php


Expected: ERROR: Server is at maximum capacity. Please try again later.

🕒 3. Testimi i Timeout-it<br>

- Mos dërgoni mesazhe për 100+ sekonda dhe pastaj shkruani: Hello


Expected: Klienti duhet të rilidhet automatikisht.

# ❗ Error Handling Tests
❌ 1. Komanda invalide<br>
- /invalid

Expected: Unknown command. Available: /list, /read, /upload, /download, /delete,<br> /search, /info, /messages

📄 2. Fajllat që nuk ekzistojnë<br>
- /read nonexistent.txt


Expected: ERROR: File not found

# Antaret e Grupit
- Erjon Mustafa, Albin Maqastena, Jon Llabjani dhe Diell Fazliu
