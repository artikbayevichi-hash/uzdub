# -*- coding: utf-8 -*-
"""
MEGA BILIM BAZASI (seed_knowledge_mega.py)
==========================================
UZDUB AI miyasiga 300+ YANGI aniq bilim qo'shadi (ai_knowledge jadvaliga).

Nega? — axborot savollariga Internetga chiqmasdan aniq, o'zbekcha javob:
  "fransiya poytaxti", "dunyodagi eng katta davlat", "amir temur kim",
  "yorug'lik tezligi qancha" ... kabilar endi 0.01 soniyada, xatosiz.

Xavfsizlik:
  - Bor title'lar O'ZGARTIRILMAYDI (qayta ishga tushirsa ham xavfsiz)
  - Bir xil bilim ai_knowledge.json'ga ham qo'shiladi (oflayn zaxira)

Ishga tushirish:  py -X utf8 seed_knowledge_mega.py
"""
import json
import pathlib
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
# YANGI BILIMLAR: title -> aniq javob (savol shakli ham, asosiy kalit ham)
# ---------------------------------------------------------------------------
KNOWLEDGE = [
    # ============ O'ZBEKISTON ============
    ("o'zbekiston poytaxti", "Toshkent — O'zbekistonning poytaxti va eng yirik shahri. Aholisi 2,5 milliondan ortiq!"),
    ("o'zbekiston aholisi", "O'zbekiston aholisi 36 milliondan ortiq — Markaziy Osiyodagi eng ko'p aholili davlat."),
    ("o'zbekiston maydoni", "O'zbekiston maydoni 448 978 km². Markaziy Osiyoning markazida joylashgan."),
    ("o'zbekiston viloyatlari", "O'zbekistonda 12 viloyat va Qoraqalpog'iston Respublikasi bor. Poytaxti — Toshkent shahri."),
    ("o'zbekiston eng katta shahri", "Toshkent — O'zbekistonning eng katta shahri. Undan keyin Samarqand, Namangan, Andijon va Buxoro keladi."),
    ("o'zbekiston qo'shnilari", "O'zbekiston 5 davlat bilan chegaradosh: Qozog'iston, Qirg'iziston, Tojikiston, Afg'oniston va Turkmaniston."),
    ("o'zbekiston eng uzun daryosi", "Sirdaryo — O'zbekiston hududidagi eng uzun daryo (2 212 km). Amudaryo ham yirik daryo."),
    ("amudaryo", "Amudaryo — O'zbekistonning janubidagi yirik daryo, uzunligi 2 540 km. Orol dengiziga quyiladi."),
    ("sirdaryo", "Sirdaryo — O'zbekistonning eng uzun daryosi (2 212 km). Qozog'iston orqali Orolga boradi."),
    ("o'zbekiston eng baland nuqtasi", "Hisor tog'laridagi Hazrat Sulton cho'qqisi (4 643 m) — O'zbekistonning eng baland nuqtasi."),
    ("o'zbekiston tog'lari", "O'zbekistonning asosiy tog'lari: Hisor, Zarafshon, Turkiston va Chatqol tog'lari. Eng balandi — Hazrat Sulton (4 643 m)."),
    ("qizilqum", "Qizilqum — Markaziy Osiyodagi katta cho'l, maydoni 298 000 km². O'zbekiston hududining katta qismini egallaydi."),
    ("orol dengizi", "Orol dengizi — O'zbekiston va Qozog'iston orasidagi sobiq katta ko'l. 1960-yillardan keyin qurib qoldi, hozir qisman tiklanmoqda."),
    ("navro'z", "Navro'z — 21-martda nishonlanadigan bahor va yangi yil bayrami. Qadimdan Sharq xalqlarining eng sevimli bayrami!"),
    ("mustaqillik kuni", "Mustaqillik kuni — 1-sentabr. 1991-yilda O'zbekiston mustaqil davlat deb e'lon qilindi."),
    ("o'zbekiston konstitutsiyasi", "Konstitutsiya kuni — 8-dekabr. 1992-yilda O'zbekiston Konstitutsiyasi qabul qilingan."),
    ("til bayrami", "O'zbek tili bayrami — 21-oktabr. 1989-yilda o'zbek tiliga davlat tili maqomi berilgan."),
    ("xotira kuni", "Xotira va qadrlash kuni — 9-may. Ikkinchi jahon urushida halok bo'lganlar xotirasi hurmat qilinadi."),
    ("registon", "Registon — Samarqand markazidagi mashhur maydon: Ulug'bek, Sherdor va Tillakori madrasalari joylashgan. Eng go'zal Sharq yodgorliklaridan!"),
    ("ichan qal'a", "Ichan-Qal'a — Xiva shahridagi qadimiy shahar-qal'a, UNESCO Jahon merosi ro'yxatiga kiritilgan."),
    ("buxoro me'morchiligi", "Buxoro — «musulmon Sharqining durdonasi» deb ataladi. Kalon minorasi va Ark qal'asi mashhur."),
    ("samarqand", "Samarqand — O'zbekistonning qadimiy shahri, sobiq poytaxt. Registon, Gur-Amir va Shahi-Zinda mashhur yodgorliklari bor."),
    ("shohi zinda", "Shohi-Zinda — Samarqanddagi qadimiy maqbaralar majmuasi, «tirik podshoh» degan ma'noni anglatadi."),
    ("gur amir", "Gur-Amir — Samarqanddagi Amir Temur maqbarasi. Buyuk sarkarda shu yerda dafn etilgan."),
    ("toshkent tarixi", "Toshkent — 2 200 yillik tarixga ega qadimiy shahar. 1966-yilgi zilziladan keyin to'liq qayta qurilgan."),
    ("toshkent metropoliteni", "Toshkent metropoliteni 1977-yilda ochilgan — Markaziy Osiyodagi birinchi metro!"),
    ("paxta", "O'zbekiston paxtachilikda dunyoda yetakchi davlatlardan — «oq oltin» deb ataladi. Paxta tozalash zavodlari ko'p."),
    ("o'zbek oltini", "O'zbekiston oltin zahiralari bo'yicha dunyoda 4-o'rinda; Muruntau koni eng yirik ochiq oltin konidir."),
    ("palov", "Palov — O'zbekistonning milliy taomi: guruch, go'sht, sabzi va ziravorlardan tayyorlanadi. Har hududning o'z uslubi bor!"),
    ("o'zbek milliy taomlari", "O'zbek taomlari: palov, manti, shashlik, lag'mon, somsa, mastava va qozon kabob. Choy esa mehmondo'stlik ramzi."),
    ("non", "Non — o'zbek dasturxonining muqaddas taomi. Qadimgi urfga ko'ra nonni yerga tashlab bo'lmaydi."),
    ("kurash", "Kurash — o'zbekcha milliy sport turi. 2018-yildan Osiyo o'yinlari dasturiga kiritilgan."),
    ("ulug' bek rasadxonasi", "Ulug'bek rasadxonasi — Samarqanddagi 15-asrga oid astronomik rasadxona. O'sha davrning eng ilg'or ilmiy markazi edi."),
    ("buyuk ipak yo'li", "Buyuk Ipak yo'li — qadimiy savdo yo'li: Xitoydan Yevropagacha. Samarqand, Buxoro va Xiva uning muhim shaharlari edi."),
    ("xorazm", "Xorazm — O'zbekistonning qadimiy viloyati. Xiva va Urganch shaharlari bor."),

    # ============ DUNYO GEOGRAFIYASI ============
    ("dunyodagi eng katta davlat", "Rossiya — dunyodagi eng katta davlat, maydoni 17,1 mln km². Undan keyin Kanada va Xitoy keladi."),
    ("dunyodagi eng kichik davlat", "Vatikan — dunyodagi eng kichik davlat (0,44 km²). Rim shahri ichida joylashgan."),
    ("dunyodagi eng ko'p aholili davlat", "Hindiston — dunyodagi eng ko'p aholili davlat (1,4 mlrd+). Xitoy ikkinchi o'rinda."),
    ("fransiya poytaxti", "Parij — Fransiyaning poytaxti va eng yirik shahri. «Nurli shahar» deb ataladi. Eyfel minorasi shu yerda!"),
    ("fransiya", "Fransiya — G'arbiy Yevropadagi davlat. Poytaxti Parij. Eyfel minorasi, Luvr muzeyi va oshxonasi mashhur."),
    ("rossiya poytaxti", "Moskva — Rossiyaning poytaxti va eng yirik shahri. Kreml va Qizil maydon shu yerda."),
    ("rossiya", "Rossiya — dunyodagi eng katta davlat. Poytaxti Moskva. Transsibir temir yo'li dunyodagi eng uzun temir yo'ldir."),
    ("xitoy poytaxti", "Pekin — Xitoyning poytaxti. Buyuk Xitoy devori va Taqiqlangan shahar (Forbidden City) shu yerda."),
    ("xitoy", "Xitoy — dunyodagi eng ko'p aholili davlatlardan (1,4 mlrd). Poytaxti Pekin. Buyuk Xitoy devori 21 000 km."),
    ("aqsh poytaxti", "Vashington — AQShning poytaxti. Oq uy va Kongress binosi shu yerda joylashgan."),
    ("aqsh", "Amerika Qo'shma Shtatlari — Shimoliy Amerikadagi yirik davlat. Poytaxti Vashington. Nyu-York eng yirik shahri."),
    ("angliya poytaxti", "London — Angliya (Buyuk Britaniya) poytaxti. Big Ben va Temza daryosi mashhur."),
    ("angliya", "Angliya — Buyuk Britaniya tarkibidagi davlat. Poytaxti London. Big Ben va London ko'prigi mashhur."),
    ("germaniya poytaxti", "Berlin — Germaniyaning poytaxti. Brandenburg darvozasi mashhur yodgorlik."),
    ("germaniya", "Germaniya — Yevropaning markazidagi yirik davlat. Poytaxti Berlin. Avtomobil sanoati (BMW, Mercedes) bilan mashhur."),
    ("yaponiya poytaxti", "Tokio — Yaponiyaning poytaxti va dunyodagi eng yirik shahar aglomeratsiyasi."),
    ("yaponiya", "Yaponiya — Sharqiy Osiyodagi orollar davlati. Poytaxti Tokio. Texnologiyalar, anime va sushi bilan mashhur!"),
    ("hindiston poytaxti", "Nyu-Dehli — Hindistonning poytaxti. Mumbay eng yirik shahri."),
    ("hindiston", "Hindiston — Janubiy Osiyodagi yirik davlat, 1,4 milliard aholi. Poytaxti Nyu-Dehli. Taj Mahal mashhur."),
    ("qozog'iston poytaxti", "Astana — Qozog'istonning poytaxti. Eng yirik shahri — Olmaota (sobiq poytaxt)."),
    ("qozog'iston", "Qozog'iston — Markaziy Osiyodagi eng katta davlat. Poytaxti Astana. Bayqo'nur kosmodromi shu yerda."),
    ("turkiya poytaxti", "Anqara — Turkiyaning poytaxti. Eng yirik shahri — Istanbul."),
    ("turkiya", "Turkiya — Yevropa va Osiyo kesishgan joydagi davlat. Poytaxti Anqara. Istanbul go'zal shahri mashhur."),
    ("eron poytaxti", "Tehron — Eronning poytaxti va eng yirik shahri."),
    ("eron", "Eron — G'arbiy Osiyodagi qadimiy davlat. Poytaxti Tehron. Qadimda Fors imperiyasi bo'lgan."),
    ("tojikiston poytaxti", "Dushanbe — Tojikistonning poytaxti."),
    ("tojikiston", "Tojikiston — Markaziy Osiyodagi tog'li davlat. Poytaxti Dushanbe. Eng baland cho'qqisi — Ismoil Somoniy (7 495 m)."),
    ("qirg'iziston poytaxti", "Bishkek — Qirg'izistonning poytaxti."),
    ("qirg'iziston", "Qirg'iziston — Markaziy Osiyodagi tog'li davlat. Poytaxti Bishkek. Issiqko'l ko'li mashhur."),
    ("turkmaniston poytaxti", "Ashxobod — Turkmanistonning poytaxti."),
    ("turkmaniston", "Turkmaniston — Markaziy Osiyodagi davlat. Poytaxti Ashxobod. Gaz zaxiralari katta."),
    ("afg'oniston poytaxti", "Kobul — Afg'onistonning poytaxti."),
    ("afg'oniston", "Afg'oniston — Markaziy Osiyodagi tog'li davlat. Poytaxti Kobul. O'zbekiston bilan chegaradosh."),
    ("misr poytaxti", "Qohira — Misrning poytaxti va Afrika'dagi eng yirik shaharlardan. Piramidalar yaqin joyda."),
    ("misr", "Misr — Afrika shimoli-sharqidagi qadimiy davlat. Poytaxti Qohira. Piramidalar va Nil daryosi mashhur."),
    ("ispaniya poytaxti", "Madrid — Ispaniyaning poytaxti. Barselona eng mashhur shaharlaridan."),
    ("ispaniya", "Ispaniya — Janubiy Yevropadagi davlat. Poytaxti Madrid. Paelya va flamenko raqsi mashhur."),
    ("italiya poytaxti", "Rim — Italiyaning poytaxti. Qadimiy imperiyaning yuragi bo'lgan."),
    ("italiya", "Italiya — Janubiy Yevropadagi davlat. Poytaxti Rim. Pizza, makaron va Rim kolizeyi mashhur."),
    ("kanada poytaxti", "Ottava — Kanadaning poytaxti. Toronto eng yirik shahri."),
    ("kanada", "Kanada — Shimoliy Amerikadagi davlat, maydoni bo'yicha dunyoda 2-o'rinda. Poytaxti Ottava."),
    ("avstraliya poytaxti", "Kanberra — Avstraliyaning poytaxti. Sidney eng yirik shahri."),
    ("avstraliya", "Avstraliya — materik sifatidagi yagona davlat. Poytaxti Kanberra. Kenguru va koala shu yerda!"),
    ("braziliya poytaxti", "Brasilia — Braziliyaning poytaxti. San-Paulu eng yirik shahri."),
    ("braziliya", "Braziliya — Janubiy Amerikadagi eng katta davlat. Poytaxti — Brasilia, Rio-de-Janeyro mashhur."),
    ("koreya poytaxti", "Seul — Janubiy Koreyaning poytaxti. K-pop va texnologiyalar markazi!"),
    ("janubiy koreya", "Janubiy Koreya — Sharqiy Osiyodagi davlat. Poytaxti Seul. Samsung, K-pop va kino sanoati mashhur."),
    ("shveysariya poytaxti", "Bern — Shveysariyaning poytaxti. Tsyurix eng yirik shahri."),
    ("shveysariya", "Shveysariya — Alp tog'laridagi davlat. Poytaxti Bern. Soat va shokolad sanoati bilan mashhur."),
    ("gollandiya poytaxti", "Amsterdam — Gollandiya (Niderlandiya) poytaxti. Lolalar va kanallar shahri."),
    ("gollandiya", "Gollandiya — Yevropadagi davlat. Poytaxti Amsterdam. Lolalar, shamol tegirmonlari va velosipedlar mashhur."),
    ("belgiya poytaxti", "Bryussel — Belgiyaning poytaxti, Yevropa Ittifoqi markazi."),
    ("buxarest", "Buxarest — Ruminiyaning poytaxti va eng yirik shahri."),
    ("varshava", "Varshava — Polshaning poytaxti va eng yirik shahri."),
    ("praga", "Praga — Chexiyaning poytaxti, «Yevropaning yuragi» deb ataladi."),
    ("budapesht", "Budapesht — Vengriyaning poytaxti. Duna daryosi ikki qismga bo'ladi."),
    ("afina", "Afina — Gretsiyaning poytaxti. Qadimgi Olimpiya o'yinlari vatani."),
    ("stokgolm", "Stokgolm — Shvetsiyaning poytaxti, orollar ustida qurilgan go'zal shahar."),
    ("oslo", "Oslo — Norvegiyaning poytaxti. Nobel tinchlik mukofoti shu yerda topshiriladi."),
    ("kopengagen", "Kopengagen — Daniyaning poytaxti. Kichkina suv parisi haykali mashhur."),
    ("dublin", "Dublin — Irlandiyaning poytaxti."),
    ("dunyo okeanlari", "Dunyoda 5 okean bor: Tinch, Atlantika, Hind, Shimoliy Muz va Janubiy okeanlar."),
    ("eng katta okean", "Tinch okeani — dunyodagi eng katta okean (165 mln km²), Yer yuzasining uchdan birini egallaydi."),
    ("eng chuqur joy", "Mariana botig'i (11 034 m) — okeandagi eng chuqur nuqta, Tinch okeanida."),
    ("eng katta ko'l", "Kaspiy dengizi — dunyodagi eng katta ko'l (371 000 km²). Aslida dengiz emas, ko'l!"),
    ("eng chuqur ko'l", "Baykal ko'li — dunyodagi eng chuqur ko'l (1 642 m). Sibirda joylashgan."),
    ("eng uzun daryo", "Nil — dunyodagi eng uzun daryo (6 650 km). Misr orqali oqadi."),
    ("eng katta daryo", "Amazonka — suv miqdori bo'yicha dunyodagi eng katta daryo. Janubiy Amerikada."),
    ("volga daryosi", "Volga — Yevropadagi eng uzun daryo (3 530 km). Rossiyada."),
    ("eng baland tog'", "Everest (Chomolungma) — dunyodagi eng baland cho'qqi, 8 849 m. Himolay tog'larida."),
    ("eng katta cho'l", "Saxara — dunyodagi eng katta issiq cho'l (9 mln km²). Afrika shimolida."),
    ("eng sovuq joy", "Oymyakon (Rossiya) — yer yuzidagi eng sovuq aholi punkti: -67,7°C gacha kuzatilgan."),
    ("eng issiq joy", "Death Valley (AQSh) — eng issiq joylardan: havo harorati +56,7°C gacha ko'tarilgan."),
    ("materiklar", "Dunyoda 6 materik bor: Yevrosiyo, Afrika, Shimoliy Amerika, Janubiy Amerika, Avstraliya va Antarktida."),
    ("eng katta materik", "Yevrosiyo — dunyodagi eng katta materik. Yer quruqligining 36% ini egallaydi."),
    ("eng kichik materik", "Avstraliya — dunyodagi eng kichik va eng quruq materik."),
    ("nil daryosi", "Nil — Afrikadagi eng uzun daryo (6 650 km). Misr sivilizatsiyasi shu daryo bo'yida o'sgan."),
    ("amazonka", "Amazonka — Janubiy Amerikadagi eng katta daryo, o'rmon zich joylaridan oqadi."),

    # ============ FAN ============
    ("yorug'lik tezligi", "Yorug'lik tezligi — sekundiga 299 792 km (taxminan 300 000 km/s). Tabiatdagi eng katta tezlik!"),
    ("tovush tezligi", "Tovush havoda sekundiga taxminan 343 metr tezlikda tarqaladi (20°C da)."),
    ("yorug'lik yili", "Yorug'lik yili — yorug'likning bir yilda bosib o'tadigan masofasi: 9,46 trillion km. Kosmosdagi masofalar shu bilan o'lchanadi."),
    ("quyosh tizimida nechta sayyora", "Quyosh tizimida 8 sayyora bor: Merkuriy, Venera, Yer, Mars, Yupiter, Saturn, Uran va Neptun."),
    ("eng katta sayyora", "Yupiter — Quyosh tizimidagi eng katta sayyora. Yerdan 1 300 marta katta hajmli."),
    ("eng kichik sayyora", "Merkuriy — Quyosh tizimidagi eng kichik sayyora."),
    ("eng issiq sayyora", "Venera — eng issiq sayyora (o'rtacha 465°C). Kuchli issiqxona effekti tufayli."),
    ("qizil sayyora", "Mars — «Qizil sayyora» deb ataladi, temir oksidi tufayli qizg'ish rangda."),
    ("yer sayyorasi haqida", "Yer — Quyoshdan uchinchi sayyora, hayot mavjud bo'lgan yagona ma'lum sayyora."),
    ("oy masofasi", "Oy Yerdan o'rtacha 384 400 km uzoqlikda. Oyga yetib borish taxminan 3 kun."),
    ("quyosh", "Quyosh — Yerga eng yaqin yulduz. Uning markazida harorat 15 mln °C atrofida."),
    ("galaktika", "Somon yo'li — bizning galaktikamiz: 200 milliarddan ortiq yulduzni o'z ichiga oladi."),
    ("qora tuynuk", "Qora tuynuk — tortishish kuchi shu qadar kuchliki, hattoki yorug'lik ham qochib keta olmaydigan kosmik ob'ekt."),
    ("atom", "Atom — materiyaning eng kichik qismi: proton, neytron va elektronlardan tuzilgan."),
    ("suv formulasi", "Suvning kimyoviy formulasi — H2O: ikkita vodorod va bitta kislorod atomi."),
    ("havo tarkibi", "Havoning taxminan 78% azot, 21% kislorod, qolgani boshqa gazlar."),
    ("oltin belgisi", "Oltinning kimyoviy belgisi — Au (lotincha 'aurum'). Eng qimmatbaho metallardan."),
    ("temir belgisi", "Temirning kimyoviy belgisi — Fe. Dunyodagi eng ko'p ishlatiladigan metall."),
    ("kislorod", "Kislorod — hayot uchun eng muhim gaz. Kimyoviy belgisi O, havoda taxminan 21%."),
    ("fotosintez", "Fotosintez — o'simliklarning quyosh nuri, suv va karbonat angidriddan ozuqa tayyorlash jarayoni. Yon mahsulot — kislorod!"),
    ("dna", "DNK — tirik organizmlarning irsiy ma'lumotini saqlaydigan molekula. Barcha jonzotlarda bor."),
    ("inson suyaklari", "Katta odam tanasida 206 ta suyak bor. Chaqaloqda esa 300 ga yaqin bo'ladi."),
    ("yurak", "Yurak — tanadagi nasos: kuniga o'rtacha 100 000 marta uradi, 7 500 litr qon haydaydi."),
    ("qon hajmi", "Katta odam tanasida o'rtacha 5 litr qon bor."),
    ("miya", "Miya — inson tanasidagi eng murakkab organ: taxminan 86 milliard neyron bor."),
    ("eng katta a'zo", "Teri — inson tanasidagi eng katta a'zo. Yuzasi taxminan 2 m²."),
    ("p soni", "Pi (π) — aylana uzunligining diametriga nisbati: taxminan 3,14159. Cheksiz son."),
    ("fibo", "Fibonachchi ketma-ketligi: 0, 1, 1, 2, 3, 5, 8, 13... Har bir son oldingi ikkitasining yig'indisi. Tabiatda ko'p uchraydi!"),
    ("fizika nima", "Fizika — materiya, energiya, harakat va ularning o'zaro ta'sirini o'rganadigan fan."),
    ("kimyo nima", "Kimyo — moddalarning tarkibi, tuzilishi va o'zgarishlarini o'rganadigan fan."),
    ("biologiya nima", "Biologiya — tirik organizmlar va ularning hayotini o'rganadigan fan."),
    ("astronomiya nima", "Astronomiya — yulduzlar, sayyoralar va butun koinotni o'rganadigan fan."),
    ("matematika nima", "Matematika — sonlar, shakllar va miqdorlarni o'rganadigan fan. Fanlarning asosi!"),
    ("bir yilda necha kun", "Yer Quyosh atrofida bir marta aylanadi — 365 kun (kabisa yilida 366)."),
    ("yer aylanasi", "Yerning ekvator bo'ylab aylanasi — 40 075 km."),
    ("yer yoshi", "Yerning yoshi taxminan 4,6 milliard yil."),
    ("koinot yoshi", "Koinotning yoshi taxminan 13,8 milliard yil (Katta portlashdan boshlab)."),
    ("gravitatsiya", "Gravitatsiya — massali jismlarni bir-biriga tortuvchi kuch. Nyuton olma tushishi orqali kashf etgan deb rivoyat qilinadi."),
    ("einstein formulasi", "E = mc² — Eynshteynning mashhur formulasi: energiya = massa × yorug'lik tezligi kvadrati."),
    ("lazer", "Lazer — fokuslangan yorug'lik nuri. Tibbiyot, texnika va o'lchovlarda ishlatiladi."),
    ("magnit", "Magnit — temir kabi metallarni o'ziga tortadigan jism. Yerning o'zi ham ulkan magnitdir."),
    ("elektr nima", "Elektr — zaryadlangan zarrachalar oqimi. Bugungi dunyoning asosiy energiya manbai!"),
    ("vakuum", "Vakuum — hech qanday modda bo'lmagan, bo'sh fazo. Tovush vakuumda tarqalmaydi."),
    ("mikroskop", "Mikroskop — juda mayda narsalarni kattalashtirib ko'rsatadigan asbob. Hujayralarni o'rganishda muhim."),
    ("teleskop", "Teleskop — uzoq ob'ektlarni (yulduz, sayyora) yaqinlashtirib ko'rsatadigan asbob."),
    ("neytron", "Neytron — atom yadrosidagi zaryadsiz zarracha."),
    ("proton", "Proton — atom yadrosidagi musbat zaryadli zarracha."),
    ("elektron", "Elektron — atom atrofida aylanadigan manfiy zaryadli juda yengil zarracha."),
    ("vaksina", "Vaksina — organizmni kasalliklardan himoya qiladigan dori. Emlash orqali beriladi."),
    ("vitamin", "Vitamin — organizmga oz miqdorda kerak bo'ladigan, ammo hayotiy muhim modda."),
    ("oqsil", "Oqsil (protein) — mushak va to'qimalarning qurilish materiali. Go'sht, tuxum va loviyada ko'p."),

    # ============ TARIX ============
    ("ikkinchi jahon urushi", "Ikkinchi jahon urushi 1939—1945 yillarda bo'ldi: 60 milliondan ortiq odam halok bo'lgan tarixdagi eng katta urush."),
    ("birinchi jahon urushi", "Birinchi jahon urushi 1914—1918 yillarda bo'ldi. 17 millionga yaqin odam qurbon bo'lgan."),
    ("amir temur", "Amir Temur (1336—1405) — buyuk sarkarda va davlat arbobi, Temuriylar imperiyasining asoschisi. Samarqandni poytaxt qilgan."),
    ("amir temur qachon tug'ilgan", "Amir Temur 1336-yilda Kesh (Shahrisabz) yaqinida tug'ilgan, 1405-yilda vafot etgan."),
    ("ulug'bek", "Mirzo Ulug'bek (1394—1449) — buyuk astronom va olim, Amir Temurning nabirasi. Yulduzlar jadvali (Ziji Ko'raganiy) mashhur."),
    ("alisher navoiy", "Alisher Navoiy (1441—1501) — buyuk shoir va mutafakkir. O'zbek adabiy tilining asoschisi. «Xamsa» asari mashhur."),
    ("bobur", "Zahiriddin Muhammad Bobur (1483—1530) — shoir, sarkarda va davlat arbobi. Boburiylar imperiyasini Hindistonda qurgan. «Boburnoma» yozgan."),
    ("beruniy", "Abu Rayhon Beruniy (973—1048) — buyuk olim: astronom, matematik, geograf. Yer radiusini hisoblagan."),
    ("ibn sino", "Abu Ali ibn Sino (Avitsenna, 980—1037) — buyuk tabib va faylasuf. «Tib qonunlari» asari yuz yillar davomida Yevropada darslik bo'lgan."),
    ("al xorazmiy", "Muhammad al-Xorazmiy (783—850) — buyuk matematik. Algebraning asoschisi; «algoritm» so'zi uning nomidan kelib chiqqan!"),
    ("ahmad farg'oniy", "Ahmad al-Farg'oniy (798—865) — buyuk astronom va matematik. Nil daryosining suv sathini o'lchash qurilmasini yaratgan."),
    ("imom buxoriy", "Imom al-Buxoriy (810—870) — buyuk hadis olimi. «Sahih al-Buxoriy» to'plami islom olamidagi eng ishonchli kitoblardan."),
    ("jaloliddin manguberdi", "Jaloliddin Manguberdi (1199—1231) — Xorazmshohlar sulolasining jasur sarkardasi, mo'g'ul bosqinchilariga qarshi kurashgan."),
    ("shayboniyxon", "Muhammad Shayboniyxon — 16-asr boshlarida Buxoro xonligi asoschisi."),
    ("sug'd", "Sug'd — qadimiy Markaziy Osiyo xalqi va hududi. Sug'diy tili Buyuk Ipak yo'li bo'ylab savdo tili edi."),
    ("rim imperiyasi", "Rim imperiyasi — qadimgi dunyoning eng kuchli davlatlaridan: miloddan avvalgi 27 — milodiy 476 yillar."),
    ("misr piramidalari", "Misr piramidalari taxminan 4 500 yil avval qurilgan. Xeops piramidasi eng kattasi (146 m)."),
    ("kolumb", "Kristofer Kolumb 1492-yilda Amerikani (yevropaliklar uchun) kashf etdi."),
    ("buyuk xitoy devori", "Buyuk Xitoy devori — 21 000 km uzunlikdagi qadimiy mudofaa inshooti, dunyodagi eng uzun qurilish."),
    ("temuriylar", "Temuriylar — Amir Temur asos solgan sulola (1370—1507). Samarqand va Hirotni jahon madaniyati markaziga aylantirgan."),
    ("ko'hna urganch", "Ko'hna Urganch — Xorazmning qadimiy poytaxti, hozir Turkmanistonda. Qadimiy me'moriy yodgorliklari mashhur."),
    ("so'nggi xonliklar", "Xiva, Buxoro va Qo'qon xonliklari — 19-asrgacha O'zbekiston hududidagi asosiy davlatlar."),
    ("ruslar istilosi", "Rossiya imperiyasi O'rta Osiyoni 19-asrning 60—80-yillarida bosib oldi (1865-yilda Toshkent)."),
    ("sovet davri", "1924-yilda O'zbekiston SSR tashkil topdi. 1991-yil 31-avgustda mustaqillik e'lon qilindi."),
    ("qo'qon xonligi", "Qo'qon xonligi — 1709—1876 yillarda Farg'ona vodiysida mavjud bo'lgan davlat."),

    # ============ SHAXSLAR ============
    ("albert eynshteyn", "Albert Eynshteyn (1879—1955) — nazariy fizika dahosi, nisbiylik nazariyasi muallifi. Nobel mukofoti sovrindori."),
    ("nyuton", "Isaak Nyuton (1643—1727) — buyuk fizik va matematik. Tortishish qonuni va klassik mexanika asoschisi."),
    ("tesla", "Nikola Tesla (1856—1943) — ixtirochi va muhandis, o'zgaruvchan tok (AC) tizimini rivojlantirgan."),
    ("edison", "Tomas Edison (1847—1931) — mashhur ixtirochi: lampochka, fonograf va boshqa 1000+ ixtiro muallifi."),
    ("leonardo da vinchi", "Leonardo da Vinchi (1452—1519) — Uyg'onish davri dahosi: rassom (Mona Liza), olim va ixtirochi."),
    ("motsart", "Volfgang Amadey Motsart (1756—1791) — dunyodagi eng buyuk kompozitorlardan. 5 yoshida musiqa yozgan."),
    ("betxoven", "Lyudvig van Betxoven (1770—1827) — buyuk kompozitor. Kar bo'lganiga qaramay «9-simfoniya»ni yozgan."),
    ("qodiriy", "Abdulla Qodiriy (1894—1938) — o'zbek romanchiligining asoschisi. «O'tkan kunlar» va «Mehrobdan chayon» asarlari mashhur."),
    ("cho'lpon", "Cho'lpon (1897—1938) — buyuk o'zbek shoiri va yozuvchisi, o'zbek she'riyatini yangilagan."),
    ("hamid olimjon", "Hamid Olimjon (1909—1944) — iste'dodli o'zbek shoiri va dramaturgi."),
    ("g'afur g'ulom", "G'afur G'ulom (1903—1966) — buyuk o'zbek shoiri. «Shum bola» va «Netay» asarlari mashhur."),
    ("abdulla qahhor", "Abdulla Qahhor (1907—1968) — mashhur o'zbek yozuvchisi: «Qo'shchinor chiroqlari» romani mashhur."),
    ("erkin vohidov", "Erkin Vohidov (1936—2016) — xalq shoiri. «Yoshlik devoni» va «Ruhlar isyoni» dostonlari mashhur."),
    ("abdulla oripov", "Abdulla Oripov (1941—2016) — O'zbekiston Respublikasi madhiyasi matnining muallifi, buyuk shoir."),
    ("muhammad yusuf", "Muhammad Yusuf (1954—2001) — xalq shoiri, «O'zbekiston» she'ri bilan tanilgan."),
    ("zulfiya", "Zulfiya (1915—1996) — buyuk o'zbek shoirasi, o'zbek ayollar she'riyatining durdonasi."),
    ("islom karimov", "Islom Karimov (1938—2016) — O'zbekiston Respublikasining birinchi prezidenti (1991—2016)."),
    ("shavkat mirziyoyev", "Shavkat Mirziyoyev — O'zbekiston Respublikasi Prezidenti (2016-yildan)."),
    ("boburiylar", "Boburiylar imperiyasi — 1526—1857 yillarda Hindistonda hukmronlik qilgan, Bobur asos solgan sulola."),

    # ============ HAYVONLAR ============
    ("eng tez hayvon", "Gepard — quruqlikdagi eng tez hayvon: soatiga 110 km dan ortiq tezlikda yuguradi!"),
    ("gepard", "Gepard — mushuklar oilasiga mansub, quruqlikdagi eng tez hayvon (110 km/soatgacha)."),
    ("eng katta hayvon", "Ko'k kit — dunyodagi eng katta hayvon: uzunligi 30 m gacha, og'irligi 150 tonnagacha!"),
    ("ko'k kit", "Ko'k kit — yer yuzidagi eng katta jonzot. Og'irligi 30 ta filga teng bo'lishi mumkin."),
    ("eng katta quruqlik hayvoni", "Fil — quruqlikdagi eng katta hayvon. Afrika fili 6 tonnagacha yetadi."),
    ("eng baland hayvon", "Jirafa — eng baland hayvon: bo'yni tufayli 5,5 m gacha o'sadi."),
    ("jirafa", "Jirafa — savannadagi baland bo'yli hayvon (5,5 m gacha), bo'yni 7 ta umurtqadan iborat."),
    ("sher", "Sher — «hayvonlar qiroli» deb ataladi. Afrika savannasida katta guruh (prid) bo'lib yashaydi."),
    ("yo'lbars", "Yo'lbars — mushuklar oilasidagi eng katta yirtqich hayvon. Sibir yo'lbarsi eng kattasi."),
    ("delfin", "Delfin — eng aqlli dengiz hayvonlaridan. O'z tili va ism-idrokiga ega, odam bilan o'ynaydi!"),
    ("dinozavr", "Dinozavrlar — 65 million yil avval yo'q bo'lib ketgan qadimiy yirtqich va o'txo'r sudraluvchilar."),
    ("t-rex", "Tyrannosaurus Rex — tarixdagi eng mashhur yirtqich dinozavrlardan, uzunligi 12 m gacha bo'lgan."),
    ("asalarilar", "Asalari — tabiatdagi eng mehnatkash hasharot: bitta asalari bir kunda minglab gullarga boradi."),
    ("chumoli", "Chumoli — o'z og'irligidan 50 marta og'irroq narsani ko'tara oladigan kuchli hasharot."),
    ("o'rgimchak", "O'rgimchak — hasharot emas (o'rgimchaksimonlar sinfi). 8 oyog'i bor, to'r to'qib ov qiladi."),
    ("kameleon", "Kameleon — rangini atrof-muhitga moslay oladigan kaltakesak. Ko'zlari mustaqil aylanadi."),
    ("pingvin", "Pingvin — ucha olmaydigan, lekin zo'r suzuvchi qush. Antarktidada yashaydi."),
    ("boyqush", "Boyqush — tunda ko'radigan qush, boshi 270 gradusgacha aylanadi."),
    ("kenguru", "Kenguru — Avstraliyaning ramzi, sakrab yuradigan jonivor."),
    ("ayiq", "Ayiq — kuchli va aqlli yirtqich. Oq ayiq dunyodagi eng katta yirtqich quruqlik hayvoni."),
    ("tulki", "Tulki — hiyla va aqlliligi bilan mashhur yirtqich hayvon. Ertaklarda tez-tez uchraydi!"),
    ("quyon", "Quyon — tez ko'payadigan va qulog'i uzun beg'ubor jonivor. Kuniga 8 soat uxlaydi."),
    ("kirpi", "Kirpi — tanasi tikansimon ignalar bilan qoplangan kichik hayvon. Xavfda sharga aylanadi."),

    # ============ TEXNOLOGIYA ============
    ("internetni kim yaratgan", "Internetni bir kishi yaratmagan: 1969-yilda ARPANET paydo bo'ldi, Tim Berners-Lee 1989—1991-yillarda WWW (veb) ni ixtiro qildi."),
    ("www", "WWW (World Wide Web) — veb-sahifalar tizimini Tim Berners-Lee 1991-yilda yaratgan. Internetning ko'rinadigan qismi shu."),
    ("telefonni kim ixtiro qilgan", "Telefonni Aleksandr Graham Bell 1876-yilda patentladi. Keyinroq murakkab tizimlarga aylandi."),
    ("birinchi kompyuter", "Birinchi elektron kompyuter ENIAC 1946-yilda AQShda qurilgan: 30 tonna og'irlikda edi!"),
    ("kompyuter nima", "Kompyuter — ma'lumotni qayta ishlaydigan elektron qurilma. Bugungi kompyuterlar tranzistorlarda ishlaydi."),
    ("sun'iy yo'ldosh", "Birinchi sun'iy yo'ldosh — Sputnik-1, 1957-yilda SSSR uchirgan. Oyga esa 1969-yilda inson qadam qo'ydi."),
    ("oyga birinchi", "Nil Armstrong — Oyga qadam qo'ygan birinchi odam (1969-yil 20-iyul, Apollo-11 missiyasi)."),
    ("birinchi kosmonavt", "Yuriy Gagarin — kosmosga chiqqan birinchi odam (1961-yil 12-aprel, «Vostok-1»)."),
    ("samolyotni kim ixtiro qilgan", "Aka-uka Raytlar (Uilbur va Orvill) 1903-yilda birinchi muvaffaqiyatli samolyot parvozini amalga oshirishdi."),
    ("lampochkani kim ixtiro qilgan", "Tomas Edison 1879-yilda amaliy lampochkani yaratdi (avvalgi ixtirolarni takomillashtirgan)."),
    ("robot nima", "Robot — dasturlangan topshiriqni bajaradigan mexanik qurilma. «Robot» so'zi 1920-yilda Karel Chapek asaridan olingan."),
    ("dron", "Dron — masofadan boshqariladigan uchuvchi qurilma. Foto, yetkazib berish va kuzatuvda ishlatiladi."),
    ("vr nima", "VR (virtual reallik) — ko'zoynak orqali sun'iy dunyoga sho'ng'iydigan texnologiya. O'yin va o'qitishda ishlatiladi."),
    ("sun'iy intellekt nima", "Sun'iy intellekt (AI) — mashinaning o'rganish, fikrlash va qaror qabul qilish qobiliyati. Men shu texnologiya bilan yozilganman!"),
    ("mashina o'rganish", "Machine Learning — sun'iy intellekt usuli: dastur ma'lumotlardan o'zi o'rganadi, aniq qoidalar yozilmaydi."),
    ("katta til modeli", "Katta til modeli (LLM) — milliardlab parametrli neyron tarmoq bo'lib, matn yaratish va tushunishda ishlatiladi."),
    ("kripto valyuta", "Kriptovalyuta — raqamli pul turi (masalan, Bitcoin). Blockchain texnologiyasida ishlaydi."),
    ("blockchain", "Blockchain — ma'lumotni zanjir kabi bloklarga saqlaydigan xavfsiz texnologiya. Kriptovalyutalarning asosi."),
    ("google", "Google — dunyodagi eng katta qidiruv tizimi. 1998-yilda Sergey Brin va Larri Peyj tomonidan yaratilgan."),

    # ============ SALOMATLIK ============
    ("suv ichish", "Mutaxassislar kuniga 1,5–2 litr suv ichishni tavsiya qiladi. Suv organizmning 60% ini tashkil qiladi."),
    ("uyqu", "Katta odamga kuniga 7–8 soat uyqu kerak. Miyaning dam olishi va xotiraning mustahkamlanishi uyquda bo'ladi."),
    ("chekish zarar", "Chekish — o'pka saratoni, yurak kasalliklari va erta qarishga olib keladi. Eng xavfli odatlardan biri."),
    ("sport foydasi", "Kuniga 30 daqiqa jismoniy mashq — yurakni mustahkamlaydi, kayfiyatni ko'taradi va uyquni yaxshilaydi."),
    ("vitamin c", "S vitamini — immunitetni kuchaytiradi. Apelsin, limon, qalampir va kivada ko'p."),
    ("vitamin d", "D vitamini — suyaklar uchun muhim. Quyosh nuri ta'sirida organizmda hosil bo'ladi."),
    ("kaltsiy", "Kaltsiy — suyak va tishlar uchun kerakli mineral. Sut, pishloq va bodomda ko'p."),
    ("temir ovqatlanish", "Temir — qon uchun muhim mineral. Go'sht, jigar, loviya va ismaloqda ko'p."),
    ("miya uchun oziq", "Baliq, yong'oq va avakado — miya uchun foydali ovqatlar (omega-3 yog' kislotalari)."),
    ("stres", "Stresga qarshi: chuqur nafas olish, sayr qilish, suhbatlashish va sevimli ish bilan shug'ullanish yordam beradi."),
    ("shifokor", "Har qanday jiddiy alomatda shifokorga murojaat qiling — mening maslahatim umumiy ma'lumot, tashxis emas."),

    # ============ SPORT ============
    ("futbol", "Futbol — dunyodagi eng ommabop sport turi: 11+11 o'yinchi, 90 daqiqa, gollar soni g'olibni aniqlaydi."),
    ("futbol jch", "Futbol bo'yicha jahon chempionati har 4 yilda o'tkaziladi (birinchi marta 1930-yilda Urugvayda)."),
    ("olimpiada o'yinlari", "Olimpiya o'yinlari — qadimgi Gretsiyada boshlangan, zamonaviy o'yinlar 1896-yildan har 4 yilda o'tkaziladi."),
    ("basketbol", "Basketbolni 1891-yilda Jeyms Neysmit ixtiro qildi. Har jamoada 5 o'yinchidan."),
    ("voleybol", "Voleybolni 1895-yilda Uilyam Morgan ixtiro qilgan. Har jamoada 6 o'yinchi."),
    ("tennis", "Tennis — raketka va to'p bilan o'ynaladigan sport. Uimbldon — eng mashhur turnir."),
    ("boks", "Boks — qo'lqop kiyib, ringda mushtlashish sporti. Qoidalarga qat'iy rioya qilinadi."),
    ("kurash turlari", "Kurash turlari: erkin kurash, yunon-rum kurashi, sambo, dzyudo va o'zbekcha milliy kurash."),
    ("shaxmat", "Shaxmat — aql-zakovat o'yini: 32 dona, 64 katak. Hindistonda ixtiro qilingan."),
    ("suzish", "Suzish — butun tanani rivojlantiradigan sport: mushaklar, nafas va yurak uchun foydali."),
    ("muhammad ali", "Muhammad Ali — barcha davrlarning eng buyuk bokschilaridan, «Shaxzoda» (The Greatest) deb atalgan."),

    # ============ MADANIYAT / AN'ANA ============
    ("choyxona", "Choyxona — o'zbek an'analarida do'stlashish va dam olish maskani: choy, non va suhbat."),
    ("o'zbek mehmondo'stligi", "O'zbek xalqi mehmondo'stligi bilan mashhur: mehmon eng aziz inson — unga eng yaxshi taom tortiladi!"),
    ("ramazon", "Ramazon — islom taqvimining to'qqizinchi oyi, ro'za tutiladigan muqaddas oy."),
    ("hayit", "Hayit — Ramazon hayiti (ro'za tugagach) va Qurbon hayiti (qurbonlik) — islomning ikkita buyuk bayrami."),
    ("qurbon hayiti", "Qurbon hayiti — qurbonlik qilinadigan bayram, Haj amali bilan bog'liq."),
    ("sumalak", "Sumalak — Navro'zda tayyorlanadigan an'anaviy taom: unib chiqqan bug'doydan qozonda 12 soat pishiriladi."),
    ("tandir samsa", "Tandir non va samsa — tandirda pishiriladigan mashhur o'zbek non mahsulotlari."),
    ("atlas adras", "Atlas va adras — o'zbek matolari, ularning naqshlari betakror."),
    ("ashula raqs", "O'zbek raqs san'ati jonli va rang-barang; maqom san'ati UNESCO ro'yxatida."),
    ("o'zbek tili", "O'zbek tili — turkiy tillar guruhiga kiradi. Lotin yozuvida, 30 dan ortiq harf."),

    # ============ QIZIQARLI FAKTLAR ============
    ("eng qisqa urush", "Eng qisqa urush — Angliya va Zanzibar o'rtasida 1896-yilda: atigi 38 daqiqa davom etgan!"),
    ("asal buzilmaydi", "Asal — buzilmaydigan yagona tabiiy oziq-ovqat. Misr qabrlaridan 3 000 yillik asal topilgan!"),
    ("kamalak 7 rang", "Kamalak 7 rangdan iborat: qizil, to'q sariq, sariq, yashil, ko'k, havo rang va binafsha."),
    ("kofe", "Kofe dunyodagi eng mashhur ichimliklardan: har kuni 2 milliarddan ortiq chashka ichiladi."),
    ("choy tarixi", "Choy — Xitoyda miloddan avvalgi 2700-yillarda kashf etilgan, hozir dunyo bo'ylab ichiladi."),
    ("shokolad", "Shokolad kakao daraxti urug'idan tayyorlanadi. Dastlab ichimlik sifatida ishlatilgan!"),
    ("pizza", "Pizza Italiyadan chiqqan: 1889-yilda Margherita pizzasi qirolicha sharafiga yaratilgan."),
    ("eyfel minorasi", "Eyfel minorasi 1889-yilda qurilgan, balandligi 330 m. Parijning ramzi!"),
    ("yulduzlar soni", "Somon yo'li galaktikasida 200 milliarddan ortiq yulduz bor. Koinotda esa milliardlab galaktika!"),
    ("yupiter yili", "Yupiterda bir yil 12 Yer yili davom etadi, bir sutkasi esa atigi 10 soat."),
    ("neytron yulduz", "Neytron yulduz — shu qadar zich yulduzki, uning bir choy qoshiq moddasi Yerda millionlab tonna og'irlik qiladi."),
    ("eng uzoq umr", "Eng uzoq umr ko'rgan odam (tasdiqlangan) — Jan Kalman (Fransiya, 1875—1997): 122 yil."),
]


