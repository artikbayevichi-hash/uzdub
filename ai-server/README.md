# UZDUB AI — sayt uchun 0 dan qurilgan sun'iy intellekt

Saytingiz (`C:\xampp\htdocs\uzdub`) AI javobni **Ollama** dan kutadi
(`api/ai-chat.php` → `http://localhost:11434/api/chat`). Bu loyihada
**o'sha interfeysni qaytaradigan AI miyasi** Python'da qurildi —
Ollama modelini yuklab o'tirmasdan, sayt bizning AI bilan ishlaydi!

> Sayt kodiga **hech qanday o'zgartirish** kiritilmagan.

---

## Arxitektura

```
Brauzer (js/ai-chat.js)
   │  fetch
   ▼
PHP: api/ai-chat.php
   │  curl POST /api/chat   (Ollama formati)
   ▼
Bizning Python server: 127.0.0.1:11434
   ├─ ai_server.py   (Flask — Ollama interfeysi)
   ├─ brain.py       (AI miya — 0 dan)
   │    ├─ niyat aniqlash (qoidalar)
   │    ├─ tavsiyalar (MySQL: uzdub bazasidan)
   │    ├─ xotira (ai_knowledge jadvaliga saqlaydi)
   │    └─ PyTorch neyron tarmoq (intent_nn.py) — yordamchi
   └─ intent_nn.py   (niyat klassifikatori: PyTorch'da o'qitilgan)
```

## Fayllar

