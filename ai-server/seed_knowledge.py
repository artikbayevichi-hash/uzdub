# -*- coding: utf-8 -*-
"""
BILIM BAZASINI KENGAYTIRISH (seed_knowledge.py)
================================================
UZDUB AI miyasiga yangi BILIMLAR qo'shadi (ai_knowledge jadvaliga).

Nega kerak? — "ko'proq bilim" so'rovingiz uchun:
  "kino nima?", "anime nima?", "sun'iy intellekt nima?" kabi savollarga
  endi AI o'zi aniq, o'zbekcha javob bera oladi (avval "javobim yo'q"
  der edi).

Qanday ishlaydi:
  - Har bir qator: mavzu (title) -> tushuntirish (content)
  - Bor qatorlar o'zgartirilmaydi (qayta ishga tushirsa ham xavfsiz)
  - `ask` intentsi "X nima?" savolida shu jadvaldan qidiradi

Ishga tushirish:  py -X utf8 seed_knowledge.py
"""
import sys

try:
    import pymysql
except ImportError:
    print("pymysql topilmadi — avval: py -m pip install pymysql")
    sys.exit(1)

DB_CONFIG = {
    "host": "localhost", "port": 3306, "user": "root",
    "password": "", "database": "uzdub", "charset": "utf8mb4",
}