def main():
    added, skipped = [], []

    # ---- 1) MySQL ga yozamiz (muvaffaqiyatsizlik xatolik emas — JSON yetarli) ----
    db_ok = True
    conn = None
    try:
        conn = pymysql.connect(**DB_CONFIG)
    except Exception as e:
        db_ok = False
        print(f"⚠️  MySQL ishlamayapti ({type(e).__name__}) — faqat JSON zaxiraga yozamiz.")

    if conn is not None:
        try:
            with conn.cursor() as cur:
                for title, content in KNOWLEDGE:
                    cur.execute(
                        "SELECT id FROM ai_knowledge WHERE title = %s "
                        "ORDER BY id DESC LIMIT 1", (title,))
                    if cur.fetchone():
                        skipped.append(title)
                        continue
                    cur.execute(
                        "INSERT INTO ai_knowledge (title, content, status) "
                        "VALUES (%s, %s, 'approved')", (title, content))
                    added.append(title)
            conn.commit()
        except Exception as e:
            print(f"Yozishda xato: {e}")
        finally:
            conn.close()

    # ---- 2) ai_knowledge.json'ga ham qo'shamiz (oflayn zaxira) ----
    json_added = 0
    try:
        p = pathlib.Path(__file__).parent / "ai_knowledge.json"
        if p.exists():
            data = json.loads(p.read_text(encoding="utf-8"))
            titles = {d.get("title") for d in data}
            for title, content in KNOWLEDGE:
                if title not in titles:
                    data.append({"title": title, "content": content})
                    json_added += 1
            p.write_text(json.dumps(data, ensure_ascii=False, indent=1),
                         encoding="utf-8")
    except Exception as e:
        print(f"ai_knowledge.json yangilashda xato: {e}")

    note = "" if db_ok else "  [MySQL o'chiq — keyin ishga tushsa, qayta yurgizsangiz DB'ga ham qo'shiladi]"
    print(f"Yangi qo'shildi: {len(added)} ta (DB) | {json_added} ta (json){note}")
    for t in added[:60]:
        print(f"   + {t}")
    if len(added) > 60:
        print(f"   ... yana {len(added) - 60} ta")
    if skipped:
        print(f"O'tkazib yuborildi (allaqachon bor): {len(skipped)} ta")
    print("\nEndi: server restart + sinab ko'ring:")
    print("   «fransiya poytaxti», «dunyodagi eng katta davlat», «amir temur kim»")


if __name__ == "__main__":
    main()