| Fayl | Vazifasi |
|---|---|
| `ai_server.py` | Ollama `/api/chat` + OpenAI-mos `/v1/chat/completions` |
| `brain.py` | AI miyasi: suhbat, tavsiyalar, xotira |
| `intent_nn.py` | PyTorch neyron tarmoq (niyat klassifikatori) |
| `rating_nn.py` | PyTorch neyron tarmoq (reyting bashoratchisi) |
| `start_ai.bat` | Serverni bir tugma bilan ishga tushirish |
| `voice_chat.py` | **OVOZLI MULOQOT**: mikrofon + o'zbekcha ovoz (edge-tts) bilan telefon suhbatidek gaplashish |
| `voice_chat.bat` | Ovozli muloqotni ishga tushirish (bir tugma) |
| `seed_knowledge.py` | Bilimlar bazasini kengaytirish (100+ bilim qo'shadi) |
| `seed_knowledge_mega.py` | **MEGA bilim**: 300 ta yangi aniq fakt (O'zbekiston, dunyo poytaxtlari, fan, tarix, shaxslar, hayvonlar, texnologiya, sport, salomatlik...) → DB + `ai_knowledge.json`'ga qo'shadi (idempotent) |
| `mega_knowledge_a.py` … `mega_knowledge_h.py` | **MEGA bilim**: 8 ta mavzuli fayl (davlatlar, fan, biologiya, IT, kino, tarix, ta'lim...) → ~830 ta yangi bilim |
| `seed_mega.py` | Mega bilim + 100 foydalanuvchi afzalligi + fikr-mulohazalar → **jami 1000+ qator** (idempotent) |
| `test_functions.py` | Avtomatik sinov: matematika, TOP, birliklar, shaxsiyat, bilim, stats (1000+), internet fallback (37 ta) |
| `test_integration.py` | Avtomatik sinov: kontekst, niyatlar, xotira |
| `test_learning.py` | Avtomatik sinov: o'z-o'zini rivojlantirish |
| `test_emotion.py` | Avtomatik sinov: hissiyotlarni tushunish (11 ta hissiyot) |
| `cleanup_test_data.py` | Test qoldiqlarini tozalash (`pref:`/`fb:` qatorlari) |
| `intent_nn.pth` | O'qitilgan niyat tarmog'i (avtomatik yaratiladi) |
| `rating_nn.pth` | O'qitilgan reyting tarmog'i (avtomatik yaratiladi) |
| `data/user_messages.jsonl` | Haqiqiy foydalanuvchi xabarlari (NN qayta o'qitadi) |
| `ovozli_ai.html` | **JONLI OVOZLI SUHBAT** (AI papkasining ildizida): hands-free rejim — gapirasiz → sukunat 1.8s → savol yuboriladi → bot o'zbek neyron ovozda javob aytadi → qayta eshitadi |
| `build_knowledge_json.py` | Barcha bilimlarni `ai_knowledge.json`'ga jamlaydi (MySQL'siz fallback) |
| `ai_knowledge.json` | **Offlayn bilim bazasi** (1250+ qator) — MySQL o'chiq bo'lsa brain shu yerdan o'qiydi |

---

## Xotira (Chat History) — ai_server'da

`ai_server.py` har bir foydalanuvchi uchun **sessiya tarixini** saqlaydi
(`session_id` kaliti bilan):

```
POST /api/chat  { model, session_id, messages, stream }
   └─ suhbat tarixi: system prompt + oxirgi 15 xabar (MAX_HISTORY)
GET  /api/history?session_id=...   → tarixni ko'rish
POST /api/history/clear            → tarixni tozalash
```

- Sayt to'liq tarix yuborsa → almashtiradi; ovozli sahifa bitta xabar
  yuborsa → sessiya tarixiga qo'shiladi. Ikkala holat ham xotirada ishlaydi.

## AI rejimi — endi FOYDALANUVCHINING O'Z AIsi (cloud o'chiq)

> ⚙️ **Standart holat: cloud O'CHIQ.** Foydalanuvchi talabiga ko'ra tashqi AI
> xizmatlar (Groq/Cerebras/SambaNova/Together/OpenRouter) **ishlatilmaydi**.

**Nima ishlaydi (to'liq oflayn, tezkor):**

```
1) brain.py — o'zimiz yozgan AI (bilim bazasi + sayt DB + o'z-o'zini o'rganish)
2) ERKIN muloqot — qoidalar yumshatildi:
   ✗ "faqat kino/anime/multfilm" degan qat'iy rad yo'q
   ✗ salomlashish endi kino'ga majburan yo'naltirmaydi
   ✓ istalgan mavzuda ochiq suhbat, savol echo + davom ettirish
3) Kino/anime/multfilm savollarida — faqat REAL sayt kontenti + watch.php
```

**Lokal neyron tarmoq (asosiy yo'l, API kalitsiz, to'liq oflayn):**

Ollama'da ochiq manbali neyron tarmoq modeli (Qwen3:8b) o'rnatilgan bo'lsa,
suhbat javoblari **mashinaning o'zida** yaratiladi — ChatGPT kabi erkin
fikrlaydi, internet ham kalit ham kerak emas.

```
1) O'rnatish (bir marta):
   winget install --id Ollama.Ollama -e
   setx OLLAMA_HOST 127.0.0.1:11435      (port ziddiyatini oldini olish)
   ollama pull qwen3:8b                   (~5 GB, bir marta)

2) Har safar ishga tushirish:
   ollama serve          (yoki Ollama dasturini ochish)
   py -X utf8 ai_server.py                (11434)
```

Zanjir: 1) Real Ollama (lokal Qwen3:8b — erkin fikr) → 2) OpenRouter bepul
LLM (ixtiyoriy, internet bo'lsa) → 3) brain.py qoidalari (chaqmoq tez zaxira).

**Agar cloud ham kerak bo'lsa (ixtiyoriy):**

```
UZDUB_CLOUD=1  py -X utf8 ai_server.py
```

Shunda zanjir shunday: 1) OpenRouter (nvidia Nemotron 120B/550B bepul —
Groq/Cerebras/Together kalitlari 403 beradi, SambaNova limitda) →
2) Real Ollama (qwen3:8b) → 3) brain.py zaxira.

- Kalitlar **kodda yo'q** — saytning `C:\xampp\htdocs\uzdub\.env` faylidan
  runtime'da o'qiladi (GROQ_API_KEY, CEREBRAS_API_KEY, ...).
- Suhbatda token tejash: LLM'ga so'nggi 8 xabar (+ max 700 token javob).

### Kino/anime/multfilm savollari — REAL sayt kontentidan

- `tavsiya qil`, `top`, `yangi qo'shilganlar`, `nechta ... bor?`, `qanday janrlar`,
  `tasodifiy` kabi savollar → **brain.py** (sayt DB'sidagi haqiqiy kontent +
  `watch.php` havolalar). LLM uydirma nom bermaydi.
- Janr to'g'ri tutadi: `"komediya kino"`, `"qo'rqinchli kino"`, `"sarguzasht
  multfilm"` → shu janrdagi real kontent. Toifa+janr bo'sh bo'lsa — noto'g'ri
  kontent BERILMAYDI; o'rniga halol izoh bilan shu janrda borlari ko'rsatiladi.