# ---------------------------------------------------------------------------
# YANGI BILIMLAR: mavzu -> tushuntirish  (title LIKE %mavzu% bo'yicha topiladi)
# ---------------------------------------------------------------------------
KNOWLEDGE = [
    ("kino", "kino — harakatlanuvchi tasvirlar orqali hikoya aytib beruvchi "
             "san'at turi. Qisqasi: ko'chma suratlar + ovoz + hikoya = kino 🎬"),
    ("film", "film — kino san'atining asari. Janrlarga bo'linadi: komediya, "
             "drama, triller, fantastika, sarguzasht va boshqalar."),
    ("anime", "anime — Yaponiyada yaratilgan multifilm uslubi. O'ziga xos "
              "chizmasi, katta ko'zlari va chuqur hikoyalari bilan tanilgan. "
              "Yoshu qari hammaga yoqadi! 🎌"),
    ("multfilm", "multfilm — chizilgan yoki kompyuterda yasalgan personajlar "
                 "ishtirokidagi animatsion kino. Bolalar uchun ham, kattalar "
                 "uchun ham zo'r multfilmlar bor!"),
    ("animatsiya", "animatsiya — jonlantirish san'ati: rasmlar yoki modellar "
                   "ketma-ket almashib, harakat hissi paydo qiladi. Multfilm "
                   "va animelarning asosi shu."),
    ("sun'iy intellekt", "sun'iy intellekt (AI) — mashinaning inson kabi "
                         "o'rganish, fikrlash va qaror qabul qilish qobiliyati. "
                         "Men ham shunday tizimman! 🧠"),
    ("neyron tarmoq", "neyron tarmoq — sun'iy intellektning miya modeli: "
                      "xuddi miya neyronlaridek bog'langan qatlamlar ma'lumot "
                      "o'rganadi. Mening niyat tanlovim va reyting bashoratim "
                      "shu bilan ishlaydi."),
    ("PyTorch", "PyTorch — Facebook tomonidan yaratilgan ochiq kodli neyron "
                "tarmoq kutubxonasi. Men undan foydalanuvchining intentsini "
                "aniqlash va film reytingini bashorat qilishda foydalanaman."),
    ("Python", "Python — oddiy va kuchli dasturlash tili. Men (UZDUB AI) "
               "aynan Python'da 0 dan yozilganman! 🐍"),
    ("chatbot", "chatbot — inson bilan suhbatlashuvchi dastur. Men yordamchi "
                "chatbotman: kino, anime, multfilm bo'yicha tavsiya beraman, "
                "xotin-qizlar kayfiyatini tushunaman va o'z-o'zimni rivojlantiraman."),
    ("Ollama", "Ollama — shaxsiy kompyuterda katta til modellarini ishga "
               "tushirish platformasi. Saytimiz eski Ollama'ga o'xshab so'rov "
               "yuborardi, endi esa javobni MEN beraman!"),
    ("xotira", "AI xotirasi — o'rganilgan bilimlarni saqlash joyi. Mening "
               "xotiram = ai_knowledge jadvali: afzalliklar, fikr-mulohazalar "
               "va yangi bilimlar u yerda yotadi."),
    ("reyting", "reyting — foydalanuvchilarning baholari asosida kontentning "
                "mashhurlik ko'rsatkichi. Men reytingni neyron tarmoq bilan "
                "bashorat ham qila olaman!"),
    ("premium", "premium — UZDUB saytining pullik obunasi: reklamasiz tomosha, "
                "exklyuziv kontent va yuqori sifat. Profil bo'limidan ulash mumkin."),
    ("uzdub", "UZDUB — O'zbek tilidagi kino, anime va multfilm platformasi. "
              "Administrator e'lonlar qo'shadi, men esa foydalanuvchilarga "
              "tavsiya beraman! 🎬"),
    ("server", "server — so'rovlarni qabul qilib, javob qaytaradigan dastur. "
               "Saytimiz meni Flask serveri orqali chaqiradi (127.0.0.1:11434)."),
    ("api", "API — dasturlar o'rtasidagi kelishilgan aloqa interfeysi. "
            "Sayt my-ga Ollama'ga yuboradigan API so'rovlarini yuboradi, "
            "men o'sha formatda javob beraman."),
    ("ma'lumotlar bazasi", "ma'lumotlar bazasi (DB) — tartiblangan ma'lumotlar "
                           "ombori. Men MySQL'dagi uzdub bazasidan foydalanaman "
                           "(ai_knowledge jadvali — mening xotiram)."),
    ("komediya", "komediya — kulgili janr: hazil, kulgili vaziyatlar va yorqin "
                 "personajlar. Yaxshi kayfiyat uchun eng zo'r tanlov! 😄"),
    ("drama", "drama — inson his-tuyg'ulari va hayotiy vaziyatlarni chuqur "
              "tasvirlovchi jiddiy janr. Ko'z yoshi va fikr uyg'otadi."),
    ("triller", "triller — keskin, sirlarga to'la va taranglikni oshiradigan "
                "janr. Tomoshabin nafasini ushlab qaraydi! 😱"),
    ("fantastika", "fantastika — ilmiy-fantastik olamlar, texnologiyalar va "
                   "kelajak haqidagi janr. Kosmos, robotlar, vaqt sayohati!"),
    ("sarguzasht", "sarguzasht — qahramonning sarguzashtli sayohati haqidagi "
                   "janr: xazina izlash, o'rmonlar, janglar va kashfiyotlar! 🗺️"),
    ("qo'rqinchli", "qo'rqinchli — dahshat va qo'rquv uyg'otuvchi janr (gorror). "
                    "Jasurlar uchun! Qorong'ida tomosha qilmang... 👻"),
    ("qorqinchli", "qorqinchli — dahshat va qo'rquv uyg'otuvchi janr (gorror). "
                   "Jasurlar uchun! Qorong'ida tomosha qilmang... 👻"),
    ("romantika", "romantika — sevgi va muhabbat haqidagi janr. Yurakka iliq "
                  "his tuyg'u beradi, juftlar uchun mukammal! 💕"),
    ("detektiv", "detektiv — jinoyatni ochish va sirni topish haqidagi janr. "
                 "Aqlli kuzatuvchilar uchun! 🕵️"),
    ("janr", "janr — asarning turkumi: komediya, drama, triller, fantastika... "
             "UZDUB'da janr bo'yicha qidirish mumkin: «komediya kino» diyaversangiz "
             "kifoya!"),
    ("rejissyor", "rejissyor — filmni boshqaruvchi ijodkor: aktyorlar, kamera "
                  "va hikoya — hammasi uning qo'lida. Filmning «muallifi» deyish mumkin."),
    ("kino tarixi", "kino tarixi 1895-yilda Lumiere aka-ukalarning birinchi "
                    "seansidan boshlangan. Dastlab ovozsiz edi, keyin ovoz, "
                    "rang va kompyuter animatsiyasi paydo bo'ldi!"),
    # --- GEOGRAFIYA / O'ZBEKISTON ---
    ("o'zbekiston", "O'zbekiston — Markaziy Osiyodagi qadimiy davlat. "
                    "Poytaxti — Toshkent. 12 viloyat bor. Aholisi 36 milliondan "
                    "ortiq. Samarkand, Buxoro, Xiva — jahon tarixida mashhur "
                    "shaharlar. 🇺🇿"),
    ("toshkent", "Toshkent — O'zbekistonning poytaxti va eng katta shahri. "
                 "Aholisi 3 millionga yaqin. Havoriylik bekati emas, balki "
                 "ko'kalamzor bog'lar va metro yulduzi bilan mashhur! 🏙️"),
    ("samarqand", "Samarkand — «Sharq qavasi» deb ataluvchi qadimiy shahar. "
                  "Registon maydoni, Shahizinda va Ulug'bek rasadxonasi jahonga "
                  "mashhur. 2750 yildan oshiq tarixga ega! 🏛️"),
    ("buxoro", "Buxoro — 2500 yillik tarixli shahar, Ipak yo'li markazi. "
               "Minorai Kalon, Ark qal'asi va savdo gumbazlari bilan tanilgan."),
    ("xiva", "Xiva — ochiq osmon ostidagi muzey shahri! Ichon-Qal'a qal'asi "
             "UNESCO ro'yxatiga kiritilgan."),
    ("poytaxt", "Poytaxt — davlatning bosh shahri. O'zbekiston poytaxti — "
                "Toshkent. Filmlarda ko'p shaharlar «poytaxt» deb ataladi 🎬"),
    ("dengiz", "Dengiz — quruqlikdan katta suv maydoni. Yer yuzining 70%ini "
               "okean va dengizlar egallaydi. Eng katta dengiz? Kaspiv! 🌊"),
    ("okean", "Okean — ulkan suv maydoni: Tinch, Atlantika, Hind va Shimoliy "
              "Muz okeani. Yer yuzidagi eng katta suv havzalari."),
    ("tog'", "Tog'lar — Yer qobig'ining ko'tarilgan qismi. O'zbekistonda "
             "Hisor, Chotqol va Tyan-Shan tog'lari bor. Eng baland nuqta — "
             "Xazrati Sulton (4643 m)! ⛰️"),
    ("daryo", "Daryo — tabiiy suv oqimi: Amudaryo va Sirdaryo O'zbekistonning "
              "ikki buyuk daryosi. «Oq oltin» — paxta shu daryolardan sug'oriladi."),
    # --- FAN / OLAM ---
    ("gravitatsiya", "Gravitatsiya — jismlarni bir-biriga tortadigan kuch. "
                     "Olma yerda qolishi ham, Oyning Yer atrofida aylanishi ham "
                     "shu kuch bilan bog'liq! Nyuton kashf etgan. 🍎"),
    ("elektr", "Elektr — zaryadlangan zarralar oqimi. Uyimizdagi lampa, "
               "kompyuter va kino projektori elektr bilan ishlaydi ⚡"),
    ("yorug'lik", "Yorug'lik — eng tez narsa: sekundiga 300 000 km! Quyosh "
                  "nuri Yerga 8 daqiqada yetib keladi."),
    ("quyosh", "Quyosh — bizning yulduzimiz, o'rtacha yulduz. Yer Quyosh "
               "atrofida 365 kunda aylanadi. Quyoshsiz hayot bo'lmas edi ☀️"),
    ("oy", "Oy — Yerning tabiiy yo'ldoshi. Yerga eng yaqin osmon jismi. "
           "Oyga 1969-yilda birinchi odam qadam qo'ygan (Nil Armstrong). 🌙"),
    ("yulduz", "Yulduz — o'z yorug'ligini chiqaradigan ulkan gaz shar. "
               "Osmonda ko'rgan yulduzlar — katta masofadagi quyoshlar! ✨"),
    ("sayyora", "Sayyora — yulduz atrofida aylanadigan osmon jismi. Quyosh "
                "tizimida 8 sayyora bor: Merkuriy, Venera, Yer, Mars, Yupiter, "
                "Saturn, Uran, Neptun. 🪐"),
    ("dinozavr", "Dinozavrlar — 65 million yil avval Yerda yashagan ulkan "
                 "kaltakesaklar. T-Rex eng mashhuri! Ular haqida juda ko'p "
                 "filmlar bor 🦖"),
    ("havo", "Havo — Yerni o'rab turgan gaz qatlami: kislorod, azot va "
             "boshqalar. Uning yordamida nafas olamiz va samolyotlar uchadi."),
    # --- TEXNIKA / INTERNET ---
    ("internet", "Internet — butun dunyodagi kompyuterlarni bog'laydigan tarmoq. "
                 "Saytlar, video va AI'lar shu tarmoqda ishlaydi 🌐"),
    ("kompyuter", "Kompyuter — dasturlar bilan ishlaydigan elektron mashina. "
                  "Birinchi kompyuterlar xona kattaligida edi, endi esa "
                  "cho'ntakda! 💻"),
    ("telefon", "Telefon — ovoz va matn uzatadigan qurilma. «Telefon» grekcha: "
                "tele (uzoq) + fon (ovoz). Smartfon esa — cho'ntakdagi "
                "kompyuter! 📱"),
    ("ilova", "Ilova (app) — telefon yoki kompyuter uchun dastur. Telegram, "
              "YouTube, o'yinlar — hammasi ilovalar."),
    ("dastur", "Dastur — kompyuterga nima qilishni buyuradigan ko'rsatmalar "
               "majmui. Python, PHP, JavaScript — dasturlash tillari."),
    ("veb-sayt", "Veb-sayt — internetdagi sahifalar to'plami. UZDUB ham "
                 "veb-sayt! Saytlar HTML, CSS va PHP bilan quriladi."),
    ("wifi", "Wi-Fi — internetsiz simsiz ulanish texnologiyasi. Uyda, kafeda "
             "va «Poytaxt bayrami» da ham hamma Wi-Fi so'raydi 😄"),
    ("parol", "Parol — hisobingizni himoya qiluvchi maxfiy so'z. Kuchli parol: "
              "8+ belgi, raqam va belgilar bilan. Hech kimga bermang! 🔐"),
    ("xavfsizlik", "Internet xavfsizligi — parollar, viruslar va aldashlardan "
                   "himoya. Noma'lum havolalarni bosmang, parolni sir saqlang!"),
    ("brauzer", "Brauzer — veb-saytlarni ko'rsatadigan dastur: Chrome, "
                "Firefox, Edge, Safari. UZDUB'ni istalgan brauzerda oching!"),
    # --- KINO OLAMI (chuqur bilim) ---
    ("kinoteatr", "Kinoteatr — katta ekranda film ko'rsatadigan joy. Birinchi "
                  "kinoteatr 1895-yilda Parijda ochilgan. Popkorn kinoteatrning "
                  "ajralmas qismi! 🍿"),
    ("oskar", "Oscar — kinoning eng nufuzli mukofoti. Har yili Gollivudda "
              "taqdim etiladi. «Eng yaxshi film» — asosiy nominatsiya! 🏆"),
    ("premyera", "Premyera — filmning birinchi namoyishi. Yangi filmlar "
                 "odatda premyeradan keyin platformalarda chiqadi."),
    ("dublyaj", "Dublyaj — filmni boshqa tilga ovozlashtirish. O'zbekcha "
                "dublyajli animelar yoshlar orasida juda ommabop! 🎙️"),
    ("subtitr", "Subtitr — ekrandagi tarjima matni. Filmni asl tilida "
                "subtitr bilan ko'rish tili o'rganishga ham yordam beradi."),
    ("aktyor", "Aktyor — filmda rol ijro etuvchi san'atkor. Mashhur aktyorlar "
               "filmni xalq sevadigan yulduzga aylantiradi. ⭐"),
    ("prodyuser", "Prodyuser — filmning moliyasi va tashkiliy ishlarini "
                  "boshqaruvchi shaxs. Rejissyor «filmni yaratadi», prodyuser "
                  "«uni amalga oshiradi»."),
    ("ssenariy", "Ssenariy — filmning yozma hikoyasi: dialoglar va sahnlar. "
                 "Har bir ajoyib film ajoyib ssenariydan boshlanadi! 📝"),
    ("o'zbek kinosi", "O'zbek kinosi o'ziga xos maktabga ega: «O'tgan kunlar», "
                      "«Shum bola» kabi filmlar milliy kinoning durdonalari. "
                      "Hozir yangi o'zbek filmlari ham mashhur!"),
    ("tizer", "Tizer — filmning qisqa, qiziqarli oldi-treyleri. Tizerlarda "
              "asosiy voqea emas, kayfiyat ko'rsatiladi!"),
    # --- SALOMATLIK / HAYOT ---
    ("uyqu", "Uyqu — salomatlikning asosi! Kattalarga 7-8 soat uyqu kerak. "
             "Uyqu vaqtida miya o'rganilganlarni «arxivlaydi» 😴"),
    ("vitamin", "Vitaminlar — organizm uchun zarur moddalar: A, B, C, D... "
                "Sabzavot-mevalar vitaminlarga boy. Vitamin D quyoshdan "
                "olinadi! 🥕"),
    # --- SPORT ---
    ("futbol", "Futbol — dunyodagi eng ommabop sport. Har 4 yilda jahon "
               "chempionati bo'ladi. O'zbekiston termasi ham yirik "
               "turnirlarga chiqmoqda! ⚽"),
    ("kurash", "Kurash — o'zbek milliy sport turi! Yoshi katta va yosh "
               "kurashchilar xalqaro chempionatlarda g'olib chiqmoqda. 🤼"),
    ("shaxmat", "Shaxmat — strategiya o'yini, «aql sporti». 64 katak, 16 "
                "har bir tomonda. O'zbekiston shaxmatchilari jahon "
                "shaxmatini zabt etmoqda! ♟️"),
    # --- OVQAT / MADANIYAT ---
    ("choy", "Choy — o'zbek mehmondo'stligining ramzi! «Choysiz mehmon "
             "qolmas». Yashil choy — sharqona, qora choy — yevropacha. 🫖"),
    ("palov", "Palov — O'zbekistonning milliy taomi. Har hududnning o'z "
              "palovi bor: Samarqand, Buxoro, Xorazm palovi... 🍚"),
    ("non", "Non — muqaddas taom! O'zbek dasturxonida non asosiy o'rin "
            "egallaydi. Tandir noni eng mazalisi deb hisoblanadi."),
]