- `qo'rqinchli` kabi apostrofli so'zlar ham aniq tushuniladi.

### Ovozli tinglash yaxshilandi (ovozli_ai.html)

- Sukunat 1.8s → **2.6s** (gap o'rtasida kesib tashlamaydi); gap tinish
  belgisi/yakuniy qism bilan tugamagan bo'lsa **yana 1.8s** kutadi.
- `saylov`→`salom` va boshqa ASR xatolari to'g'rilanadi; ortiqcha tovushlar
  (`ee`, `hm`...) va tutilishlar (`kino kino`) tozalanadi.

## Xatosiz degradatsiya (MySQL / internet yo'q)

- MySQL o'chiq bo'lsa → `brain.query()/execute()` xato tashlamaydi
  (bo'sh natija / yozmaydi), bilim bazasi `ai_knowledge.json`'dan yuklanadi.
- Internet yo'q bo'lsa → izlash o'tkazib yuboriladi, bilim bazasidan javob.
- Ovoz (TTS) chiqmasa → brauzer tizim ovozi zaxira bo'ladi.

---

## Ishga tushirish

1. **Lokal AI (Ollama)** — bir marta: `winget install --id Ollama.Ollama`,
   `setx OLLAMA_HOST 127.0.0.1:11435`, `ollama pull qwen3:8b`.
2. **XAMPP** ishlamoqchi bo'lsin (Apache + MySQL).
3. Cloud rejim (ixtiyoriy) uchun: `py -m pip install requests`.
4. Serverni ishga tushiring — ikki usul:
   ```bash
   # 1-usul (tavsiya): bir marta bosish bilan
   start_ai.bat

   # 2-usul (terminal):
   py ai_server.py
   ```
5. Saytni oching → AI chat bo'limi ishlaydi.

Eslatma: kompyuter qayta ishga tushsa, Ollama + serverni qayta ishga
tushirish kerak (start_ai.bat).

## AI nima qiladi?

| So'rov | Javob |
|---|---|
| `salom` | salomlashish |
| `mening ismim Ozod` | ismingizni eslab qoladi, keyingi javoblarda ishlatadi |
| `yangi anime bormi?` | eng yangi qo'shilgan anime |
| `komediya kino tavsiya qil` | janr bo'yicha tavsiyalar |
| `multfilm ko'rsat` | multfilmlar (0-reytinglilarga AI baho qo'shadi) |
| `Yulduzlararo bormi?` | nom bo'yicha qidiruv |
| `yana bormi?` | avvalgi tavsiyaga o'xshash yana 3 ta taklif |
| `buni ko'rmoqchiman` | suhbat tarixidagi havoladan foydalanadi |
| `bugun charchadim` | kayfiyatingizga mos javob |
| `juda xafa bo'lib yig'layapman` | **hissiyotingizni tushunib**, iliq empatik javob |
| `bugun juda xursandman` | quvonchingizga sherik bo'ladi |
| `yaxshimisan` / `rasmsan` / `qayerdansan` / `nima qilyapsan` | **ERKIN SUHBAT**: kundalik o'zbekcha gaplar 35+ qoida bilan ~15msda, tarmoqsiz, tabiiy javob (ilgari bir xil "dahshatli mavzu" javobi qaytardi) |
| `imtihondan qo'rqyapman` | taskin beradi (11 xil hissiyot: xursand, g'amgin, jahldor, stress, charchagan, yolg'iz, qo'rqqan, hayajonli, zerikkan, og'riq, minnatdor) |
| `anime nima?` / `sun'iy intellekt nima?` | **BILIM**: 1300+ mavzu (kino, anime, AI, O'zbekiston, davlatlar, fan, texnika, tarix, sport...) bo'yicha o'zbekcha tushuntirish |
| `germaniya nima?` / `al-xorazmiy kim?` / `navro'z qachon?` | **Katta bilim bazasi**: davlatlar, shaharlar, tarixiy shaxslar, bayramlar, formulalar — **1300 dan ortiq mavzu** |
| `fransiya poytaxti` (savol so'zisiz ham!) | **Aqlli routing**: "fransiya poytaxti", "o'zbekiston aholisi", "eng tez hayvon qaysi", "dunyodagi eng katta davlat" kabi har xil shakldagi savollar aniq bilimga yetkaziladi |
| `bitkoin nima?` (bazada yo'q mavzu) | **INTERNETDAN AQLLI IZLASH** 🌐: Wikipedia'ning eng mos GAPini tanlab javob beradi (qachon/nechta/qayerda/nega og'irliklari), tez ishlash uchun byudjet cheklangan, topilgani **eslab qolinadi** — keyingi so'rov chaqmoq tez |
| `menga anime yoqadi` | afzalligingizni **eslab qoladi**, keyingi tavsiyalar shunga moslashadi |
| `yoqmadi` / `zo'r edi` | tavsiyadan keyingi fikringizni hisobga oladi |
| `o'z-o'zini rivojlantiradimi?` | jonli "o'rganish kabineti" hisoboti |
| `esda tut: X = Y` | yangi bilimni yodda saqlash |
| `premium nima?` | sayt haqida |
| oddiy suhbatlar | tasodifiy (bir nechta variantdan) tabiiy javoblar |

### 🧮 ChatGPt'nikidek tezkor funksiyalar (ChatGPT-style quick features)

| So'rov | Javob |
|---|---|
| `23 * 4 qancha` / `2 + 3 * 4` | matematikani yechadi |
| `1 km necha metr?` / `2 soat necha daqiqa?` | birliklar konvertatsiyasi |
| `soat nechi?` / `bugun qaysi sana?` | vaqt va sana (kun nomi bilan) |
| `eng mashhur kino` / `eng yaxshi anime` | **TOP** ro'yxat (mashhurlik bo'yicha) |
| `eng yuqori reyting` / `eng ko'p ko'rilgan` | TOP — reyting / tomosha soni bo'yicha |
| `top 5 kino` | ixtiyoriy TOP soni (3-5) |
| `nechta kino bor?` / `nechta anime bor?` | bazadagi kontent **soni** |
| `qanday janrlar bor?` | sayt janrlari ro'yxati |
| `tasodifiy kino` | tasodifiy 3 ta taklif |
| `seni kim yaratdi?` / `o'zing haqida gapir` | shaxsiyat — odamdek suhbat |

> 💡 Yangi bilimlar `seed_knowledge.py` va **`mega_knowledge_*.py` +
> `seed_mega.py`** bilan qo'shiladi — bilim bazasi **900+ bilimga
> kengaytirildi** (jami **1000+ qator**: 900+ bilim + 106 afzallik + 40
> fikr-mulohaza): davlatlar va shaharlar (Germaniya, Yaponiya, London,
> Dubay...), O'zbekiston (Amir Temur, Ulug'bek, Navoiy, Bobur,
> Al-Xorazmiy, Ipak yo'li, Navro'z...), fan (yorug'lik tezligi, atom,
> algebra, qora tuynuk, sayyoralar...), tabiat va hayvonot (sher,
> delfin, panda...), IT va dasturlash (Python, JS, React, API...),
> kino/anime/musiqa/sport, tarix va iqtisod, ta'lim va kasblar va
> boshqalar. `seed_mega.py` qayta ishga tushirilsa ham xavfsiz —
> bor qatorlar o'tkazib yuboriladi.

### 🌐 Internetdan izlash (bazada topilmasa)

Agar foydalanuvchi so'ragan mavzu bilim bazasida bo'lmasa, AI:

1. **Xotira** (ai_knowledge) va **`_web_cache`** — ilgari topilgan javobni darhol qaytaradi;
2. **Wikipedia API** — o'zbekcha → ruscha → inglizcha tartibida qisqa izoh oladi;
3. **DuckDuckGo** — Wikipedia javob bermasa, zaxira qidiruv;
4. Topilgan javob `learn_web()` orqali **xotira + bazaga saqlanadi** — keyingi so'rovda chaqmoq tez, internet chiqmasa ham.

Internet yo'q bo'lsa (ofis/ovoz rejimi) — **xato bermaydi**: "esda
tut: ..." taklifi bilan o'zingiz o'rgatishingiz mumkin. Sinov:
`py -X utf8 test_functions.py` (12-bo'lim).

### 🧠 Aqlli javob mashinasi — Groq'dan ham aniq (0 dan, o'z kodi)

Chat-botning "miyasi" tashqi AI xizmatisiz quyidagi qatlamlar bilan
har qanday so'rovga tez va to'g'ri javob beradi:

1. **Bilim bazasi (1300+)** — `seed_knowledge_mega.py` bilan 300 ta
   yangi aniq fakt qo'shildi: 50+ davlat poytaxti, O'zbekiston
   geografiyasi/tarixi/shaxslari, fan (yorug'lik tezligi, sayyoralar,
   E=mc²...), hayvonlar (gepard 110 km/soat...), texnologiya, sport,
   salomatlik. Javob **0.0 soniyada**, internet chiqmasa ham.
2. **Aqlli so'rov tushunish** — savol shakllari keng tushuniladi:
   `"X qaysi?"` ham savol; `"fransiya poytaxti"` kabi **so'roq so'zisiz**
   iboralar ham bilimga boradi; `"o'zbekistonning poytaxti"` kabi
   -ning qo'shimchalari tozalanadi; to'liq ibora KB'da bo'lsa — NN
   yanglishmasligi uchun avval bilim qaraladi.
3. **Wikipedia'ning eng mos GAPi** — `web_lookup` maqolani o'qib,
   savolga mos gapni o'zi tanlaydi: *qachon/yil*, *nechta/son*,
   *qayerda/poytaxt*, *nega/sabab* og'irliklari va otlarning aniqliligiga
   qarab — "Ayt!" deganiga Buyruq gapni berib qo'ymaydi.
4. **Tez va chidamli** — Wikipedia har so'rovda 2s kutib, 2 marta
   urinadi; oddiy so'rov uchun 4s (ovozli chat osilib qolmaydi),
   "batafsil" uchun 6s byudjet; kirill so'rovlar avval ruscha
   Wikipedia'dan izlanadi. Topilgan javob DB + JSON'ga saqlanadi.
5. **MySQL o'chiq bo'lsa ham tez** — qulash paytida bir marta 2s
   kutadi, keyin 30 soniya davomida qayta urinmaydi (javoblar
   `ai_knowledge.json`'dan, chaqmoq tez).
6. **Erkin suhbat qatlami** — kundalik o'zbekcha gaplar tabiiy qabul
   qilinadi: `"yaxshimisan"`, `"rasmsan"`, `"qayerdansan"`,
   `"nima qilyapsan"`, `"seni sevaman"`, `"uyqum keldi"`... — 35+ qoida,
   0 dan, tarmoqsiz, **~15ms** javob. So'roq so'zisiz ham, apostrofli
   ham ishlaydi (`"zo'rsan"` xohlagandek). Noma'lum mavzuga qolsa —
   endi "dahshatli mavzu" emas, balki tabiiy savol bilan suhbatni
   ochadi (jami 37+ qoida).

### 🧮 ChatGPt'nikidek tezkor funksiyalar (ChatGPT-style quick features)

Har bir tavsiya `http://localhost/uzdub/watch.php?id=N` havolasi bilan
beriladi — foydalanuvchi bir bosishda ko'rishi mumkin.

## Suhbat tarixi & davomiylik

PHP sayt mijozdan **6 ta oxirgi xabarni** `messages[]` ichida yuboradi.
Miya buni hisobga oladi:

- **"yana bormi?"** — oxirgi so'rovdagi kategoriya/janr bo'yicha keyingi
  batchni (offset 3) qaytaradi
- **"buni ko'rmoqchiman"** — avvalgi javobdagi tavsiya havolasiga
  yo'naltiradi
- **ism** — "mening ismim X" dan keyin barcha javoblarda ishlatiladi

## O'z-o'zini rivojlantirish (foydalanuvchilardan o'rganish)

AI shunchaki javob bermaydi — u **haqiqiy foydalanuvchi
ma'lumotlaridan o'rganadi** va vaqt o'tishi bilan yaxshilanadi:

1. **Afzalliklardan o'rganish** — "menga anime yoqadi" desa, buni
   `ai_knowledge` jadvaliga yozadi (`pref:anime`). Endi "tavsiya qil"
   deganida **aynan shu kategoriyadan** taklif beradi.
2. **Fikr-mulohazadan o'rganish** — tavsiyadan keyin "yoqmadi" yoki
   "zo'r edi" deylsa, o'sha kontentga `fb:dislike:id` / `fb:like:id`
   yoziladi. Keyingi tavsiyalar **buni tartibda hisobga oladi**
   (yoqmaganlar pastga tushadi, yoqqanlar yuqoriga chiqadi).
3. **NN real ma'lumot bilan o'zini o'qitadi** — qoidalar ishonchli
   aniqlagan har bir haqiqiy xabar `data/user_messages.jsonl` ga
   pseudo-metkali namuna bo'lib yig'iladi; 5 ta yangi namuna to'plansa,
   `intent_nn.py` o'zini shu REAL so'zlar bilan qayta o'qitadi
   (`n_user_train` hisoblagichi bilan). Ya'ni neyron tarmoq
   **foydalanuvchilarning o'z iboralaridan o'rganadi**.
4. **Bazadagi foydalanuvchi faolligidan** — tavsiyalar tartibiga
   `watch_history` (ko'rilganlik) va `content_ratings` (berilgan
   reytinglar) ham qo'shiladi: foydalanuvchilar ko'p ko'rgan va yuqori
   baholagan kontent yuqoriroq chiqadi.
5. **Bilimlarning mustahkamlanishi** — javobda ishlatilgan har bir
   bilimning `use_count`i oshadi (`esda tut:` bilan o'rgatilgan
   bilimlar ham shu yo'l bilan "kuchayadi").

**Hisobot:** "o'z-o'zini rivojlantiradimi?" deysangiz — AI hozirgi
holatini aytadi: nechta xabar, qancha afzallik/fikr o'rgangan, NN
uchun nechta real namuna yig'ilgan va necha marta qayta o'qitilgan.

> Eslatma: afzallik va fikr-mulohazalar `ai_knowledge` jadvalida
> saqlanadi — server qayta ishga tushsa ham **yo'qolmaydi**. Shu
> sababli AI har kuni "aqlliroq" bo'lib boradi.

## Hissiyotlarni tushunish (empatiya) 💙

AI endi shunchaki javob bermaydi — u **kayfiyatingizni his qiladi**.
"Axir siz odam bilan suhbatlashayotgandek" taassurot berish uchun 11
xil hissiyot aniqlanadi va har biriga **bir nechta** iliq, o'zbekcha
javob bor (tasodifiy tanlanadi, ismingizni qo'shib):

| Hissiyot | Namuna |
|---|---|
| xursand 🎉 | "bugun juda xursandman" → "Quvonchingizga sherik bo'ldim... komediya tanlaylik!" |
| g'amgin 😔 | "yig'layapman" → "Men siz bilan shu yerdaman... iliq multfilm ko'raylikmi?" |
| jahldor 😤 | "jahlim chiqdi" → "Jahlingiz nohaq emas... nafas oling, keling film tanlaymiz" |
| stress 🧘 | "stress, bosim ketyapti" → dam olishga yordam taklifi |
| charchagan 😴 | "charchadim" → "Bir chashka choy... yengil narsa tomosha qiling" |
| yolg'iz 💙 | "jonim siqildi" → "Men yoningizdaman" |
| qo'rqqan 💪 | "qo'rqyapman" → taskin + iliq multfilm taklifi |
| hayajonli ✨ | "hayajondan uxlay olmayapman" → sarguzasht taklifi |
| zerikkan 😄 | "zerikdim" → qiziqarli film "davosi" |
| og'riq 🤒 | "boshim og'riyapti" → g'amxo'rlik |
| minnatdor 🌟 | "rahmat, minnatdorman" → iliq javob |

Hissiyot **aniq so'rovlarni buzmaydi**: "qo'rqinchli kino tavsiya qil"
desangiz — bu hissiyot emas, TAVSIYA so'rovi deb tushuniladi. Sinov:
`py -X utf8 test_emotion.py`

## Ovozli muloqot — AI bilan telefondagidek 🎙️🗣️

ChatGPT/Gemini'ning ovoz rejimidek: **siz gapirasiz → AI eshitadi →
javobni OVOZ bilan aytadi**. `voice_chat.py`:

1. **Mikrofon (STT)**: `speech_recognition` + Google — o'zbekchani
   tushunadi (`uz-UZ`, keyin `ru-RU`, `en-US` zaxiralar)
2. **Miya**: aynan shu `brain.py` — barcha bilim, hissiyot va xotira
   ovozli rejimda ham ishlaydi
3. **Ovoz (TTS)**: `edge-tts` — **uz-UZ-MadinaNeural** (tabiiy o'zbekcha
   ayol ovozi) yoki **uz-UZ-SardorNeural**; internet bo'lmasa `pyttsx3`
   offlayn zaxira

Ishga tushirish:
```bash
voice_chat.bat        # yoki:  py -X utf8 voice_chat.py
```

Buyruqlar (ovoz bilan ham aytish mumkin):
- **"chiqish" / "xayr" / "tugatish"** — suhbatni yakunlash
- **"yozib yuboray"** — klaviatura rejimiga o'tish (mikrofon yo'q bo'lsa ham avtomatik)
- klaviaturada **"ovoz"** yozsangiz — qaytib mikrofon rejimiga

Kerakli kutubxonalar (allaqachon o'rnatilgan):
```bash
py -m pip install speechrecognition pyaudio edge-tts pyttsx3
```

## Bilimlar bazasi (seed_knowledge.py) 📚

`seed_knowledge.py` AI'ga **29 ta yangi bilim** qo'shadi: kino, anime,
multfilm, sun'iy intellekt, neyron tarmoq, PyTorch, Python, chatbot,
Ollama, xotira, reyting, premium, UZDUB, server, API, ma'lumotlar
bazasi, barcha janrlar, rejissyor, kino tarixi... Endi
**"anime nima?", "sun'iy intellekt nima?"** kabi savollarga AI o'zi
o'zbekcha javob beradi. Qayta ishga tushirish bemalol (bor qatorlar
o'tkazib yuboriladi):
```bash
py -X utf8 seed_knowledge.py
```

**Katta kengaytirish — 1000+ qator (mega):**
```bash
py -X utf8 seed_mega.py
```
Bu skript `mega_knowledge_a.py` … `mega_knowledge_h.py` dan ~830 yangi
bilimni, 100 ta foydalanuvchi afzalligini (`pref:u001:kino` va h.k.) va
har kontent uchun fikr-mulohazalarni (`fb:like/id`, `fb:dislike/id`)
qo'shadi → **jami 1000+ qator (900+ bilim + 106 afzallik + 40
fikr-mulohaza)**. Xuddi `seed_knowledge.py` kabi **idempotent**:
qayta ishga tushirsangiz bor qatorlar o'tkazib yuboriladi.

## Saytdagi ChatGPT uslubidagi orb 🎡 (ai-chat.css / ai-chat.js)

Sayt widgeti (`C:\xampp\htdocs\uzdub\css\ai-chat.css` va
`js/ai-chat.js`) — ChatGPT'nikiga o'xshash **jonli yumaloq orb** bilan
ishlaydi:

- **Bosh avatar** → yumaloq orb: radial yaltiroq gradient + aylanuvchi
  porloq halqa (`::before`/`::after`) + "nafas olish" (breathe) animatsiyasi;
- **O'ylash holati** → JS `sendToAI()` dan `finishTurn()` gacha panelga
  `aic-thinking` klassi qo'shiladi: orb tez va yorqin pulsatsiya qiladi,
  halqa tez aylanadi (e'lon: `panel.classList.add('aic-thinking')`);
- **FAB (suzuvchi tugma)** → tashqarisida sekin aylanuvchi halqa;
- **Typing indikatori** → mini orb + "O'ylayapman..." matni (`#aic-typing`);
- **Bot xabarlari avatarlari** → kichik orb + halqa (`.aic-msg-bot .aic-msg-avatar`).

Barcha animatsiyalar faqat CSS (`@keyframes aic-orb-breathe` va
`aic-orb-spin`) — JS'dan faqat klass almashinadi, PHP'ga o'zgarish yo'q.

## Texnologiyalar

- **0 dan**: niyat aniqlash, matn normalizatsiya, SQL-tavsiyalar,
  xotira — barchasi qo'lda yozilgan Python kodida
- **PyTorch (intent_nn.py)** — so'z "sumkasi" (bag-of-words) dan
  niyatni aniqlaydigan 2 qatlamli neyron tarmoq (noaniq holatlarda
  yo'nalish beradi; yo'q bo'lsa ham miya ishlaydi)
- **PyTorch (rating_nn.py)** — reytingi noma'lum kontentlarga TAXMINIY
  reyting beradigan 3 qatlamli MLP (kategoriya, yil, janrlar,
  ko'rishlar soni va holatdan o'rganadi); natija tavsiyada
  "★~4.9 (AI)" shaklida ko'rsatiladi

## Portni o'zgartirish

Agar 11434 band bo'lsa:
```bash
set PORT=11500
py ai_server.py
```
va sayt `.env` faylida `OLLAMA_URL=http://localhost:11500/api/chat` qiling.