ADDED = []
SKIPPED = []


def main():
    total = 0
    try:
        conn = pymysql.connect(**DB_CONFIG)
    except Exception as e:
        print(f"Bazaga ulanishda xato: {e}")
        print("XAMMY server ishlayotganini tekshiring (xampp -> MySQL).")
        return

    try:
        with conn.cursor() as cur:
            for title, content in KNOWLEDGE:
                cur.execute(
                    "SELECT id FROM ai_knowledge WHERE title = %s "
                    "ORDER BY id DESC LIMIT 1", (title,))
                if cur.fetchone():
                    SKIPPED.append(title)
                    continue
                cur.execute(
                    "INSERT INTO ai_knowledge (title, content, status) "
                    "VALUES (%s, %s, 'approved')", (title, content))
                ADDED.append(title)
            cur.execute(
                "SELECT COUNT(*) FROM ai_knowledge WHERE status='approved'")
            row = cur.fetchone()
            total = row[0] if row else 0
        conn.commit()
    except Exception as e:
        print(f"Yozishda xato: {e}")
    finally:
        conn.close()

    print(f"✅ Yangi qo'shildi: {len(ADDED)} ta")
    for t in ADDED:
        print(f"   + {t}")
    if SKIPPED:
        print(f"⏭️  O'tkazib yuborildi (allaqachon bor): {len(SKIPPED)} ta")
        for t in SKIPPED:
            print(f"   = {t}")
    print(f"📊 Jami bilim qatorlari: {total or '?'}")
    print("\nEndi «anime nima?», «sun'iy intellekt nima?» kabi savollarni sinab ko'ring!")


if __name__ == "__main__":
    main()