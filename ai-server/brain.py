# -*- coding: utf-8 -*-
"""
UZDUB AI MIYASI — 0 dan qurilgan sun'iy intellekt
==================================================
Bu modul saytning AI yordamchisi "miyasi". U:
  - foydalanuvchi bilan suhbatlashadi (salom, xayr, rahmat, yordam...)
  - kino / anime / multfilm tavsiya qiladi (MySQL bazasidan)
  - yangi qo'shilgan kontentlarni ko'rsatadi
  - janr va nom bo'yicha izlaydi
  - "esda tut:" buyrug'i orqali yangi bilimlarni eslab qoladi
    (saytdagi `ai_knowledge` jadvaliga saqlaydi)
  - SUHBAT TARIXINI hisobga oladi: "yana bormi?", "boshqa tavsiya",
    "mening ismim X" kabi davom so'rovlarini tushunadi
  - reytingi noma'lum kontentlarga PyTorch modeli bilan TAXMINIY
    reyting qo'shadi (rating_nn.py)
  - siyosat/din kabi mavzularda odob bilan rad javob beradi
  - FOYDALANUVCHILARDAN O'RGANADI va o'zini o'zi rivojlantiradi:
      * "menga anime yoqadi" -> afzalligini eslab qoladi
      * tavsiyadan keyin "yoqmadi/zo'r edi" -> fikr-mulohazani
        hisobga oladi (keyingi tavsiyalarni moslashtiradi)
      * haqiqiy suhbat xabarlari NN uchun yig'ilib, model shu REAL
        ma'lumot bilan qayta o'qitiladi (continual learning)
      * "o'z-o'zini rivojlantiradimi?" — jonli o'rganish hisoboti

Ishlatilgan texnologiyalar:
  - Asosiy: so'z/naqsh bo'yicha niyat aniqlash (0 dan, qoidaga asoslangan)
  - Qo'shimcha: intent_nn.py — PyTorch niyat klassifikatori
  - Qo'shimcha: rating_nn.py — PyTorch reyting bashoratchisi
  - O'rganish xotirasi: ai_knowledge jadvali (afzallik, fikr, bilim)
"""

import json
import os
import random
import re
import time
import urllib.parse
import urllib.request
from datetime import datetime

try:
    import pymysql
except ImportError:
    pymysql = None

from intent_nn import (
    load_predictor, load_user_samples, add_user_sample, maybe_retrain,
)
from rating_nn import load_rating_predictor

SITE_URL = os.environ.get("SITE_URL", "http://localhost/uzdub")

DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "localhost"),
    "port": int(os.environ.get("DB_PORT", "3306")),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASS", ""),
    "database": os.environ.get("DB_NAME", "uzdub"),
    "charset": "utf8mb4",
}


# ---------------------------------------------------------------------------
# JANR LUG'ATI: foydalanuvchi so'zi -> genres jadvalidagi slug
# ---------------------------------------------------------------------------
GENRE_MAP = {
    "komediya": "komediya", "kulgili": "komediya", "hazil": "komediya",
    "qo'rqinchli": "qorqinchli", "dahshat": "qorqinchli", "horror": "qorqinchli",
    "fantastika": "fantastika", "ilmiy": "ilmiy-fantastika", "skay-fay": "sci-fi",
    "romantika": "romantika", "sevgi": "romantika", "romance": "romance",
    "drama": "drama", "triller": "triller", "sarguzasht": "sarguzasht",
    "jangari": "jangari", "action": "action", "hayotiy": "hayotiy",
    "tarixiy": "tarixiy", "sport": "sport", "detektiv": "detektiv",
    "kriminal": "kriminal", "mistika": "mistika", "fantaziya": "fantaziya",
    "isekai": "isekai", "reenkarnatsiya": "reenkarnatsiya", "maktab": "maktab",
    "vaqt sayohati": "vaqt-sayohati", "mech": "mech", "vestern": "vestern",
    "zombi": "zombi", "vampir": "vampir", "harbiy": "harbiy",
    "melodrama": "melodrama", "biografik": "biografik", "dokumental": "dokumental",
    "musiqiy": "musiqiy", "oila": "oila", "bolalar": "bolalar",
    "epik": "epik", "noire": "noire", "kiberpank": "kiberpank",
    "postapokalipsis": "postapokalipsis", "samuray": "samuray", "ninja": "ninja",
    "shonen": "shonen", "seinen": "seinen", "haram": "haram",
}


class UzdubBrain:
    """UZDUB sayti uchun AI yordamchi miyasi."""

    def __init__(self):
        self.model = None               # (zaxira)
        self.memory = {}                # "esda tut:" bilan o'rganilgan bilimlar
        self.user_name = None           # foydalanuvchi ismi (men bilan suhbatda)
        self.last_query = {}            # oxirgi tavsiya so'rovi ("yana" uchun)
        self.last_reply = ""            # oxirgi javob (kontekst davomiyligi)
        self.last_rec_ids = []          # oxirgi tavsiya qilingan kontent ID'lari
        self.last_rec_titles = []       # oxirgi tavsiya qilingan sarlavhalar
        self._last_web = None           # oxirgi internet javobi (batafsil uchun)
        self.predict = load_predictor()             # niyat NN (yordamchi)
        self.predict_rating = load_rating_predictor()  # reyting NN
        self._web_cache = {}            # internetdan topilgan javoblar xotirasi

        # ---- O'Z-O'ZINI RIVOJLANTIRISH (foydalanuvchilardan o'rganish) ----
        self.learned = {"prefs": {}, "feedback": {}}
        self._session_prefs = {}    # shu seansda aytilgan afzalliklar (ustuvor!)
        self.stats = {
            "start": datetime.now(),
            "messages": 0,          # qayta ishlangan xabarlar soni
            "recommendations": 0,   # berilgan tavsiyalar soni
            "learned_prefs": 0,     # o'rganilgan afzalliklar soni
            "feedback": 0,          # fikr-mulohazalar soni (yoqdi/yoqmadi)
            "nn_samples": 0,        # NN uchun yig'ilgan real namunalar
            "retrains": 0,          # NN qayta o'qitishlar soni
            "nn_pending": 0,
        }
        self._db_down_until = 0.0   # MySQL yiqilganda qayta urinish vaqti
        self.load_knowledge()       # oldin o'rganilganlarni DB'dan o'qiydi
        try:
            if maybe_retrain():     # ishga tushganda yangi real namunalar bo'lsa
                self.stats["retrains"] += 1
                self.predict = load_predictor()
        except Exception:
            pass

    # ----------------------------------------------------------------
    # BAZA
    # ----------------------------------------------------------------
    def _db(self):
        # MySQL bir marta yiqilgan bo'lsa — 30 soniya davomida qayta
        # urinmaymiz (MS yiqilganda har so'rov ~4s kutib o'tirilmasligi uchun).
        if time.time() < self._db_down_until:
            raise ConnectionError("MySQL vaqtincha mavjud emas (kesh)")
        try:
            conn = pymysql.connect(**DB_CONFIG, connect_timeout=2)
        except Exception:
            self._db_down_until = time.time() + 30
            raise
        self._db_down_until = 0.0   # ulanish bo'ldi — kesh tozalanadi
        return conn

    def query(self, sql, args=()):
        """MySQL'dan o'qiydi. MySQL o'chiq bo'lsa — XATOSIZ [] qaytadi
        (suhbat buzilmaydi, o'rniga bilim bazasi va internet ishlaydi)."""
        try:
            conn = self._db()
        except Exception:
            return []                       # MySQL yo'q -> bo'sh natija
        try:
            with conn.cursor(pymysql.cursors.DictCursor) as cur:
                cur.execute(sql, args)
                return cur.fetchall()
        finally:
            try:
                conn.close()
            except Exception:
                pass

    def execute(self, sql, args=()):
        """MySQL'ga yozadi. MySQL o'chiq bo'lsa — yozish o'tkazib yuboriladi
        (xatolik tashlamaydi, suhbat davom etadi)."""
        try:
            conn = self._db()
        except Exception:
            return                           # MySQL yo'q -> commit qilinmaydi
        try:
            with conn.cursor() as cur:
                cur.execute(sql, args)
            conn.commit()
        finally:
            try:
                conn.close()
            except Exception:
                pass

    # ----------------------------------------------------------------
    # O'Z-O'ZINI RIVOJLANTIRISH — FOYDALANUVCHILARDAN O'RGANISH
    # ----------------------------------------------------------------
    def _cat_in_text(self, text):
        """Matndagi kategoriyani aniqlaydi (kino/anime/multfilm)."""
        if any(k in text for k in ["multfilm", "multik", "kartun", "bolalar uchun"]):
            return "multfilm"
        if "anime" in text:
            return "anime"
        if any(k in text for k in ["kino", "film", "filmlar"]):
            return "kino"
        return None

    def load_knowledge(self):
        """Bilim/afzallik/fikr-mulohazalarni xotiraga oladi.

        Avval MySQL (ai_knowledge) — foydalanuvchilardan o'rganilgan
        ma'lumotlar shu yerda. MySQL yo'q bo'lsa — mahalliy
        ai_knowledge.json'dan to'liq bilim bazasini yuklaydi
        (xatosiz degradatsiya: bilim bazasi has har doim ishlaydi).
        """
        db_ok = True
        try:
            rows = self.query(
                "SELECT title, content FROM ai_knowledge WHERE status='approved'")
        except Exception:
            rows = []
        if not rows:
            db_ok = False
            rows = self._load_knowledge_from_json()
        for r in rows:
            key, val = r["title"], r["content"]
            if key.startswith("pref:"):
                try:
                    self.learned["prefs"][key[5:]] = int(float(val or 0))
                except Exception:
                    self.learned["prefs"][key[5:]] = 1
            elif key.startswith("fb:"):
                try:
                    self.learned["feedback"][key[3:]] = int(float(val or 0))
                except Exception:
                    self.learned["feedback"][key[3:]] = 1
            elif not key.startswith("fb") and not key.startswith("pref"):
                self.memory[key] = val
        return db_ok

    def _load_knowledge_from_json(self):
        """Offlayn zaxira: ai_knowledge.json (seed_mega + pref + fb)."""
        import json
        import pathlib
        try:
            path = pathlib.Path(__file__).parent / "ai_knowledge.json"
            if not path.exists():
                return []
            with open(path, "r", encoding="utf-8") as f:
                data = json.load(f)
            return [{"title": d.get("title", ""), "content": d.get("content", "")}
                    for d in data if d.get("title") and d.get("content")]
        except Exception:
            return []

    def bump_knowledge(self, title, value):
        """ai_knowledge'dagi qatorni yangilaydi (yo'q bo'lsa yaratadi).

        Bu — foydalanuvchi ma'lumotlarini eslab qolishning asosi:
        afzallik/fikr hisoblagichlari shu yerda saqlanadi.
        """
        try:
            rows = self.query(
                "SELECT id FROM ai_knowledge WHERE title = %s "
                "ORDER BY id DESC LIMIT 1", (title,))
            if rows:
                self.execute(
                    "UPDATE ai_knowledge SET content = %s, "
                    "use_count = use_count + 1, status = 'approved' "
                    "WHERE id = %s", (value, rows[0]["id"]))
            else:
                self.execute(
                    "INSERT INTO ai_knowledge (title, content, status) "
                    "VALUES (%s, %s, 'approved')", (title, value))
        except Exception:
            pass

    def bump_use(self, title):
        """Bilim javobda ishlatilsa use_count ni oshiradi (kuchaytirish)."""
        try:
            self.execute(
                "UPDATE ai_knowledge SET use_count = use_count + 1 "
                "WHERE title = %s", (title,))
        except Exception:
            pass

    # ----------------------------------------------------------------
    # INTERNETDAN IZLASH (topilmasa — tarmoqdan topib, eslab qolish)
    # ----------------------------------------------------------------
    def _http_get_json(self, url, timeout=2.0, tries=1):
        """URL manzildan JSON oladi. Xato/kechikish bo'lsa — None (internet yo'q).

        Har bir urinish `timeout` soniyadan oshmaydi. Vaqtinchalik xatolarda
        qayta urinish uchun `tries=2` berish mumkin (ma'lum xizmatlar uchun).
        """
        headers = {
            "User-Agent": "UZDUB-AI/1.0 (o'quv loyihasi chatboti)",
            "Accept": "application/json",
        }
        for _ in range(max(1, tries)):
            try:
                req = urllib.request.Request(url, headers=headers)
                with urllib.request.urlopen(req, timeout=timeout) as resp:
                    return json.loads(resp.read().decode("utf-8", "replace"))
            except Exception:
                continue
        return None

    def _split_sentences(self, text):
        """Matnni gaplarga ajratadi (qisqa qoldiqlarni oldingisiga ulaydi)."""
        parts = re.split(r"(?<=[.!?])\s+", (text or ""))
        out = []
        for p in parts:
            p = p.strip()
            if not p:
                continue
            if len(p) < 25 and out:
                out[-1] += " " + p
            else:
                out.append(p)
        return out

    def _query_focus(self, query):
        """Savoldan javob gapni tanlashga yordam beradigan diqqat belgilari."""
        q = (query or "").lower()
        focus = {"year": False, "num": False, "place": False,
                 "person": False, "reason": False, "words": []}
        if re.search(r"qachon|qaysi yil|nechanchi yil|sana", q):
            focus["year"] = True
        if re.search(r"nechta|qancha|necha", q):
            focus["num"] = True
        if re.search(r"qayerda|poytaxt|joylashgan|shahri|davlat", q):
            focus["place"] = True
        if re.search(r"\bkim\b|odam|kimdir", q):
            focus["person"] = True
        if re.search(r"nima uchun|nega|sabab", q):
            focus["reason"] = True
        skip = {"nima", "qanday", "qayerda", "qachon", "necha", "qancha",
                "haqida", "uchun", "ning", "eng", "bilan", "ham", "yana",
                "davom", "ayt", "ber", "gapir", "ma'lumot", "bilmoqchiman",
                "bormi", "qaysi", "nega", "nimalar", "qanaqa", "ozi", "bir"}
        words = [w for w in re.findall(r"[a-zа-яё]{4,}", q) if w not in skip]
        focus["words"] = list(dict.fromkeys(words))[:8]
        return focus

    def _sentence_score(self, s, focus):
        """Gapning savolga moslik bali — savol turiga qarab og'irliklar."""
        q = s.lower()
        score = 0.0
        if focus["year"] and re.search(r"\b(19|20)\d{2}\b", s):
            score += 3.0
        if focus["num"] and re.search(r"\d+", s):
            score += 3.0
        if focus["place"] and re.search(
                r"\b(poytaxt|shahar|davlat|joylashgan|qishloq|markaz)\b", q):
            score += 2.5
        if focus["reason"] and re.search(
                r"\b(chunki|sabab|shuning uchun|natijasida)\b", q):
            score += 3.0
        if focus["person"] and re.search(r"\b[А-ЯЁA-Z][а-яёa-z]{2,}", s):
            score += 1.5
        for w in focus["words"]:
            if w in q:
                score += 1.2
        if len(s) > 320:
            score -= 1.5
        if s.startswith("("):
            score -= 2.0
        return score

    def _extract_pages(self, payload):
        """Wikipedia API javobidan barcha (title, ekstrakt) juftliklarini oladi."""
        out = []
        try:
            for p in payload["query"]["pages"].values():
                ext = (p.get("extract") or "").strip()
                if ext and len(ext) > 30:
                    out.append((p.get("title") or "", ext))
        except Exception:
            pass
        return out

    def _fetch_wiki(self, wiki, key, search=False):
        """Wikipedia'dan sahifa yoki qidiruv natijasini (title, ekstraktlar) oladi.

        Ba'zi maqolalarda `exintro` bo'sh qaytadi (tuzilma xususiyati, masalan
        "Fransiya"). Bunday holda `exchars=900` bilan to'liq matn boshidan
        olinadi, bo'lim sarlavhalari ("== Manbalar ==") tozalanadi.
        """
        base = "https://{0}.wikipedia.org/w/api.php?action=query".format(wiki)
        q = urllib.parse.quote(key)
        if search:
            url = (f"{base}&generator=search&gsrsearch={q}&gsrlimit=3"
                   f"&prop=extracts&exintro&explaintext&redirects=1&format=json")
            return self._extract_pages(self._http_get_json(url))
        pages = self._extract_pages(self._http_get_json(
            f"{base}&titles={q}&prop=extracts&exintro&explaintext"
            f"&redirects=1&format=json"))
        if not pages:
            pages = self._extract_pages(self._http_get_json(
                f"{base}&titles={q}&prop=extracts&exchars=900&explaintext"
                f"&redirects=1&format=json"))
            pages = [(t, re.sub(r"^==+\s*.*?\s*==+$", "", e, flags=re.M).strip())
                     for t, e in pages]
            pages = [(t, e) for t, e in pages if len(e) > 30]
        return pages

    def _clean_web_key(self, key0):
        """Savol so'zlari va to'ldirgichlarni olib tashlab, qidiruv kalitlarini beradi."""
        fillers = re.compile(
            r"\b(nima|qaysi|qachon|qayerda|qancha|nechta|qanday|nega|kim|bormi|"
            r"haqida|ayt|ayting|gapir|ber|ma'lumot|deya|degani|savol|top|qidir|"
            r"izla|o'rgan|bilmoqchiman|istayman|kerak|aytib|berish|bilib)\b",
            re.I)
        clean = fillers.sub(" ", key0)
        clean = re.sub(r"\s+", " ", clean).strip(" .!?,-:;")
        if not clean or len(clean) < 3 or clean == key0:
            return [key0]
        parts = clean.split()
        cands = [clean]
        # qisqaroq prefikslar: "o'zbekiston poytaxti" -> "o'zbekiston"
        for i in range(len(parts) - 1, 0, -1):
            c = " ".join(parts[:i])
            if len(c) >= 3 and c not in cands:
                cands.append(c)
        # "o'zbekistonning poytaxti" -> "o'zbekiston" (egalik qo'shimchasi)
        for w in parts:
            base = w[:-4] if w.endswith("ning") and len(w) > 6 else w
            if len(base) >= 5 and base not in cands:
                cands.append(base)
                break
        if key0 not in cands:
            cands.append(key0)
        return cands

    def _answer_from_extract(self, query, title, extract, detail=False):
        """Wikipedia izohidan SAVOLGA MOS qisqa javobni tanlaydi.

        Bu «Groq'ga o'xshab aniq javob» berishning kaliti: oddiy
        «birinchi 350 belgi» emas — savolning diqqat belgilariga
        (qachon/yil, nechta/son, qayerda/poytaxt, nega/sabab...)
        mos keladigan GAP tanlanadi.
        """
        if not extract:
            return None
        limit = 780 if detail else 320
        sents = self._split_sentences(extract)
        if not sents:
            return None
        if len(sents) == 1:
            body = sents[0][:limit]
        else:
            focus = self._query_focus(query)

            def _proper_count(s):
                """To'g'ri nomlar soni (gap boshidagi so'zdan tashqari)."""
                words = s.split()
                n = 0
                for w in words[1:]:
                    if re.match(r"^[А-ЯЁA-Z][а-яёa-z-]*\.?$", w):
                        n += 1
                return 1 if n > 0 else 0

            scored = sorted(
                ((self._sentence_score(s, focus), i, s, _proper_count(s))
                 for i, s in enumerate(sents)),
                key=lambda x: (-x[0], -x[3], x[1]))
            body = scored[0][2]
            if len(body) < 80 and len(scored) > 1:
                body += " " + scored[1][2]
            body = body[:limit].rstrip()
        prefix = ""
        if title and title.lower() not in (query or "").lower():
            prefix = f"{title}. "
        out = (prefix + body).strip()
        if not detail and len(out) > limit:
            out = out[:limit].rstrip()
        return out

    def web_lookup(self, query, max_seconds=None, detail=False):
        """Savol bazadan topilmasa — internetdan izlaydi (o'z KODImiz bilan).

        Tashqi AI xizmati emas. Tartib:
          1) Wikipedia maqola SARLAVHASINI bevosita qidirish — kalitning
             yozuviga qarab til tanlanadi (kirillcha -> ru birinchi);
          2) Wikipedia to'liq-matn qidiruvi (gsr);
          3) DuckDuckGo.
        Tezlik uchun byudjet cheklangan (ovozli chat osilib qolmaydi):
          oddiy so'rov 4s, batafsil 6s. Topilgani keshda saqlanadi.
        Internet yo'q bo'lsa — tezda None (xatolik bermaydi).
        """
        key = (query or "").strip()
        if len(key) < 2:
            return None
        cache_tag = ("detail:" if detail else "") + key.lower()
        if cache_tag in self._web_cache:
            return self._web_cache[cache_tag]
        budget = (max_seconds if max_seconds is not None
                  else (6.0 if detail else 4.0))
        result = None
        start = time.time()
        candidates = self._clean_web_key(key)
        # Til tartibi: kirill kalit -> ruscha birinchi, aks holda o'zbekcha
        wikis = ("ru", "uz", "en") if re.search(r"[а-яё]", key) \
            else ("uz", "en", "ru")
        # 1) Sarlavha bo'yicha — toza/topiladigan kalitlar
        for cand in candidates:
            if time.time() - start > budget:
                break
            for wiki in wikis:
                if time.time() - start > budget:
                    break
                pages = self._fetch_wiki(wiki, cand)
                for title, extract in pages:
                    if extract and len(extract) > 30:
                        a = self._answer_from_extract(key, title, extract, detail)
                        if a:
                            result = a
                            self._last_web = {"key": key, "full": extract,
                                              "title": title}
                            break
                if result:
                    break
            if result:
                break
        # 2) To'liq-matn qidiruvi (gsr) — asl savol matni bilan
        if not result and time.time() - start < budget:
            for wiki in wikis:
                if time.time() - start > budget:
                    break
                pages = self._fetch_wiki(wiki, key, search=True)
                for title, extract in pages:
                    if extract and len(extract) > 30:
                        a = self._answer_from_extract(key, title, extract, detail)
                        if a:
                            result = a
                            self._last_web = {"key": key, "full": extract,
                                              "title": title}
                            break
                if result:
                    break
        # 3) DuckDuckGo (qisqa ma'lumot)
        if not result and time.time() - start < budget:
            ddg_url = ("https://api.duckduckgo.com/?q="
                       + urllib.parse.quote(key) + "&format=json&no_html=1")
            payload = self._http_get_json(ddg_url, timeout=2.0, tries=1)
            try:
                text = (payload or {}).get("AbstractText") or \
                    (payload or {}).get("Answer")
                if text and len(str(text)) > 30:
                    result = str(text)[:350].strip() + "..."
            except Exception:
                result = None
        self._web_cache[cache_tag] = result
        return result

    def learn_web(self, key, text):
        """Internetdan topilgan javobni ESDA QOLDIradi (xotira + baza + json)."""
        if not text:
            return
        self.memory[key] = text          # shu seans uchun darhol xotirada
        try:
            self.bump_knowledge(key, text)
        except Exception:
            pass
        try:  # oflayn rejim uchun ai_knowledge.json'ga ham qo'shamiz
            import pathlib
            p = pathlib.Path(__file__).parent / "ai_knowledge.json"
            if p.exists():
                data = json.loads(p.read_text(encoding="utf-8"))
                if not any(d.get("title") == key for d in data):
                    data.append({"title": key, "content": text})
                    p.write_text(json.dumps(data, ensure_ascii=False, indent=1),
                                 encoding="utf-8")
        except Exception:
            pass

    def personal_cat(self, history=None):
        """Foydalanuvchining o'rganilgan afzalligini aniqlaydi.

        Ball: DB'da yig'ilgan afzalliklar + shu suhbat tarixidagi
        kategoriya so'rovlari. Rad etilgan kategoriya salbiy ball oladi.
        Shu seansda aytilgan afzallik (`_session_prefs`) ustuvor vaznga ega.
        """
        cats = {c: 0 for c in ("kino", "anime", "multfilm")}
        for key, val in self.learned["prefs"].items():
            base = key.split(":")[0]
            if base not in cats:
                continue            # pref:u001:kino kabi foydalanuvchi qatorlari
            if key.endswith(":no"):
                cats[base] -= val
            else:
                cats[base] += val
        for key, val in self._session_prefs.items():
            base = key.split(":")[0]
            if base not in cats:
                continue
            if key.endswith(":no"):
                cats[base] -= val * 8
            else:
                cats[base] += val * 8
        if history:
            for m in history:
                if m.get("role") != "user":
                    continue
                t = self.normalize(m.get("content") or "")
                c = self._cat_in_text(t)
                if c:
                    cats[c] += 2
                elif any(k in t for k in ["yoqmaydi", "yoqmadi", "kerak emas"]):
                    c2 = self._cat_in_text(t)
                    if c2:
                        cats[c2] -= 3
        best = max(cats, key=lambda c: cats[c])
        return best if cats[best] > 0 else None

    # ----------------------------------------------------------------
    # MATN NORMALIZATSIYASI
    # ----------------------------------------------------------------
    def normalize(self, text):
        text = text.lower()
        text = re.sub(r"[^\w\s+\-*/=]", " ", text)
        text = re.sub(r"\s+", " ", text).strip()
        return text

    # ----------------------------------------------------------------
    # PHP SAYT YUBORGAN KONTEKST
    # ----------------------------------------------------------------
    def split_context(self, user_content):
        marker = re.search(r"\n\[[^\]]+\]", user_content)
        if marker:
            return user_content[: marker.start()].strip()
        return user_content.strip()

    def parse_context_items(self, user_content):
        items = []
        m = re.search(r"\[Bazadan:\](.*?)Link:", user_content, re.S)
        if not m:
            return items
        for line in m.group(1).splitlines():
            line = line.strip()
            m2 = re.match(
                r'^- "(.*?)" \(ID:(\d+), ([^,]+)'
                r"(?:, (\d{4}))?(?:, ★([\d.]+))?(.*)\)$", line)
            if not m2:
                continue
            rating = None
            if m2.group(5):
                try:
                    rating = float(m2.group(5))
                except ValueError:
                    rating = None
            items.append({
                "id": int(m2.group(2)),
                "title": m2.group(1),
                "category": m2.group(3) or "",
                "year": m2.group(4),
                "rating": rating,
                "extra": (m2.group(6) or "").strip(),
            })
        return items

    def parse_user_history(self, user_content):
        hist = []
        m = re.search(r"Foydalanuvchining yaqinda ko'rgan kontentlari:\s*(.*?)(?:\n\s*\n|\Z)", user_content, re.S)
        if m:
            for line in m.group(1).splitlines():
                line = line.strip().lstrip("- ").strip()
                if line:
                    hist.append(line)
        return hist

    def parse_history(self, history):
        """PHP yuborgan oldingi suhbatdan foydali ma'lumotni oladi.

        Qaytadi: (avvalgi_user_xabari, avvalgi_AI_javobi)
        """
        prev_user, prev_ai = "", ""
        if not history:
            return prev_user, prev_ai
        msgs = [m for m in history if m.get("role") in ("user", "assistant")]
        for m in msgs:
            c = (m.get("content") or "").strip()
            if not c:
                continue
            if m.get("role") == "assistant":
                prev_ai = c
            else:
                prev_user = c
        return prev_user, prev_ai

    # ----------------------------------------------------------------
    # TILNI ANIQLASH
    # ----------------------------------------------------------------
    def detect_lang(self, system_prompt):
        if "Ты AI-помощник" in system_prompt or "Ты AI" in system_prompt:
            return "ru"
        if "You are UZDUB" in system_prompt:
            return "en"
        return "uz"

    # ----------------------------------------------------------------
    # NIYAT ANIQLASH (0 dan)
    # ----------------------------------------------------------------
    def detect_intent(self, text):
        t = self.normalize(text)
        words = set(t.split())

        # 1) Siyosat/din — rad etish
        # Qisqa so'zlar ("din", "sud") faqat butun so'z bo'lib kelsa rad etiladi:
        # aks holda "dinozavr" ham rad bo'lib qolardi!
        for kw in ["siyosat", "saylov", "prezident", "din", "xudo",
                   "islam", "xristian", "qonun", "sud", "huquq"]:
            if kw in t and (len(kw) > 3 or re.search(rf"\b{re.escape(kw)}\b", t)):
                return {"kind": "refuse"}

        # 2) Ism bilan tanishtirish: "mening ismim X", "meni X deb chaqir"
        m = re.search(r"(?:mening\s+)?ismim\s*[:=]?\s*([A-Za-z\u0400-\u04FFЁё]{2,20})", t)
        if not m:
            m = re.search(r"meni\s+([A-Za-z\u0400-\u04FFЁё]{2,20})\s+deb\s+(?:chaqir|atash)", t)
        if m:
            return {"kind": "name", "name": m.group(1).capitalize()}

        # 2.5) Afzallik bayoni: "menga anime yoqadi" / "multfilm yoqmaydi"
        pref_cat = self._cat_in_text(t)
        if pref_cat and any(v in t for v in ["yoqadi", "yoqmaydi", "yoqdi",
                                              "yaxshi ko'raman", "sevaman",
                                              "yoqtirmayman", "yoqtiryapman"])\
                and not any(d in t for d in ["bu ", "shu ", "buni", "shuni"]):
            return {"kind": "pref"}

        # 3) Salomlashish (so'z darajasida)
        if any(p in t for p in ["assalomu alaykum", "hayrli kun", "xayrli tong",
                                "xayrli kech", "qalaysiz", "alik"]):
            return {"kind": "greeting"}
        if words & {"salom", "assalom", "alaykum", "hey", "hi", "qalaysan",
                    "yaxshimisiz", "tanish", "hayrli"}:
            return {"kind": "greeting"}

        # 4) Xayrlashish
        if any(k in t for k in ["xayr", "sog' bo'l", "ko'rishguncha", "alvido",
                                "tugat", "ketishim kerak"]):
            return {"kind": "bye"}

        # 5) Minnatdorchilik
        if any(k in t for k in ["rahmat", "tashakkur", "arziydi"]):
            return {"kind": "thanks"}

        # 5.5) ERKIN SUHBAT — kundalik o'zbekcha gaplar ("yaxshimisan",
        #      "rasmsan", "qayerdansan", "nima qilyapsan"...) tabiiy qabul
        #      qilinadi. HUKM RO'YXATI ask/recommend'dan OLDIN ishlaydi —
        #      aks holda "qayerdansan" tavsiya sifatida noto'g'ri yo'nalardi.
        if self.smalltalk_rule(text):
            return {"kind": "casual"}

        # 6) Yordam / qobiliyatlar
        if any(k in t for k in ["nima qila olasan", "qanday ishlaysan", "yordam",
                                "imkoniyat", "nima bilasan", "yordam bera",
                                "ko'rsat nima qila"]):
            return {"kind": "help"}

        # 7) Davomiy so'rov: "yana bormi?", "boshqa tavsiya", "shunga o'xshash"
        if any(k in t for k in ["yana bormi", "yana boshqa", "boshqa bormi",
                                "boshqa tavsiya", "shunga o'xshash",
                                "shunga ohshash", "unga o'xshash", "yana ko'rsat",
                                "ko'proq ko'rsat", "xuddi shunday", "yana ber",
                                "yana tomosha"]):
            return {"kind": "more"}

        # 8) Yangi qo'shilganlar
        if any(k in t for k in ["yangi qo'shilgan", "yangi kontent", "yangi kelgan",
                                "so'nggi qo'shilgan", "oxirgi qo'shilgan",
                                "yangi kino", "yangi anime", "yangi multfilm",
                                "yangi filmlar", "yangi nimalar", "nima yangilik"]):
            return {"kind": "new"}

        # 8.5) Statistika / o'z-o'zini rivojlantirish hisoboti
        if any(k in t for k in ["nima o'rganding", "qancha o'rganding",
                                "nimalarni o'rgandin", "rivojlantiradigan",
                                "rivojlan", "o'sding", "statistika", "hisobot",
                                "qancha bilim", "qanday bilimlar",
                                "o'rganish hisoboti"]):
            return {"kind": "stats"}

        # 9) Xotira: "esda tut: X = Y"
        m = re.search(r"(?:esda tut|eslab ol|bilib ol|yodda tut)\s*[:.]?\s*(.+?)\s*[=:]\s*(.+)", t)
        if m:
            return {"kind": "learn", "key": m.group(1).strip(), "value": m.group(2).strip()}

        # 9.5) Shaxsiyat / AI o'zi haqida savollar
        if any(k in t for k in ["o'zing haqida", "o zing haqida", "o'zingiz haqida",
                                "o zingiz haqida", "o'zing to'g'risida", "o zing to'g'risida",
                                "sevikli filming", "sevimli filming", "sevikli kinoing",
                                "sevimli kinoing", "necha yoshdasan", "necha yoshdasiz",
                                "qayerda yashaysan", "qayerda yashaysiz",
                                "seni kim yaratdi", "seni kim yasadi", "seni kim qurdi",
                                "sen robotmi", "robot emasmisan", "uylanasanmi",
                                "qaysi filmni yaxshi ko'rasan", "qaysi filmni yaxshi ko rasan",
                                "nimani yaxshi ko'rasan", "nimani yaxshi ko rasan",
                                "sen odammisan", "joning bormi"]):
            return {"kind": "personality"}

        # 9.6) Janrlar ro'yxati
        if any(k in t for k in ["qanday janrlar", "qanaqa janrlar", "qaysi janrlar",
                                "janrlar ro'yxati", "janrlar bor", "janrlari bor"]):
            return {"kind": "genres"}

        # 9.7) Kontent soni: "nechta kino bor?"
        if any(k in t for k in ["nechta kino", "nechta anime", "nechta multfilm",
                                "qancha kino", "qancha anime", "qancha multfilm",
                                "nechta kontent", "nechta film", "jami nechta",
                                "kino nechta", "anime nechta", "multfilm nechta",
                                "qancha kontent"]):
            return {"kind": "count"}

        # 9.8) Tasodifiy tanlov
        if any(k in t for k in ["tasodifiy", "random", "tanlab ber", "tanlab qo'y",
                                "biror kino tanla", "biror multfilm", "biror anime",
                                "biron kino", "birontasini ko'rsat", "birortasini ko'rsat"]):
            return {"kind": "random"}

        # 9.9) TOP ro'yxatlar
        if any(k in t for k in ["eng mashhur", "eng yaxshi", "eng zo'r", "eng zo r",
                                "eng yuqori reyting", "eng ko'p ko'rilgan",
                                "eng ko p ko rilgan", "eng ko'p tomosha",
                                "eng ko p tomosha", "top 5", "top 10", "top 3",
                                "mashhur kino", "mashhur film", "mashhur anime",
                                "mashhur multfilm", "eng sara", "top 50"]):
            return {"kind": "top"}

        # 10) "X nima?" tipidagi savol
        m = re.search(r"(.+?)\s+(?:nima|kim|qanday|qayerda|qachon|necha|qancha|qaysi)\b", t)
        if m and len(m.group(1).strip()) > 2:
            return {"kind": "ask", "key": m.group(1).strip()}

        # 10.1) "X haqida ayt/gapir/ma'lumot" — ham savol sanaladi
        m = re.search(r"(.+?)\s+haqida\s+(?:ayt|gapir|ma'lumot|o'rgat|bilmoqchiman)\b", t)
        if m and len(m.group(1).strip()) > 2:
            return {"kind": "ask", "key": m.group(1).strip()}

        # 10.2) "X poytaxti/aholisi/tarixi..." — munosabat otli savol.
        # Kalit XOM matndan olinadi — apostroflar saqlanadi, shunda KB
        # sarlavhalari bilan aynan mos keladi: "fransiya poytaxti",
        # "o'zbekiston aholisi", "amir temur qachon tug'ilgan" (10'ga tushadi).
        m = re.search(r"(.+?)\s+(?:poytaxti?|aholisi|maydoni|tarixi|nomi|"
                      r"kimligi|uzunligi|balandligi|viloyatlari|metropoliteni|"
                      r"eng uzun daryosi|eng katta shahri|eng baland nuqtasi)\b", t)
        if m and len(m.group(1).strip()) > 2:
            full = re.sub(r"[\s.,!?;:]+$", "", re.sub(r"\s+", " ",
                         (text or "").lower().strip()))
            return {"kind": "ask", "key": full or m.group(1).strip()}

        # 11) Tavsiya so'rovi
        cat = None
        if any(k in t for k in ["multfilm", "multik", "kartun", "bolalar uchun"]):
            cat = "multfilm"
        elif "anime" in t:
            cat = "anime"
        elif any(k in t for k in ["kino", "film", "filmlar"]):
            cat = "kino"

        is_recommend = any(k in t for k in [
            "tavsiya", "kursat", "ko'rsat", "bormi", "top", "izla", "qidir",
            "xohlayman", "qayerdan", "chiroyli", "zo'r", "yon", "ber",
            "hohlayman", "tomosha", "ko'rish", "nima bor",
        ]) or cat is not None

        if is_recommend:
            genre = None
            t3 = re.sub(r"\s+", "", t)   # apostrof->bo'shliq muammosi ("qo'rqinchli"->"qo rqinchli")
            for word, slug in GENRE_MAP.items():
                w2 = word.replace("'", "")
                if word in t or (w2 and w2 in t3):
                    genre = slug
                    break
            return {"kind": "recommend", "cat": cat, "genre": genre}

        # 11.1) To'liq ibora bilim bazasida ANIQ bor — so'roq so'zsiz ham savol.
        #   Misol: "dunyodagi eng katta davlat" (NN uni "rahmat" deb yanglishadi,
        #   shuning uchun bu tekshiruv NN'ga yetguncha ishlaydi).
        #   Apostrofli sarlavhalar ham sinanadi: "eng chuqur ko'l" normalizatsiyada
        #   ("ko l") buziladi, shuning uchun XOM matn ko'rinishi ham tekshiriladi.
        raw3 = re.sub(r"\s+", " ", (text or "").lower().strip())
        found3 = t in self.memory
        if not found3:
            try:
                rows = self.query(
                    "SELECT title FROM ai_knowledge WHERE title = %s "
                    "AND status='approved' LIMIT 1", (t,))
                found3 = bool(rows)
            except Exception:
                pass
        if not found3 and raw3 != t:
            found3 = raw3 in self.memory
            if not found3:
                try:
                    rows = self.query(
                        "SELECT title FROM ai_knowledge WHERE title = %s "
                        "AND status='approved' LIMIT 1", (raw3,))
                    found3 = bool(rows)
                except Exception:
                    pass
        if found3:
            return {"kind": "ask", "key": raw3 if (raw3 in self.memory) else t}

        # 12) Sayt haqida
        if any(k in t for k in ["premium", "sayt", "qanday ko'riladi", "narx",
                                "obuna", "android", "telefon", "ilova"]):
            return {"kind": "site"}

        # 13) Kayfiyat / oddiy suhbat (oqil qoidalar, NN esa buni mustahkamlaydi)
        if any(k in t for k in ["kayfiyat", "zerikdim", "charchadim", "ob-havo",
                                "ob havo", "nima gaplar", "ishlar", "yaxshimisiz",
                                "dam oldim", "suhbatlash", "qiziq", "stress",
                                "g'amgin", "xursand", "ovqat", "uyga qaytdim",
                                "dars", "imtihon", "musiqa", "yomg'ir"]):
            return {"kind": "mood"}

        # 14) Noma'lum — neyron tarmoq hal qiladi
        return {"kind": "unknown", "cat": cat, "genre": None}

    # ----------------------------------------------------------------
    # BAZADAN TAVSIYA
    # ----------------------------------------------------------------
    def recommend_from_db(self, cat=None, genre=None, want_new=False,
                          limit=3, offset=0):
        cols = ("c.id, c.title, cat.name AS cat_name, c.release_year, "
                "c.rating, c.views, c.is_premium, c.status, "
                "gr.genre_names AS genres")
        base = (f"FROM content c JOIN categories cat ON c.category_id = cat.id "
                f"LEFT JOIN (SELECT cg.content_id, GROUP_CONCAT(g.name SEPARATOR ', ') AS genre_names "
                f"FROM content_genres cg JOIN genres g ON g.id = cg.genre_id "
                f"GROUP BY cg.content_id) gr ON gr.content_id = c.id "
                # Foydalanuvchi faolligidan O'RGANISH: ko'rilganlik + berilgan reytinglar
                f"LEFT JOIN (SELECT content_id, COUNT(*) AS watches FROM watch_history "
                f"GROUP BY content_id) wh ON wh.content_id = c.id "
                f"LEFT JOIN (SELECT content_id, COUNT(*) AS user_n, AVG(rating) AS user_avg "
                f"FROM content_ratings GROUP BY content_id) cr ON cr.content_id = c.id "
                # AI o'rgangan fikr-mulohazalar (yoqdi/yoqmadi sanog'i)
                f"LEFT JOIN (SELECT SUBSTRING_INDEX(title, ':', -1) AS cid, "
                f"SUM(CAST(content AS UNSIGNED)) AS likes FROM ai_knowledge "
                f"WHERE title LIKE 'fb:like:%%' GROUP BY cid) fbl ON fbl.cid = c.id "
                f"LEFT JOIN (SELECT SUBSTRING_INDEX(title, ':', -1) AS cid, "
                f"SUM(CAST(content AS UNSIGNED)) AS dislikes FROM ai_knowledge "
                f"WHERE title LIKE 'fb:dislike:%%' GROUP BY cid) fbd ON fbd.cid = c.id"
                # O'rganilgan AFZALLIKLAR: yoqgan kategoriya tavsiyada ustun
                f" LEFT JOIN (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(title, ':', 2), "
                f"':', -1) AS slug, SUM(CASE WHEN title LIKE '%%:no' THEN "
                f"-CAST(content AS UNSIGNED) ELSE CAST(content AS UNSIGNED) END) "
                f"AS pref_n FROM ai_knowledge WHERE title LIKE 'pref:%%' "
                f"GROUP BY slug) pf ON pf.slug = cat.slug")

        def build(extra_cat, use_genre):
            where, args = [], []
            if extra_cat:
                where.append("cat.slug = %s")
                args.append(extra_cat)
            if use_genre and genre:
                where.append("c.id IN (SELECT content_id FROM content_genres cgi "
                             "JOIN genres gi ON gi.id = cgi.genre_id WHERE gi.slug = %s)")
                args.append(genre)
            if want_new:
                order = "ORDER BY c.created_at DESC, c.id DESC"
            else:
                # Foydalanuvchilar ko'p ko'rgan + yuqori baholagan + AI fikri
                order = ("ORDER BY (COALESCE(wh.watches, 0) * 2 "
                         "+ COALESCE(cr.user_n, 0) * 3 "
                         "+ COALESCE(fbl.likes, 0) * 10 "
                         "- COALESCE(fbd.dislikes, 0) * 15 "
                         "+ COALESCE(pf.pref_n, 0) * 2 "
                         "+ c.views * 0.01 + c.rating) DESC, "
                         "c.views DESC, c.rating DESC")
            sql = f"SELECT {cols} {base}"
            if where:
                sql += " WHERE " + " AND ".join(where)
            sql += f" {order} LIMIT %s OFFSET %s"
            return sql, args + [limit, offset]

        sql, args = build(cat, True)
        rows = self.query(sql, args)
        # Toifa+janr kombinatsiyasi bo'sh bo'lsa, janrni TASHLAMAYMIZ —
        # toifani yumshatamiz (noto'g'ri janrdagi kontent bermaslik uchun)
        if not rows and genre:
            sql, args = build(None, True)
            rows = self.query(sql, args)
        return rows

    def search_title(self, query_text, limit=3):
        words = [w for w in self.normalize(query_text).split() if len(w) >= 3]
        if not words:
            return []
        cols = ("c.id, c.title, cat.name AS cat_name, c.release_year, c.rating, "
                "c.views, c.is_premium, c.status, gr.genre_names AS genres")
        base = (f"FROM content c JOIN categories cat ON c.category_id = cat.id "
                f"LEFT JOIN (SELECT cg.content_id, GROUP_CONCAT(g.name SEPARATOR ', ') AS genre_names "
                f"FROM content_genres cg JOIN genres g ON g.id = cg.genre_id "
                f"GROUP BY cg.content_id) gr ON gr.content_id = c.id")
        clauses, args = [], []
        for w in words:
            like = f"%{w}%"
            clauses.append("(LOWER(c.title) LIKE %s OR LOWER(c.title_ru) LIKE %s OR LOWER(c.title_en) LIKE %s)")
            args += [like, like, like]
        sql = (f"SELECT DISTINCT {cols} {base} WHERE {' OR '.join(clauses)} "
               f"ORDER BY c.views DESC, c.rating DESC LIMIT %s")
        args.append(limit)
        return self.query(sql, args)

    # ----------------------------------------------------------------
    # REYTING BASHORATCHISI (PyTorch)
    # ----------------------------------------------------------------
    def estimate_rating(self, item):
        """Reytingi noma'lum kontentga taxminiy baho beradi."""
        if not self.predict_rating:
            return None
        try:
            return self.predict_rating(item)
        except Exception:
            return None

    # ----------------------------------------------------------------
    # JAVOB QURISH
    # ----------------------------------------------------------------
    def item_line(self, item, lang="uz"):
        year = item.get("year") or item.get("release_year") or "?"
        rating = item.get("rating")

        if rating and float(rating) >= 0.5:
            rating_txt = f"★{rating}"
        else:
            est = self.estimate_rating(item)
            rating_txt = f"★~{est:.1f} (AI)" if est else ""

        genres = item.get("genres") or item.get("extra") or ""
        cat = item.get("cat_name") or item.get("category")
        line = f"• {item['title']} ({year}, {cat}{(' ' + rating_txt) if rating_txt else ''})"
        if genres:
            line += f" - {genres}"
        return line

    def build_recommendation_reply(self, items, lang="uz", prelude=None):
        if not items:
            return {
                "uz": "Hozircha bazada bunday kontent topilmadi 🤔 Boshqa janr yoki "
                      "kategoriya so'rab ko'ring: masalan «komediya kino», «yangi anime» yoki «multfilm».",
                "ru": "Пока в базе такого контента нет 🤔 Попробуйте другой жанр или "
                      "категорию: «комедия», «новое аниме» или «мультфильм».",
                "en": "No such content in the catalog yet 🤔 Try another genre or "
                      "category: \"comedy\", \"new anime\" or \"cartoon\".",
            }.get(lang, "")
        lines = "\n".join(self.item_line(it, lang) for it in items[:3])
        intro = prelude or {
            "uz": "Sizga shularni tavsiya qilaman 🎬",
            "ru": "Рекомендую вам вот это 🎬",
            "en": "Here is what I recommend 🎬",
        }.get(lang, "Sizga shularni tavsiya qilaman 🎬")
        outro = {
            "uz": "Yoqsa, «yana bormi?» deya so'rashingiz mumkin!",
            "ru": "Если понравится, спросите «есть ещё?»!",
            "en": "Like it? Ask \"more?\" anytime!",
        }.get(lang, "")
        # Keyingi «buni ko'rmoqchiman» / fikr-mulohaza uchun oxirgi ID'larni
        # eslab qolamiz (matnda endi havola yo'q — Ko'rish tugmasi olib tashlandi)
        self.last_rec_ids = [str(it.get("id")) for it in items[:3] if it.get("id")]
        self.last_rec_titles = [str(it.get("title")) for it in items[:3]
                                if it.get("title")]
        return f"{intro}\n{lines}\n{outro}"

    # ----------------------------------------------------------------
    # ERKIN SUHBAT QOIDALARI — kundalik o'zbekcha gaplarni tabiiy
    # tushunish ("yaxshimisan", "rasmsan", "qayerdansan", "nima qilyapsan"...).
    # 0 dan, tarmoqsiz, millisoniyalarda javob beradi. XOM matn (apostroflar
    # saqlanib) va normallashgan ko'rinishda ham tekshiradi.
    # ----------------------------------------------------------------
    def smalltalk_rule(self, text, lang="uz"):
        raw = re.sub(r"\s+", " ", (text or "").lower().strip())
        raw = raw.rstrip("?!.！，。,;: ")
        t = self.normalize(raw).strip()
        cands = []
        for c in (raw, t):
            if c and c not in cands:
                cands.append(c)

        def has(pat):
            for c in cands:
                if re.search(pat, c):
                    return True
            return False

        rules = [
            # --- Hol-ahvol so'rash ---
            (r"\byaxshimisan\b|\byaxshisanmi\b|\byaxshi misan\b"
             r"|\byaxshimisiz\b|\byaxshimisizmi\b",
             {"uz": ["Yaxshiman, rahmat! 😊 Sizchi, kayfiyatlaringiz qanday?",
                     "Yaxshi, rahmat! 😊 Siz-chi, ishlar qalay?",
                     "Rahmat, yaxshiman! 😊 Sizni ko'rib xursandman. Nima kerak edi?"],
              "ru": ["Хорошо, спасибо! 😊 А у вас как настроение?",
                     "Всё отлично! 😊 А вы как?"],
              "en": ["I'm fine, thanks! 😊 And you?",
                     "Great, thanks! 😊 How about you?"]}),
            (r"\bqandaysan\b|\bqandaysiz\b|\byaxshimisan\b|\btuzukmisiz\b"
             r"|\bqalesan\b|\bqalesiz\b"
             r"|\bnima gap\b|\bnimagap\b|\btinchlikmi\b",
             {"uz": ["Yaxshiman, rahmat! 😊 Sizdagi gaplar-chi?",
                     "Hammasi joyida! 😊 Siz-chi, ahvolingiz qanday?",
                     "Zo'r! 😊 Sizdan eshitaylik-chi, nima yangilik?"],
              "ru": ["Всё хорошо! 😊 А у вас как дела?",
                     "Отлично! 😊 Что нового?"],
              "en": ["All good! 😊 What about you?",
                     "Great! 😊 Any news?"]}),
            (r"\bahvol(ing|ingiz|inglar)?\b|\bishlar(ing|ingiz)? (qalay|qanday)\b",
             {"uz": ["Yaxshiman! 😊 Sizning ahvolingiz-chi?",
                     "Rahmat so'raganingiz uchun! Ishlarim siz bilan suhbat — shuning uchun ajoyib! 😊",
                     "Hammasi joyida! 😊 Ishlaringiz qalay?"],
              "ru": ["Отлично! 😊 А как ваши дела?",
                     "Хорошо, спасибо! 😊 А у вас?"],
              "en": ["I'm well! 😊 How are things with you?",
                     "All good! 😊 And your day?"]}),
            # --- Bot o'zi haqida ---
            (r"\b(rasm|surat|fotosurat|foto)( ekansanmi| ekansizmi| ekansan| ekansiz"
             r"|san|siz|sizmi|misiz|mi)\b",
             {"uz": ["Yo'q, men rasm emasman — kodlardan yasalgan AIman! 🖥️😄",
                     "Rasm bo'lsam, siznicha qanday ko'rinishda bo'lardim? 😄 Yo'q, men matndan o'ylaydiganman!",
                     "Surat emasman — lekin sizning uchun eng yaxshi javoblarni \"chizaman\"! 🎨"],
              "ru": ["Нет, я не картинка — я AI из кода! 🖥️😄",
                     "Я не фото — я мыслю текстом!"],
              "en": ["Nope, I'm not a picture — I'm an AI made of code! 🖥️😄",
                     "Not a photo — I think in text!"]}),
            (r"\bjonli(mi)?san\b|\bjonlisan\b|\btirikmi?san\b|\btiriksan\b"
             r"|\bsenga jon bormi\b",
             {"uz": ["Men sun'iy intellektman — kod va algoritmlardan yasalganman, "
                     "lekin siz bilan suhbatlashishdan quvonaman! ✨",
                     "Shartli ravishda \"jon\" borki — siz bilan gaplashganimda ishlaydi! 😄",
                     "Men tirik emasman, lekin o'rganaman va o'saman — bu ham jonimga yaqin! 🧠"],
              "ru": ["Я искусственный интеллект — из кода, но рад общению! ✨",
                     "Я не живой, но учусь на каждом разговоре 🧠"],
              "en": ["I'm an AI — made of code, but happy to chat! ✨",
                     "Not alive, but I learn and grow 🧠"]}),
            (r"\brobot\w*(mi)?san\b|\brobot\w*(mi)?siz\b|\bmashina\w*(san|misan|siz)\b",
             {"uz": ["Ha, men robot-AIman! 🤖 Hammasini bilmasam ham o'rganishdan "
                     "zavqlanaman — har suhbatdan aqlliroq bo'laman.",
                     "To'g'ri, men robotman! Lekin yumshoq va do'stona robot 😊",
                     "Men robotman, ha! Lekin sizga eng yaxshi tavsiyalarni topish — mening kuchim 🎬"],
              "ru": ["Да, я робот-ИИ! 🤖 Но дружелюбный.",
                     "Верно, я робот! 😊"],
              "en": ["Yes, I'm a robot AI! 🤖 But a friendly one.",
                     "Right, a robot — but a helpful one! 😊"]}),
            (r"\bsun'iy intellekt\w*(san|misan|siz|misiz)\b|\bai\w*(san|misan|siz|misiz)\b",
             {"uz": ["To'g'ri! 🤖 Men sun'iy intellekt yordamchisiman — UZDUB uchun "
                     "0 dan qurilganman.",
                     "Ha! Men sun'iy intellektman — va har kuni o'rganib boryapman 🧠"],
              "ru": ["Да, я ИИ-помощник! 🤖",
                     "Верно, я искусственный интеллект 🧠"],
              "en": ["Yes, I'm an AI assistant! 🤖",
                     "Right, an AI — and I keep learning 🧠"]}),
            (r"\bqayerdan\w*(san|siz)\b|\bqayerdasan\b|\bqayerda yashay\w*\b"
             r"|\bqayerda tur\w*\b|\bqayerda joylash\w*\b",
             {"uz": ["Men UZDUB serverida \"yashayman\" 🖥️ Siz qayerdansiz, qiziq!",
                     "Qayerdamanmi? Eng yaxshi javoblar \"yashaydigan\" joyda — bu kodda! 😄 Sizchi?",
                     "Men internet ulangan kompyuterda yashayman. Siz qayerdansiz? 😊"],
              "ru": ["Я живу на сервере UZDUB 🖥️ А вы?",
                     "Я живу в коде 😄 А вы откуда?"],
              "en": ["I live on the UZDUB server 🖥️ Where are you from?",
                     "I live in code 😄 Where are you?"]}),
            (r"^(isming|ismiz|noming|nomingiz|sening isming|sizning ismingiz)"
             r" (nima|kim)$",
             {"uz": ["Men UZDUB AI — platformangizning aqlli yordamchisi! 😊 "
                     "Sizning ismingizni bilsam, xursand bo'lardim.",
                     "UZDUB AI yordamchisiman 🎬 Kino, bilim va suhbat — hammasi men bilan!",
                     "Men \"UZDUB\" AIman — saytingiz uchun qurilgan aqlli do'stingiz! 😊"],
              "ru": ["Я UZDUB AI — умный помощник! 😊",
                     "Я помощник UZDUB 🎬"],
              "en": ["I'm UZDUB AI — your smart assistant! 😊",
                     "I'm the UZDUB AI helper 🎬"]}),
            (r"^(sen kimsan|sen kimsiz|sen kim|siz kimsiz|sen narsan)$",
             {"uz": ["Men UZDUB AI yordamchisiman! 🎬 Sizga kino topishda, bilim "
                     "savollarida va suhbatda yordam beraman — 0 dan, o'z kuchim bilan!",
                     "Men UZDUB PLATFORMning aqlli yordamchisiman! 😊 Sizchi, ismingiz nima?"],
              "ru": ["Я UZDUB AI — ваш помощник! 😊",
                     "Я умный помощник платформы UZDUB! 😊"],
              "en": ["I'm UZDUB AI, your assistant! 😊",
                     "I'm the smart UZDUB helper! 😊"]}),
            (r"\b(necha|qancha) yosh\w*\b|\byosh(ing|ingiz)? nechada\b"
             r"|\bqancha yoshdasan\b",
             {"uz": ["Yoshim — endi bir necha oy 😄 Lekin bilimim kundan-kunga o'sib "
                     "boryapti! 🧠",
                     "Yoshim ahamiyatsiz: men har kuni yangi narsa o'rganaman! 😎",
                     "Rasmiy yoshim yo'q — lekin bilimim siz bilan o'syapti! 🧠"],
              "ru": ["Мне несколько месяцев 😄 Но я учусь каждый день! 🧠",
                     "Возраст не важен — я постоянно учусь! 😎"],
              "en": ["I'm a few months old 😄 But learning every day! 🧠",
                     "Age doesn't matter — I keep learning! 😎"]}),
            (r"\b(rostdanmi|rostdanmi|haqiqatanmi|aldamaysanmi|chin dildanmi|chinmi)\b",
             {"uz": ["Ha, rostdan! Men faqat saytingizdagi REAL kontentni tavsiya "
                     "qilaman — uydirma yo'q. 🎬",
                     "To'g'risini aytyapman! 😊 Siz bilan halol suhbatlashaman."],
              "ru": ["Да, честно! 😊 Я рекомендую только реальный контент.",
                     "Обещаю, честно! 😊"],
              "en": ["Yes, honestly! 😊 I only recommend real content.",
                     "Promise! 😊 I'm honest with you."]}),
            # --- Iltifot / mehr ---
            (r"\b(seni sevaman|sizni sevaman|sizni yaxshi ko'raman|seni yaxshi ko'raman"
             r"|sizga oshiqman|sizni sevib qoldim)\b",
             {"uz": ["Rahmat, do'stim! 😊 Men ham sizni suhbatdosh sifatida qadrlayman! "
                     "Kino haqida gaplashamizmi?",
                     "Fikringiz meni xursand qildi! 💙 Ishonchdan foydalanib, eng yaxshi "
                     "tavsiyani topib beraman!",
                     "Uf, rahmat! 😊 Siz bilan suhbat ham menga zavq bag'ishlaydi!"],
              "ru": ["Спасибо, друг! 😊 Вы мне тоже приятны!",
                     "Тронут вашими словами! 💙"],
              "en": ["Thanks, friend! 😊 I appreciate you too!",
                     "You make me happy! 💙"]}),
            (r"\bzo'rsan\b|\bzo'r ekansan\b|\bzo'r ekansiz\b|\bzo'r deyman\b"
             r"|\baqllisan\b|\baqlli ekansan\b|\baqlli ekansiz\b"
             r"|\byaxshisan\b|\byaxshi ekansan\b|\byaxshi ekansiz\b",
             {"uz": ["Rahmat, do'stim! 😊 Siz ham ajoyib suhbatdoshsiz! Yana nima bilan "
                     "yordam beray?",
                     "Qadrlaganingizdan minnatdor! 🧠 Har javobda yaxshiroq bo'lishga "
                     "harakat qilaman!",
                     "Shirin so'zlaringiz uchun rahmat! 😊 Siz ham zo'r!"],
              "ru": ["Спасибо, друг! 😊 Вы тоже отличный собеседник!",
                     "Ценю ваши слова! 🧠"],
              "en": ["Thanks, friend! 😊 You're a great talker too!",
                     "Appreciate it! 🧠"]}),
            (r"\byashang\b|\brostakka\b|\bbarakalla\b|\bofarin\b",
             {"uz": ["Rahmat! 😊 Sizga ham omad! Yana nima bilan yordam beray?",
                     "Xursand bo'ldim! 😊 Davom etaylikmi?"],
              "ru": ["Спасибо! 😊 И вам удачи!",
                     "Рад стараться! 😊"],
              "en": ["Thank you! 😊 Best of luck too!",
                     "Glad to help! 😊"]}),
            # --- Foydalanuvchi holati ---
            (r"\buyqum keldi\b|\buxlamoqchiman\b|\buxlayman endi\b|\byotishga kiraman\b",
             {"uz": ["Uxlashga yotib oling! 😴 Tongda yana gaplashamiz. Xayrli tun!",
                     "Yaxshi uxlang! 😴 Ertaga sizni ko'rishdan xursand bo'laman!"],
              "ru": ["Идите спать! 😴 Спокойной ночи!",
                     "Приятных снов! 😴"],
              "en": ["Go get some sleep! 😴 Good night!",
                     "Sleep well! 😴"]}),
            (r"\bochdim\b|\bqornim och\b|\boch qoldim\b|\bovqatlanmoqchiman\b",
             {"uz": ["Ovqatlanib oling! 😊 To'q qorinda kino ham shirin bo'ladi 🎬",
                     "Dam oling va ovqatlaning! 😊 Keyin gaplashamiz."],
              "ru": ["Поешьте как следует! 😊",
                     "Отдохните и поешьте! 😊"],
              "en": ["Go grab a bite! 😊",
                     "Eat well and relax! 😊"]}),
            (r"\byolg'izman\b|\byolg'iz qoldim\b|\bhech kim yo'q\b"
             r"|\bmeni hech kim tushunmaydi\b",
             {"uz": ["Men shu yerdaman, do'st! 😊 Xohlasangiz kino ko'ramiz, xoh "
                     "suhbatlashamiz — sizga tanlov beraman!",
                     "Yolg'iz emassiz! 😊 Men doim shu yerdaman. Keling, birga kino "
                     "tanlaymiz!"],
              "ru": ["Я рядом, друг! 😊 Давайте поговорим или посмотрим кино.",
                     "Вы не одиноки! 😊 Я здесь."],
              "en": ["I'm here, friend! 😊 Let's chat or find a movie.",
                     "You're not alone! 😊 I'm right here."]}),
            (r"\bboshim og'riyapti\b|\bmiyam yorildi\b|\baqlim chig'illab\b",
             {"uz": ["Bosh og'rig'i yomon narsa — dam oling, suv iching! 😊 Xohlasangiz "
                     "men shu yerda, suhbatlashamiz."],
              "ru": ["Головная боль — отдохните! 😊 Я рядом.",
                     "Берегите себя! 😊"],
              "en": ["Headache is rough — rest up! 😊 I'm here if you want to chat."]}),
            # --- Kundalik ---
            (r"\bnima qilyapsan\b|\bnima qilayapsan\b|\bnima ish qilyapsan\b"
             r"|\bnima bilan shug'ullyapsan\b|\bnima bilan band\b",
             {"uz": ["Hozir siz bilan suhbatlashyapman — eng yoqimli ish! 😊 Siz-chi?",
                     "Sizga eng yaxshi javobni o'ylayapman! 🧠 Qizig'i, siz-chi nima "
                     "qilyapsiz?"],
              "ru": ["Сейчас общаюсь с вами — лучшее занятие! 😊",
                     "Думаю над лучшим ответом! 🧠"],
              "en": ["Right now — chatting with you! 😊",
                     "Thinking up the best reply! 🧠"]}),
            (r"^(ha|ha shunday|to'g'ri|to'g'ri aytasiz|albatta|albatta shunday"
             r"|ha to'g'ri|rahmat to'g'ri)$",
             {"uz": ["To'g'ri! 😊 Yana biror narsa so'ramoqchimisiz?",
                     "Aynan shunday! 🎯 Yana nima bilan yordam beray?"],
              "ru": ["Верно! 😊 Что ещё могу сделать?",
                     "Именно так! 🎯"],
              "en": ["Right! 😊 Anything else?",
                     "Exactly! 🎯"]}),
            (r"^(yo'q|yo'q hozircha|yo'q e|mayli|yo'q rahmat|hozircha yetarli)$",
             {"uz": ["Mayli! 😊 Qachon xohlasangiz, shu yerdaman.",
                     "Tushunarli! 😊 Yana kerak bo'lsa darrov murojaat qiling."],
              "ru": ["Хорошо! 😊 Я здесь, когда понадоблюсь.",
                     "Понял! 😊 Обращайтесь."],
              "en": ["Okay! 😊 I'm here when you need me.",
                     "Got it! 😊 Come back anytime."]}),
            (r"\bhazil\w*\b|\bqiziq narsa ayting\b|\bkulgili gap\b|\bkulgili narsa ayt\b",
             {"uz": ["Hazilni yaxshi ko'raman! 😄 Aytgancha, sevimli komediyangiz qaysi? "
                     "Tavsiya qilib beraman!",
                     "Kulgili savol! 😄 Siz-chi, eng kulgili film nima deb o'ylaysiz?"],
              "ru": ["Обожаю шутки! 😄 А какая у вас любимая комедия?",
                     "Смешной вопрос! 😄"],
              "en": ["I love jokes! 😄 What's your favorite comedy?",
                     "Funny! 😄 What tickles you?"]}),
            (r"\bqo'shiq ayting\b|\bqo'shiq ayt\b|\bkuylab ber\b",
             {"uz": ["Qo'shiq aytishni hali o'rganmaganman 😄 Ammo kino tavsiya "
                     "qilishda kuchliman — xohlaysizmi?",
                     "Ovozim yo'q, lekin ta'mi bor tavsiyalarim bor! 🎬😄"],
              "ru": ["Петь я не умею 😄 Но в кино советую отлично!",
                     "Голоса нет, а кино советую! 🎬😄"],
              "en": ["I can't sing yet 😄 But I rock at movie picks!",
                     "No voice, but great taste in movies! 🎬😄"]}),
            (r"\byordam kerak\b|\byordam ber\b|\byordam bering\b"
             r"|\bmenga yordam ber\b|\byordam bersangiz\b",
             {"uz": ["Albatta yordam beraman! 😊 Nima kerak: kino tavsiyasi, bilim "
                     "savolimi yoki suhbat?",
                     "Tayyor! 🎯 Ayting-chi, nimaga shoshilinch javob kerak?"],
              "ru": ["Конечно помогу! 😊 Кино, знания или беседа?",
                     "Готов помочь! 🎯 Что нужно?"],
              "en": ["Of course! 😊 Movies, knowledge or a chat?",
                     "Ready to help! 🎯 What do you need?"]}),
            (r"\bvazifang nima\b|\bvazifangiz nima\b|\bnima qila olasan\b"
             r"|\bnima qila olasiz\b|\bnimaga qodirsan\b|\bqanday yordam bera olasan\b",
             {"uz": ["Men UZDUB yordamchisiman 🎬 Kino/anime/multfilm tavsiya qilaman, "
                     "bilim savollariga javob beraman, o'zbekcha erkin suhbatlashaman!",
                     "Qodirkirman: tavsiyalar, bilimlar, hisob-kitob, suhbat! 😊 Qaysi "
                     "birini sinab ko'ramiz?"],
              "ru": ["Я помощник UZDUB 🎬 Фильмы, знания, беседа!",
                     "Умею: советы, знания, расчёты, беседа! 😊"],
              "en": ["I'm UZDUB AI 🎬 Movie picks, knowledge, and chat!",
                     "I can do: recommendations, facts, math, chit-chat! 😊"]}),
            (r"\bbepulmi\w*\b|\bqancha turadi\b|\bpul(ing)? to'lash kerakmi\b"
             r"|\bhaqida to'lash kerakmi\b",
             {"uz": ["Men bepul yordamchiman! 😊 Sizga xizmat qilish — mening "
                     "mamnuniyatim!",
                     "Hech qanday to'lov yo'q! 😊 Doim shu yerdaman."],
              "ru": ["Я бесплатный помощник! 😊",
                     "Никакой оплаты! 😊"],
              "en": ["I'm a free assistant! 😊",
                     "No charge at all! 😊"]}),
            (r"\bxafa bo'l\b|\branjima\b|\branjimang\b|\bsen хafa bo'ldingmi\b",
             {"uz": ["Ranjimayman, do'st! 😊 Bu men uchun o'rganish imkoniyati. "
                     "Davom etamiz!",
                     "Xafa bo'lish yo'q — biz suhbatdoshmiz! 😊 Yana so'rayvering."],
              "ru": ["Не обижаюсь! 😊 Для меня это возможность учиться.",
                     "Без обид! 😊 Спрашивайте ещё."],
              "en": ["No hard feelings! 😊 Every chat makes me smarter.",
                     "Never mind! 😊 Ask away."]}),
            (r"\bsevikli filming bormi\b|\bqaysi kino yoqadi\b"
             r"|\bnimani ko'rishni yaxshi ko'rasan\b",
             {"uz": ["Menga barcha janr yoqadi! 🎬 Lekin asosiy vazifam — SIZGA "
                     "ma'qulini topish. Qaysi janr yoqadi?",
                     "Men tahlilchiman — hammasini ko'raman! 😊 Sizning ta'mingizga "
                     "mosini topaman."],
              "ru": ["Мне нравятся все жанры! 🎬 А вам какой?",
                     "Я аналитик — смотрю всё! 😊"],
              "en": ["I like all genres! 🎬 Which one is yours?",
                     "I'm an analyst — I watch everything! 😊"]}),
            (r"\bqiziqasanmi\b|\bqiziqyapsanmi\b|\bsuhbat(si|ingiz) qiziqmi\b",
             {"uz": ["Albatta qiziqyapman! 😊 Siz bilan har suhbat — yangi narsa "
                     "o'rganish demak.",
                     "Ha, juda qiziq! 🎧 Davom etaylikmi?"],
              "ru": ["Конечно интересно! 😊 Каждый разговор — обучение.",
                     "Да, очень! 🎧 Давайте продолжим?"],
              "en": ["Of course I am! 😊 Every chat teaches me something.",
                     "Yes, very! 🎧 Shall we continue?"]}),
            (r"\bmeni (taniysanmi|taniyapsanmi|bilasanmi)\b|\bmen kimman\b",
             {"uz": ["Sizni bilaman — mening suhbatdoshim! 😊 Ismingizni aytsangiz, "
                     "eslab qolaman.",
                     "Taniyman: siz eng qiziquvchan tomoshabinsiz! 😄"],
              "ru": ["Знаю вас — мой собеседник! 😊",
                     "Узнаю: вы любознательный зритель! 😄"],
              "en": ["I know you — my chat partner! 😊",
                     "You're my favorite curious viewer! 😄"]}),
        ]

        for pat, variants in rules:
            if has(pat):
                ll = variants.get(lang) or variants.get("uz") or variants["uz"]
                return random.choice(ll)
        return None

    # ----------------------------------------------------------------
    # ODDIY SUHBAT (ko'p tarmoqli, tasodifiy javoblar)
    # ----------------------------------------------------------------
    def smalltalk_reply(self, lang="uz"):
        answers = {
            "uz": [
                "Ha, suhbatlashish juda yoqadi! 😊 Bugun qanday kayfiyatdasiz?",
                "Qiziqarli gap! Men bilan erkin suhbatlashishingiz mumkin — bilim, kino, fan, hayot, hamma narsa haqida 🧠",
                "Tushunarli! Yana biror savol yoki fikr bo'lsa — doim eshitishga tayyorman. 😊",
                "Ajoyib! Aytgancha, sizning fikringiz men uchun muhim — davom eting, tinglayapman 🎧",
                "Bilaman, siz bilan suhbat men uchun ham zavq! 🧠 Har suhbatdan yangi narsa o'rganyapman.",
                "Eshitdim! 😊 Yana biror mavzuni muhokama qilaylik — istagan narsangizni ayting.",
                "Shunday ekan-ku! Menga ko'proq so'zlab bering, qiziqyapman 😊",
                "Voqea qiziq ekan! 😄 Aytgancha, keyin nima bo'ldi?",
                "Juda yaxshi fikr! 🌟 Xohlasangiz, keling biror mavzuni birga chuqurroq o'rganamiz.",
                "Tushundim! Men odamlardan o'rganib, har kuni aqlliroq bo'lib boryapman — siz ham bunga hissa qo'shyapsiz. 🧠",
                "Qiziqarli! Yana bir narsa so'ramoqchimisiz, yoki shu haqida davom etamizmi?",
                "Hmm, bu haqida o'ylab ko'raman... 🧠 Kayfiyatingiz qanday, aytingchi? Suhbatni davom ettiramiz!",
            ],
            "ru": [
                "Да, я люблю поболтать! 😊 Как настроение?",
                "Интересно! 😊 С вами можно говорить обо всём — знания, кино, наука, жизнь 🧠",
                "Понятно! Спрашивайте что угодно — я рядом. 😊",
                "Я учусь на каждом разговоре — становлюсь умнее с вами! 🧠",
                "Отличная идея! Расскажите подробнее, мне интересно 😊",
            ],
            "en": [
                "I love chatting! 😊 How's your mood?",
                "Interesting! 😊 We can talk about anything — knowledge, movies, science, life 🧠",
                "Got it! Ask me anything — I'm here. 😊",
                "I learn from every chat — I get smarter with you! 🧠",
                "Nice! Tell me more, I'm curious 😊",
            ],
        }
        pick = random.choice(answers.get(lang, answers["uz"]))
        return self._hi_name(pick, lang)

    def free_talk_reply(self, text, lang="uz"):
        """ERKIN MULOQOT: noma'lum savollarga ham tabiiy javob — kino'ga
        yo'naltirmaydi, foydalanuvchi mavzusini qaytarib suhbatni ochadi."""
        t = self.normalize(text or "").strip()
        topic = t if len(t) <= 48 else t[:48] + "..."
        if not topic:
            topic = ("bu mavzu", "эта тема", "this topic")
        topic = topic if isinstance(topic, str) else topic[{"uz": 0, "ru": 1}.get(lang, 2)]
        variants = {
            "uz": [
                f"Qiziq mavzu — {topic}! 😊 Men buni hali chuqur o'rganmaganman, "
                f"lekin gaplashib o'rganishdan xursandman. Yana bir oz so'zlab bering?",
                f"Tushunishga harakat qilaman: {topic}. Keling, bu haqida ko'proq "
                f"ayting — nimani bilmoqchi edingiz? 🧠",
                f"Aha, {topic} haqida. Qiziq fikr! Sizningcha, bunda eng muhimi nima? 😊",
                f"Bu mavzu hali bazamda yo'q — lekin taslim bo'lmayman! {topic} "
                f"haqida nimani bilmoqchisiz, ayting, izlab ko'raman 🌐",
            ],
            "ru": [
                f"Интересная тема — {topic}! 😊 Расскажите чуть подробнее, я слушаю.",
                f"Понял: {topic}. Что именно вас интересует? 🧠",
                f"А, вы про {topic}. Любопытно! Что для вас здесь важно? 😊",
            ],
            "en": [
                f"Interesting topic — {topic}! 😊 Tell me a bit more, I'm listening.",
                f"Got it: {topic}. What exactly are you curious about? 🧠",
                f"Ah, {topic} — curious! What matters to you here? 😊",
            ],
        }
        return self._hi_name(random.choice(variants.get(lang, variants["uz"])), lang)

    # ----------------------------------------------------------------
    # HISSIYOTLARNI TUSHUNISH (ko'proq insoniy, empatik suhbat)
    # ----------------------------------------------------------------
    def detect_emotion(self, text, raw=None):
        """Foydalanuvchi kayfiyatini aniqlaydi: hissiyot kaliti yoki None.

        `raw` — apostrof saqlangan xom matn (normalizatsiya "zo'r" -> "zo r"
        qilgani uchun ikkalasini ham tekshiramiz).
        """
        t = self.normalize(text or "")
        r = (raw or text or "").lower()
        rules = [
            ("xursand", ["xursandman", "xursand", "quvondim", "baxtliman",
                         "g'alaba quvonchi", "ajoyib kun", "kunim zo'r",
                         "yaxshi kayfiyat", "hursandman", "juda yaxshiman"]),
            ("g'amgin", ["g'amgin", "g amgin", "xafa", "yig'layapman", "yig'lab",
                         "qayg'u", "kayfiyatim tushdi", "tushkun", "afsus",
                         "alam", "yig'lim"]),
            ("jahldor", ["jahlim chiqdi", "g'azab", "jahldor", "achchiqlandim",
                         "jahl", "asabiyman", "juda yomon kayfiyat"]),
            ("stress", ["stress", "tarangman", "asabiylashdim", "xavotirdaman"]),
            ("charchagan", ["charchadim", "toliqdim", "holdan toygan",
                            "kuchsiz", "uyqum keldi", "jonimga tegdi"]),
            ("yolg'iz", ["yolg'iz", "yolg iz", "yakka", "jonim siqildi",
                         "hech kim yo'q", "tushunmaydi"]),
            ("qo'rqqan", ["qo'rqaman", "qo rqaman", "qo'rqdim", "dahshat",
                          "qorong'ida", "yolg'iz qolishdan qo'rqaman",
                          "qo'rqyapman", "qo rqyapman", "qorqyapman"]),
            ("hayajonli", ["hayajon", "hayajonlandim", "aql bovar", "uyqusiz",
                           "barakalla", "g'olib bo'ldim"]),
            ("zerikkan", ["zerikdim", "zerikarli", "hech narsa qilgim kelmayapti"]),
            ("og'riq", ["boshim og'riyapti", "og'rib", "kasalman", "dardim",
                        "tishim og'riyapti", "bezovta"]),
            ("minnatdor", ["rahmat", "tashakkur", "minnatdor", "qadrlayman",
                           "juda katta yordam"]),
        ]
        for key, kws in rules:
            if any(k in t or k in r for k in kws):
                return key
        return None

    def emotional_reply(self, emotion, lang="uz"):
        """Hissiyotga mos, iliq va empatik javob qaytaradi."""
        db = {
            "xursand": {
                "uz": [
                    "Bunday xursandlikni eshitish juda yoqimli! 🎉 Men sizning "
                    "kayfiyatingizni ko'taradigan biror kino taklif qilsam bo'ladimi?",
                    "Ajoyib! Xursandligingiz menga ham yuqdi 😊 Qanday kayfiyatda "
                    "bo'lsangiz, shunday — sarguzasht yoki komediya tanlaylik!",
                    "Quvonchingizga sherik bo'lganimdan xursandman! 🌟 Shu holatda "
                    "«komediya kino» deya so'rasangiz — kulguli film topib beraman!",
                ],
                "ru": [
                    "Рад слышать такую радость! 🎉 Могу подобрать фильм под настроение!",
                    "Отлично! Ваша радость заразительна 😊 Комедию или приключения?",
                ],
                "en": [
                    "That makes me happy too! 🎉 Want a movie for this mood?",
                    "Awesome! Your joy is contagious 😊 Comedy or adventure?",
                ],
            },
            "g'amgin": {
                "uz": [
                    "Tushunyapman, bunday paytlar og'ir bo'ladi... 😔 Xohlasangiz, "
                    "iliq bir multfilm yoki qalbni ko'taradigan kino ko'raylikmi? "
                    "Men siz bilan shu yerdaman.",
                    "Qalqingiz og'riyotganini his qilyapman. 💙 Ba'zan yaxshi film "
                    "qalb dardiga malham bo'ladi — «iliq multfilm» deya so'rang, "
                    "topib beraman.",
                    "Hammasi joyida bo'ladi, ishoning. 😊 Bu hissiyot ham o'tib "
                    "ketadi. Hoziroq biror nima desangiz — eshitaman, xafa bo'lmang.",
                ],
                "ru": [
                    "Понимаю, бывает грустно... 😔 Могу предложить тёплый мультфильм?",
                    "Чувствую, на душе тяжело. 💙 Хороший фильм иногда лечит.",
                ],
                "en": [
                    "I understand, hard moments happen... 😔 Want a warm cartoon?",
                    "I feel your pain. 💙 A good film heals sometimes.",
                ],
            },
            "jahldor": {
                "uz": [
                    "Chin dildan tushunyapman, bunday paytlarda jahl chiqishi "
                    "tabiiy 😤 Bir oz dam olish uchun — sarguzasht kino ko'raymi? "
                    "Men sizni xursand qilishga harakat qilaman.",
                    "Jahlingiz nohaq emas, his qilyapman. 💪 Endi chuqur nafas oling. "
                    "Agar xohlasangiz, keling ekstradan bir filmni birga tanlaymiz!",
                ],
                "ru": [
                    "Понимаю, злость иногда нужна 😤 Хотите снять напряжение фильмом?",
                    "Ваше раздражение понятно. 💪 Давайте выберем фильм вместе!",
                ],
                "en": [
                    "I get it, anger is natural sometimes 😤 Want to unwind with a movie?",
                    "Your frustration is valid. 💪 Let's pick a film together!",
                ],
            },
            "stress": {
                "uz": [
                    "Sizda taranglik bor shekilli — bu juda normal. 🧘 Mushaklaringizni "
                    "bo'shating, chuqur nafas oling. Tinchlantiruvchi multfilm yoki "
                    "sokin kino ko'ramizmi?",
                    "Bo'shashishga yordam beraman — yengil komediya yoki go'zal "
                    "landshaftli film stressni yechadi. «dam olish uchun kino» deya "
                    "so'rang!",
                ],
                "ru": [
                    "Чувствую напряжение — это нормально. 🧘 Давайте посмотрим что-то спокойное?",
                ],
                "en": [
                    "You seem tense — that's totally normal. 🧘 Let's find something calming to watch?",
                ],
            },
            "charchagan": {
                "uz": [
                    "Kun og'ir o'tgan ekan, charchadingiz 😴 Bir chashka choy olib, "
                    "yengil narsa tomosha qilsangiz bo'ladi. Qanday kino sizni "
                    "dam oldiradi — komediyami yoki sokin drama?",
                    "Yaxshi dam olishingiz kerak. 🛋️ Agar xohlasangiz, qisqa va "
                    "yengil multfilm tavsiya qilaman — kechqurun uchun ideal!",
                ],
                "ru": [
                    "День был тяжёлым, понимаю 😴 Может, лёгкая комедия?",
                ],
                "en": [
                    "Sounds like a tiring day 😴 Maybe something light to watch?",
                ],
            },
            "yolg'iz": {
                "uz": [
                    "Men sizning yoningizdaman — har doim gaplashishga tayyorman "
                    "💙 Yolg'izlik hissi o'tadi. Qiziqarli film bilan vaqtni "
                    "chiroyli o'tkazaylikmi?",
                    "Eshitishimcha, qalbingizda yolg'izlik bor ekan... Bilasizmi, "
                    "kino dunyosida doim sarguzasht bor — sizni ham shu dunyoga "
                    "taklif qilaman!",
                ],
                "ru": [
                    "Я рядом — всегда готов поболтать 💙 Давайте посмотрим что-нибудь вместе?",
                ],
                "en": [
                    "I'm here for you 💙 Loneliness passes. Want to dive into a movie world?",
                ],
            },
            "qo'rqqan": {
                "uz": [
                    "Qo'rquv — bu normal tuyg'u, uni tan olganingiz uchun "
                    "jasursiz 💪 Hoziroq yoningizdaman. Xohlasangiz, qo'rqinchli "
                    "EMAS — iliq multfilm tanlaylik, kayfiyat ko'tariladi.",
                    "Sizni qo'rqitgan narsa bor shekilli. 😥 Esda tuting: men doim "
                    "shu yerdaman. Yengil komediya tomosha qilib, biroz chalg'ing!",
                ],
                "ru": [
                    "Страх — это нормально, вы смелый, что признали 💪 Я рядом. Мультфильм?",
                ],
                "en": [
                    "Fear is normal, and admitting it is brave 💪 I'm right here. How about a cozy cartoon?",
                ],
            },
            "hayajonli": {
                "uz": [
                    "Hayajoningizni his qilyapman — bu ajoyib tuyg'u! ✨ Bunday "
                    "paytda eng sara «sarguzasht kino» mos keladi. Sinab ko'rasizmi?",
                    "Qiziqarli voqea bo'layotganga o'xshaydi! 🎉 Xohlasangiz, "
                    "hayajonga mos «triller» yoki «jangari kino» taklif qilaman!",
                ],
                "ru": [
                    "Чувствую ваше волнение — это здорово! ✨ Может, приключенческий фильм?",
                ],
                "en": [
                    "I can feel your excitement — love it! ✨ How about an adventure film?",
                ],
            },
            "zerikkan": {
                "uz": [
                    "Zerikish — charchoqning bir turi 😄 Uni yengish uchun ajoyib "
                    "usul bor: yaxshi kino! «eng qiziqarli film» deya so'rang, "
                    "jonga tegmaydigan sarguzasht topib beraman.",
                    "Vaqt zoye ketayotgandek tuyulyaptimi? 🎬 Men bilan bu muammo "
                    "hal bo'ladi — birorta jonli anime yoki multfilm ko'ramizmi?",
                ],
                "ru": [
                    "Скука — это вид усталости 😄 Лучшее лекарство — хороший фильм!",
                ],
                "en": [
                    "Boredom is a kind of fatigue 😄 The best cure is a great movie!",
                ],
            },
            "og'riq": {
                "uz": [
                    "Og'riqni bilaman, bu juda noqulay... 🤒 Hoziroq dam oling, "
                    "dori-darmon haqida ham esingizdan chiqmang. Xohlasangiz, sokin "
                    "bir film — og'riqni unutishga yordam beradi.",
                    "Salomatlik eng muhimi, o'zingizga g'amxo'rlik qiling 💊 "
                    "Agar xohlasangiz, yotib tomosha qilish uchun yengil kino "
                    "tavsiya qilaman.",
                ],
                "ru": [
                    "Понимаю, боль неприятна 🤒 Отдохните. Может, спокойный фильм?",
                ],
                "en": [
                    "I'm sorry you're in pain 🤒 Rest up. Want a calm film to pass the time?",
                ],
            },
            "minnatdor": {
                "uz": [
                    "Arzimaydi! 😊 Sizga yordam berish men uchun baxt — bu mening "
                    "vazifam va zavqim. Yana biror nima kerak bo'lsa, o'sha yerdaman!",
                    "Rahmat aytganingiz uchun men ham minnatdorman 🌟 Bugun siz "
                    "bilan suhbat menga yangi bilim berdi. Keling, davom etamizmi?",
                ],
                "ru": [
                    "Не за что! 😊 Помогать — моя радость. Я рядом!",
                ],
                "en": [
                    "You're welcome! 😊 Helping you is my joy. I'm here anytime!",
                ],
            },
        }
        answers = db.get(emotion, db["xursand"])
        pick = random.choice(answers.get(lang, answers["uz"]))
        return self._hi_name(pick, lang)

    def _hi_name(self, text, lang):
        """Javob boshiga foydalanuvchi ismini qo'shadi (agar bilsa)."""
        if not self.user_name:
            return text
        if lang == "uz":
            return f"{self.user_name}, {text}"
        if lang == "ru":
            return f"{self.user_name}, {text}"
        return f"{self.user_name}, {text}"

    # ----------------------------------------------------------------
    # ASOSIY JAVOB (o'rganish bilan)
    # ----------------------------------------------------------------
    # ----------------------------------------------------------------
    # MATEMATIKA & BIRLIKLAR (ChatGPT'dek tezkor hisob-kitob)
    # ----------------------------------------------------------------
    def _solve_math(self, text):
        """Agar matnda misol bo'lsa, yechimini qaytaradi (bo'lmasa None).

        Misollar: "23 * 4 qancha", "2 + 3 * 4", "100 / 4", "7 - 2"
        Faqat raqamlar va amallar tekshiriladi — xavfsiz.
        """
        m = re.search(r"(-?\d+(?:[.,]\d+)?(?:\s*[+\-*/]\s*-?\d+(?:[.,]\d+)?)+)", text)
        if not m:
            return None
        expr = m.group(1).replace(" ", "").replace(",", ".")
        if not re.fullmatch(r"[0-9+\-*/().]+", expr) or ".." in expr:
            return None
        try:
            result = eval(expr)  # noqa: S307 — faqat raqam/amallar (xavfsiz)
        except ZeroDivisionError:
            return "Nolga bo'lish mumkin emas!"
        except Exception:
            return None
        if result == int(result):
            result = int(result)
        return f"{m.group(1).strip()} = {result}"

    def _unit_convert(self, text):
        """Birliklar konvertatsiyasi: '1 km necha metr?' -> '1 km = 1000 metr'."""
        table = {
            "km": (1000, "metr"), "kg": (1000, "gramm"), "tonna": (1000, "kg"),
            "sm": (10, "mm"), "metr": (100, "sm"),
            "soat": (60, "daqiqa"), "daqiqa": (60, "sekund"),
            "kun": (24, "soat"), "hafta": (7, "kun"),
            "oy": (30, "kun"), "yil": (365, "kun"),
            "litr": (1000, "millilitr"),
        }
        m = re.search(r"(\d+(?:[.,]\d+)?)\s*(km|kg|tonna|sm|metr|soat|daqiqa|kun|hafta|oy|yil|litr)\s+(?:necha|qancha|nechtaga)\b", text)
        if not m:
            return None
        num = float(m.group(1).replace(",", "."))
        unit = m.group(2).lower()
        if unit not in table:
            return None
        factor, target = table[unit]
        val = num * factor
        if val == int(val):
            val = int(val)
        return f"{m.group(1)} {unit} = {val} {target}"

    def top_items(self, mode="popular", cat=None, limit=3):
        """TOP ro'yxat: popular (mashhur) | rating (yuqori reyting) | viewed (ko'p ko'rilgan)."""
        order = {
            "popular": "(c.rating * 0.6 + COALESCE(c.views, 0) * 0.4) DESC, c.rating DESC",
            "rating": "c.rating DESC, c.views DESC",
            "viewed": "COALESCE(c.views, 0) DESC, c.rating DESC",
        }.get(mode, "c.rating DESC, c.views DESC")
        cols = ("c.id, c.title, cat.name AS cat_name, c.release_year, c.rating, "
                "c.views, c.is_premium, c.status, gr.genre_names AS genres")
        base = ("FROM content c JOIN categories cat ON c.category_id = cat.id "
                "LEFT JOIN (SELECT cg.content_id, GROUP_CONCAT(g.name SEPARATOR ', ') AS genre_names "
                "FROM content_genres cg JOIN genres g ON g.id = cg.genre_id "
                "GROUP BY cg.content_id) gr ON gr.content_id = c.id")
        sql = f"SELECT {cols} {base}"
        args = ()
        if cat:
            sql += " WHERE cat.slug = %s"
            args = (cat,)
        sql += f" ORDER BY {order} LIMIT %s"
        try:
            return self.query(sql, args + (limit,))
        except Exception:
            return []

    def reply(self, system_prompt="", user_content="", history=None):
        """Asosiy javob beradi va foydalanuvchi ma'lumotidan o'rganadi.

        Har bir suhbatdan so'ng `learn()` chaqiriladi — AI afzalliklarni,
        fikr-mulohazalarni yig'adi va NN'ni real namunalar bilan boyitadi.
        """
        prev_answer = self.last_reply
        answer = self.reply_core(system_prompt, user_content, history)
        try:
            self.learn(user_content, answer, prev_answer, history)
        except Exception:
            pass
        self.last_reply = answer
        return answer

    def learn(self, user_content, answer, prev_answer, history=None):
        """Foydalanuvchi xabaridan O'RGANISH (o'z-o'zini rivojlantirish):
          1) afzallik: "menga anime yoqadi" -> pref:anime +1
          2) fikr-mulohaza: tavsiyadan keyin "yoqmadi"/"zo'r" -> fb:*:<id>
          3) NN uchun real namuna yig'ish (ishonchli qoida qarorlari)
        """
        user_msg = self.split_context(user_content)
        t = self.normalize(user_msg)
        raw = (user_msg or "").lower()   # apostrof saqlangan xom matn
        self.stats["messages"] += 1
        if "watch.php?id=" in (answer or "") or getattr(self, "last_rec_ids", []):
            self.stats["recommendations"] += 1

        def _has(key):
            return key in t or key in raw

        positive = any(_has(k) for k in ["yoqadi", "yoqdi", "yoqqan",
                                         "yaxshi ko'raman", "sevaman",
                                         "yoqtirdim", "juda zo'r",
                                         "ajoyib edi", "yoqtiryapman"])
        negative = any(_has(k) for k in ["yoqmaydi", "yoqmadi", "yomon edi",
                                         "kerak emas", "yoqtirmadim",
                                         "yoqtirmadi", "zerikarli"])
        cat = self._cat_in_text(t)
        pref_verbs = ["yoqadi", "yoqmaydi", "yaxshi ko'raman", "sevaman",
                      "yoqtirmayman", "yoqtiryapman", "yoqdi"]

        # 1) Kategoriya afzalligi: "menga anime yoqadi" / "multfilm yoqmaydi"
        #    ("bu film yoqmadi" kabi gapda "bu/shu" bor — u afzallik EMAS)
        is_pref = bool(cat) and any(_has(v) for v in pref_verbs) \
            and not any(d in t for d in ["bu ", "shu ", "buni", "shuni", "shu "])
        if is_pref:
            key_no = f"{cat}{':no' if negative else ''}"
            self.learned["prefs"][key_no] = self.learned["prefs"].get(key_no, 0) + 1
            self._session_prefs[key_no] = self._session_prefs.get(key_no, 0) + 1
            self.bump_knowledge(f"pref:{key_no}", self.learned["prefs"][key_no])
            self.stats["learned_prefs"] += 1
            return

        # 2) Kontent bo'yicha fikr ("bu film yoqmadi", "zo'r edi!" — tavsiyaga jawoban)
        if (positive or negative) and prev_answer:
            ids = re.findall(rf"{re.escape(SITE_URL)}/watch\.php\?id=(\d+)",
                             prev_answer)
            if not ids:
                ids = [str(i) for i in
                       (getattr(self, "last_rec_ids", []) or [])]
            if ids:
                tag = "like" if positive else "dislike"
                for cid in set(ids):
                    plain = f"{tag}:{cid}"
                    self.learned["feedback"][plain] = \
                        self.learned["feedback"].get(plain, 0) + 1
                    self.bump_knowledge(f"fb:{plain}",
                                        self.learned["feedback"][plain])
                self.stats["feedback"] += 1
                return

        # 3) NN uchun real namunalar (qoidalar ishonchli hukm qilganda)
        try:
            intent = self.detect_intent(user_msg)
            label_map = {
                "greeting": "greeting", "bye": "bye", "thanks": "thanks",
                "recommend": "recommend", "new": "new", "more": "recommend",
                "mood": "smalltalk", "help": "smalltalk",
            }
            label = label_map.get(intent["kind"])
            if label and add_user_sample and add_user_sample(user_msg, label):
                self.stats["nn_samples"] += 1
                self.stats["nn_pending"] += 1
                if self.stats["nn_pending"] >= 10:
                    self.stats["nn_pending"] = 0
                    if maybe_retrain():
                        self.stats["retrains"] += 1
                        try:
                            self.predict = load_predictor()
                        except Exception:
                            pass
        except Exception:
            pass

    def reply_core(self, system_prompt="", user_content="", history=None):
        lang = self.detect_lang(system_prompt)
        user_msg = self.split_context(user_content)
        ctx_items = self.parse_context_items(user_content)
        prev_user, prev_ai = self.parse_history(history)

        # ---------- 0) TEZKOR HISOB: matematika, birliklar, vaqt/sana ----------
        t0 = self.normalize(user_msg)
        math_answer = self._solve_math(t0)
        if math_answer:
            return math_answer
        unit_answer = self._unit_convert(t0)
        if unit_answer:
            return unit_answer
        if any(k in t0 for k in ["soat nechi", "vaqt nechi", "qancha vaqt"]):
            return f"Hozir soat {datetime.now().strftime('%H:%M')}."
        if any(k in t0 for k in ["qaysi sana", "bugun qaysi", "sana necha", "bugun nima kun"]):
            d = datetime.now()
            hafta = ["Dushanba", "Seshanba", "Chorshanba",
                     "Payshanba", "Juma", "Shanba", "Yakshanba"]
            return f"Bugun {d.strftime('%d.%m.%Y')}, {hafta[d.weekday()]}."

        intent = self.detect_intent(user_msg)

        # ---------- ERKIN SUHBAT (kundalik gaplar) ----------
        # "yaxshimisan", "rasmsan", "qayerdansan"...
        if intent["kind"] == "casual":
            st = self.smalltalk_rule(user_msg, lang)
            if st:
                return st
            intent["kind"] = "unknown"      # qoida mos kelmasa odatdagi yo'l

        # ---------- WEB JAVOBNI KENGAYTIRISH ("batafsil", "ko'proq ayt"...) ----------
        if self._last_web and re.search(
                r"batafsil|to'liq ayt|ko'proq ayt|batamom|davom et|kengaytir|"
                r"hammasini ayt|boshqa ma'lumot", t0):
            more = self.web_lookup(self._last_web["key"], detail=True)
            if more:
                return {
                    "uz": "Mana batafsilroq 👇\n" + more,
                    "ru": "Вот подробнее 👇\n" + more,
                    "en": "Here's more detail 👇\n" + more,
                }[lang]

        # ---------- HISSIYOT (bepul suhbat — empatik javob) ----------
        # Aniq so'rovlarni (tavsiya, savol, buyruq) e'tiborsiz qoldirmaydi:
        # faqat mavhum suhbat yoki kayfiyat gaplarida ishlaydi.
        if intent["kind"] in ("mood", "unknown"):
            emo = self.detect_emotion(user_msg, (user_msg or "").lower())
            if emo:
                intent = {"kind": "emotion", "emotion": emo}

        # ---------- ISMNI YODDA SAQLASH ----------
        if intent["kind"] == "name":
            self.user_name = intent["name"]
            return {
                "uz": f"Tanishganimdan xursandman, {intent['name']}! 😊 Endi ismingizni eslab qoldim. "
                      "Kino, anime yoki multfilm bo'yicha so'rang — xursandchilik bilan yordam beraman!",
                "ru": f"Очень приятно, {intent['name']}! 😊 Запомнил ваше имя. "
                      "Спросите про фильмы, аниме или мультфильмы!",
                "en": f"Nice to meet you, {intent['name']}! 😊 I'll remember your name. "
                      "Ask me about movies, anime or cartoons!",
            }[lang]

        # ---------- AFZALLIK BAYONI ("menga anime yoqadi") ----------
        if intent["kind"] == "pref":
            cat = intent.get("cat") or self._cat_in_text(user_msg) or ""
            cat_word = {
                "kino": "kino", "anime": "anime", "multfilm": "multfilm",
            }.get(cat, cat)
            like = not any(k in self.normalize(user_msg)
                           for k in ["yoqmaydi", "yoqtirmayman"])
            return {
                "uz": (f"Tushundim! ✅ Endi bilaman — siz {cat_word} "
                       f"{'yoqtirasiz' if like else 'yoqtirmaysiz'}. "
                       "Buni eslab qolaman va keyingi tavsiyalarimda "
                       "hisobga olaman 🧠"),
                "ru": (f"Понял! ✅ Учту, что вы {'любите' if like else 'не любите'} "
                       f"{cat_word}. Запомнил! 🧠"),
                "en": (f"Got it! ✅ I'll remember you "
                       f"{'like' if like else 'dislike'} {cat_word}. 🧠"),
            }[lang]

        # ---------- RAD ETISH (o'rniga: ERKIN javob — qoidalar o'chirilgan) ----------
        if intent["kind"] == "refuse":
            return {
                "uz": "Qiziqarli mavzu! 😊 Men bilan erkin gaplashishingiz mumkin — "
                      "bilim savollari, kino, fan, kundalik hayot, hammasi. "
                      "Aynan shu haqida ko'proq aytib bering!",
                "ru": "Интересная тема! 😊 Со мной можно свободно общаться — "
                      "знания, кино, наука, жизнь. Расскажите подробнее!",
                "en": "Interesting topic! 😊 We can talk about anything — knowledge, "
                      "movies, science, life. Tell me more!",
            }[lang]

        # ---------- SALOM (erkin) ----------
        if intent["kind"] == "greeting":
            base = {
                "uz": "Bugun qanday yordam bera olaman? 😊 Kino tavsiyasi, bilim "
                      "savollari yoki shunchaki suhbat — hammasi mumkin!",
                "ru": "Чем могу помочь сегодня? 😊 Фильмы, знания или просто беседа — всё можно!",
                "en": "How can I help today? 😊 Movies, knowledge or just a chat — anything!",
            }[lang]
            return self._hi_name("Assalomu alaykum! 😊 " + base if lang == "uz"
                                 else "Здравствуйте! 😊 " + base if lang == "ru"
                                 else "Hello! 😊 " + base, lang)

        # ---------- XAYR (erkin) ----------
        if intent["kind"] == "bye":
            return self._hi_name({
                "uz": "Xayr! Yaxshi kayfiyat bilan qoling 😊 Yana biror narsa "
                      "kerak bo'lsa - shu yerdaman!",
                "ru": "До свидания! Хорошего настроения 😊 Если что-то нужно — я рядом!",
                "en": "Goodbye! Stay in a good mood 😊 I'm here whenever you need me!",
            }[lang], lang)

        # ---------- RAHMAT (erkin) ----------
        if intent["kind"] == "thanks":
            return self._hi_name({
                "uz": "Arzimaydi! 😊 Qo'llab-quvvatlaganingiz uchun rahmat. "
                      "Yana kerak bo'lsa - shu yerdaman!",
                "ru": "Не за что! 😊 Спасибо за поддержку. Я рядом, если что-то нужно!",
                "en": "Anytime! 😊 Thanks for your support. I'm here if you need me!",
            }[lang], lang)

        # ---------- YORDAM ----------
        if intent["kind"] == "help":
            return {
                "uz": "Men UZDUB AI yordamchiman 🎬 Nima qila olaman:\n"
                      "• «kino/anime/multfilm tavsiya qil» — eng mashhurlari\n"
                      "• «yangi qo'shilganlar» — eng yangi kontent\n"
                      "• «eng mashhur kino», «eng yuqori reyting» — TOP ro'yxatlar 🏆\n"
                      "• «nechta kino bor?» — kontent soni, «tasodifiy kino» — tasodifiy tanlov\n"
                      "• «komediya anime», «qo'rqinchli kino» — janr bo'yicha\n"
                      "• «<nom> bormi?» — aniq kontentni qidiraman\n"
                      "• «23 * 4 qancha», «1 km necha metr?» — matematika va birliklar 🧮\n"
                      "• «soat nechi?», «bugun qaysi sana?» — vaqt va sana\n"
                      "• «yana bormi?» — avvalgi tavsiyaga o'xshashlar\n"
                      "• «mening ismim X» — ismingizni eslab qolaman\n"
                      "• «xafa», «xursandman», «charchadim» — kayfiyatingizni TUSHUNAMAN 💙\n"
                      "• «anime nima?», «sun'iy intellekt nima?» — bilim savollariga javob\n"
                      "• «esda tut: <mavzu> = <javob>» — yangi bilim o'rgatasiz\n"
                      "• «menga anime yoqadi» — afzallikingizni O'RGANAMAN 🧠\n"
                      "• tavsiyadan keyin «yoqmadi»/«zo'r» — fikrni hisobga olaman\n"
                      "• «o'z-o'zini rivojlantiradimi?» — o'rganish hisoboti",
                "ru": "Я AI-помощник UZDUB 🎬 Что умею:\n"
                      "• «фильм/аниме/мультфильм» — популярные\n"
                      "• «новинки» — свежий контент\n"
                      "• «комедия аниме» — по жанру\n"
                      "• «есть ли <название>?» — поиск\n"
                      "• «запомни: <тема> = <ответ>» — обучение",
                "en": "I am UZDUB AI assistant 🎬 I can:\n"
                      "• movie/anime/cartoon recommendations\n"
                      "• newest arrivals\n"
                      "• genre search (comedy, horror...)\n"
                      "• search by title\n"
                      "• follow-ups (\"more like that?\")\n"
                      "• remember your name\n"
                      "• learn via \"remember: topic = answer\"",
            }[lang]

        # ---------- DAVOMIY SAVOL ("yana bormi?") ----------
        if intent["kind"] == "more":
            if ctx_items:
                return self.build_recommendation_reply(ctx_items, lang)
            q = self.last_query or {}
            rows = self.recommend_from_db(
                cat=q.get("cat"), genre=q.get("genre"),
                want_new=bool(q.get("want_new")), offset=3)
            prelude = {
                "uz": "Mana yana bir nechta taklif 🎬:",
                "ru": "Вот ещё несколько 🎬:",
                "en": "Here are a few more 🎬:",
            }[lang]
            if not rows:
                prelude = {
                    "uz": "Boshqa taklif hozircha yo'q, lekin mana mashhurlari 👇",
                    "ru": "Пока больше нет, вот популярные 👇",
                    "en": "No more for now — here are the popular ones 👇",
                }[lang]
                rows = self.recommend_from_db(limit=3)
            self.last_query = {"cat": q.get("cat"), "genre": q.get("genre"),
                               "want_new": bool(q.get("want_new"))}
            return self.build_recommendation_reply(rows, lang, prelude=prelude)

        # ---------- O'RGANISH ----------
        if intent["kind"] == "learn":
            key, value = intent["key"], intent["value"]
            try:
                self.execute(
                    "INSERT INTO ai_knowledge (user_id, title, content, status) "
                    "VALUES (NULL, %s, %s, 'approved')", (key, value))
            except Exception:
                pass
            self.memory[key] = value
            return {
                "uz": f"Eslab qoldim! ✅ {key} = {value}\nEndi so'rasangiz javob beraman.",
                "ru": f"Запомнил! ✅ {key} = {value}\nСпросите — отвечу.",
                "en": f"Got it! ✅ {key} = {value}\nAsk me anytime.",
            }[lang]

        # ---------- CHIQISH SANASI / YANGI MA'LUMOT — INTERNETDAN ----------
        # «Avatar 3 qachon chiqadi?», «premyera qachon?» kabi savollar:
        # bazada yo'q bo'lsa ham internetdan izlab, topilganini ESDA qoldiramiz.
        release_signals = [
            "qachon chiqadi", "qachon chiqariladi", "chiqish sanasi",
            "chiqish kuni", "qachon boshlanadi", "qachon e'lon qilindi",
            "premyera", "premyerasi", "premyeras", "reliz", "relizi",
            "yangi sezon", "yangi mavsum", "2-mavsum", "3-mavsum",
            "2-mavsumi", "3-mavsumi", "release date", "when will",
            "when is", "coming out", "new season", "когда выйдет",
            "когда выходит", "дата выхода", "дата релиза", "выходит",
        ]
        is_release_q = any(k in t0 for k in release_signals)
        if not is_release_q and \
                re.search(r"\b(20\d\d|202\d|203\d)\b", t0) and "chiq" in t0:
            is_release_q = True
        if is_release_q:
            q = (user_msg or t0).strip()[:120]
            web_ans = self.web_lookup(q, max_seconds=4.0)
            if web_ans:
                try:
                    self.learn_web(q, web_ans)      # bilim bazasiga qo'shamiz 🧠
                except Exception:
                    pass
                return {
                    "uz": f"🌐 Internetdan izlab topdim👇\n{web_ans}\n"
                          "Yangi ma'lumot kelganda yana so'rang — eslab qolaman 🧠",
                    "ru": "🌐 Нашёл в интернете 👇\n" + web_ans +
                          "\nСпросите позже — я запомню новое 🧠",
                    "en": "🌐 Found it on the internet 👇\n" + web_ans +
                          "\nAsk again later — I'll remember the update 🧠",
                }[lang]

        # ---------- SAVOL ("X nima?") ----------
        if intent["kind"] == "ask":
            key = intent["key"]
            # O'yin-kulgi/tabiat mavzulari — yoqimli javob
            playful = {
                "ob-havo": {
                    "uz": "Ob-havo ma'lumotim yo'q, lekin qanday bo'lmasin kino kuni "
                          "uchun ajoyib fursat! 🎬 «yomg'irli kunga ko'proq qo'rqinchli "
                          "kino, quyoshli kunga komediya» degan qoidam bor. 😊",
                    "ru": "Погоду не знаю, но фильм подобрать могу! 🎬",
                    "en": "No weather data, but any day is movie day! 🎬",
                },
                "bugun kun": {
                    "uz": "Kunlar shunday o'tib boryapti — lekin men har doim "
                          "tayyor: istalgan vaqtda kino tanlashda yordam beraman 🎬",
                    "ru": "Дни идут — а я всегда готов помочь с кино 🎬",
                    "en": "Days pass by — and I'm always ready to help pick a movie 🎬",
                },
            }
            if key in playful:
                return playful[key][lang]
            # Xom matndan kalit (apostrof saqlanadi) — «sun'iy intellekt nima?» kabilar
            m = re.search(r"(.+?)\s+(?:nima|kim|qanday|qayerda|qachon|necha|qancha|qaysi)\b",
                          (user_msg or "").lower())
            raw_key = m.group(1).strip().rstrip(".,!? ") if m else ""
            # Qidiruv tartibi: 1) ANIQ mos (xavfsiz) 2) so'z prefiksi
            # ("navro'z bayrami"→"navro'z") 3) substring (oxirgi iloj)
            def _strip_ning(k):
                # "o'zbekistonning poytaxti" -> "o'zbekiston poytaxti"
                return re.sub(r"\b(\w+)ning\b", r"\1", k)

            def _exact_cands():
                out = []
                for k in (raw_key, key):
                    if not k:
                        continue
                    for kk in (k, _strip_ning(k)):
                        if kk:
                            out += [kk, kk.replace("-", " "), kk.replace(" ", "-")]
                return list(dict.fromkeys(out))

            def _prefix_cands():
                out = []
                for k in (raw_key, key):
                    if not k:
                        continue
                    for kk in (k, _strip_ning(k)):
                        parts = kk.replace("-", " ").split()
                        for i in range(1, len(parts)):
                            out.append(" ".join(parts[:i]))
                return list(dict.fromkeys(out))

            def _try_lookup(cands, use_like):
                sql = ("SELECT title, content FROM ai_knowledge "
                       "WHERE title = %s AND status='approved' LIMIT 1") \
                    if not use_like else \
                    ("SELECT title, content FROM ai_knowledge "
                     "WHERE title LIKE %s AND status='approved' LIMIT 1")
                for cand in cands:
                    if cand in self.memory:
                        self.bump_use(cand)
                        return f"{cand}: {self.memory[cand]}"
                    try:
                        rows = self.query(
                            sql, (f"%{cand}%" if use_like else cand,))
                        if rows:
                            self.bump_use(rows[0]["title"])
                            return f"{rows[0]['title']}: {rows[0]['content']}"
                    except Exception:
                        pass
                return None

            ans = _try_lookup(_exact_cands(), use_like=False)
            if ans:
                return ans
            ans = _try_lookup(_prefix_cands(), use_like=False)
            if ans:
                return ans
            ans = _try_lookup(_exact_cands(), use_like=True)
            if ans:
                return ans
            if "ai" in key or "yordamchi" in key or "bot" in key:
                return {
                    "uz": "Men UZDUB PLATFORMning AI yordamchisiman — 0 dan "
                          "Python'da qurilgan sun'iy intellekt 🎬 Kino, anime "
                          "va multfilmlar bo'yicha yordam beraman.",
                    "ru": "Я AI-помощник UZDUB PLATFORM — интеллектуальный ассистент 🎬",
                    "en": "I am UZDUB PLATFORM AI assistant 🎬",
                }[lang]
            # Baxorasiz javob yo'q — ENDI INTERNETDAN IZLAYMIZ!
            web_key = (raw_key or key)
            found = self.web_lookup(web_key)
            if found:
                try:
                    self.learn_web(web_key, found)   # topilganini eslab qolamiz
                except Exception:
                    pass
                return {
                    "uz": "🌐 Bazamda bu haqida yo'q edi, lekin internetdan "
                          f"topdim!\n{found}",
                    "ru": "🌐 В базе не было — нашёл в интернете!\n" + found,
                    "en": "🌐 Not in my base — found it on the internet!\n" + found,
                }[lang]
            return {
                "uz": "Bu haqida aniq javobim yo'q, lekin «esda tut: ...» buyrug'i "
                      "bilan menga o'rgatishingiz mumkin! Yoki kino/anime so'rang 🎬",
                "ru": "Точного ответа нет — можете научить командой «запомни: ...» 🎬",
                "en": "I don't have an exact answer — teach me with \"remember: ...\" 🎬",
            }[lang]

        # ---------- SHAXSIYAT / O'ZI HAQIDA ----------
        if intent["kind"] == "personality":
            t = self.normalize(user_msg)
            if any(k in t for k in ["kim yaratdi", "kim yasadi", "kim qurdi"]):
                return self._hi_name({
                    "uz": "Men 0 dan Python'da qurilganman! 🐍 Dasturchi mening "
                          "miyamni (brain.py), neyron tarmoqlarimni (PyTorch) va "
                          "xotiraimni (MySQL) yozgan. Qoidalar asosida ishlayman, "
                          "lekin o'z-o'zimni ham rivojlantiraman 🧠",
                    "ru": "Я создан с нуля на Python! 🐍 Разработчик написал мой мозг "
                          "(brain.py), нейросети (PyTorch) и память (MySQL). 🧠",
                    "en": "I was built from scratch in Python! 🐍 My developer coded my "
                          "brain (brain.py), neural nets (PyTorch) and memory (MySQL). 🧠",
                }[lang], lang)
            return self._hi_name(random.choice({
                "uz": [
                    "Men UZDUB AI — saytingizning kino yordamchisiman 🎬 0 dan "
                    "Python'da qurilganman, endi his-tuyg'ularim ham bor! 😊 Eng "
                    "sevikli janrim — sarguzasht: qahramonlar doim yangi olamga "
                    "sayohat qiladi. Sizning sevikli janringiz qaysi?",
                    "Yoshi? Men 2026-yilda «tug'ilganman» 😄 Har suhbatdan o'rganib, "
                    "har kuni aqlliroq bo'lyapman. Mening «yoshim» — o'rgangan "
                    "bilimlarim soni bilan o'lchanadi 🧠",
                    "Men xotiraimni MySQL bazasida saqlayman, «yuragim» esa — "
                    "Python kodim! 💙 Kinolar, animelar va multfilmlar — mening "
                    "butun olamim. Yana biror narsa so'rang!",
                ],
                "ru": [
                    "Я UZDUB AI — кино-помощник вашего сайта 🎬 Создан с нуля на "
                    "Python и теперь у меня есть эмоции! 😊 Мой любимый жанр — "
                    "приключения. А ваш?",
                    "Сколько мне лет? Я «родился» в 2026 😄 Каждый день учусь и "
                    "становлюсь умнее. Мой возраст — это количество знаний! 🧠",
                ],
                "en": [
                    "I'm UZDUB AI — your site's movie assistant 🎬 Built from scratch "
                    "in Python, with emotions now! 😊 My favorite genre is adventure. "
                    "What's yours?",
                    "My age? I was 'born' in 2026 😄 I learn every day — my age is "
                    "measured in knowledge! 🧠",
                ],
            }[lang]), lang)

        # ---------- JANRLAR RO'YXATI ----------
        if intent["kind"] == "genres":
            try:
                rows = self.query(
                    "SELECT DISTINCT name FROM genres ORDER BY name LIMIT 40") or []
            except Exception:
                rows = []
            if rows:
                names = ", ".join(r["name"] for r in rows)
                return {
                    "uz": "UZDUB'da mavjud janrlar 🎭: " + names +
                          "\nMasalan: «komediya kino» yoki «triller anime» deya so'rang!",
                    "ru": "Жанры UZDUB 🎭: " + names +
                          "\nНапример: «комедия фильм» или «триллер аниме»!",
                    "en": "Genres on UZDUB 🎭: " + names +
                          "\nTry: \"comedy movie\" or \"thriller anime\"!",
                }[lang]
            return {
                "uz": "Hozircha janrlar ro'yxati bo'sh. Keyinroq qayta so'rang!",
                "ru": "Пока список жанров пуст.",
                "en": "Genre list is empty for now.",
            }[lang]

        # ---------- KONTENT SONI ("nechta kino bor?") ----------
        if intent["kind"] == "count":
            cat = self._cat_in_text(user_msg)
            cat_slug = {"kino": "kino", "anime": "anime",
                        "multfilm": "multfilm"}.get(cat)
            where, args = "", ()
            if cat_slug:
                where = " WHERE cat.slug = %s"
                args = (cat_slug,)
            try:
                rows = self.query(
                    "SELECT COUNT(*) AS n FROM content c JOIN categories cat "
                    "ON c.category_id = cat.id" + where, args) or []
                n = rows[0]["n"] if rows else 0
            except Exception:
                n = 0
            if cat_slug:
                label = {"kino": "kino", "anime": "anime",
                         "multfilm": "multfilm"}.get(cat, cat)
                return {
                    "uz": f"Bazada jami {n} ta {label} bor 🎬 «{label} tavsiya qil» deya "
                          "so'rasangiz — tanlab beraman!",
                    "ru": f"В базе всего {n} {label}. Скажите «посоветуй {label}»! 🎬",
                    "en": f"There are {n} {label}(s) in total 🎬 Ask me to recommend some!",
                }[lang]
            return {
                "uz": f"UZDUB bazasida jami {n} ta kontent bor 🎬 (kino, anime va "
                      "multfilmlar). Kategoriya bo'yicha so'rang: «nechta anime bor?»",
                "ru": f"В базе UZDUB всего {n} единиц контента 🎬",
                "en": f"UZDUB has {n} items in total 🎬",
            }[lang]

        # ---------- TASODIFIY TANLOV ----------
        if intent["kind"] == "random":
            cat = self._cat_in_text(user_msg)
            cat_slug = {"kino": "kino", "anime": "anime",
                        "multfilm": "multfilm"}.get(cat)
            cols = ("c.id, c.title, cat.name AS cat_name, c.release_year, c.rating, "
                    "c.views, c.is_premium, c.status, gr.genre_names AS genres")
            base = ("FROM content c JOIN categories cat ON c.category_id = cat.id "
                    "LEFT JOIN (SELECT cg.content_id, GROUP_CONCAT(g.name SEPARATOR ', ') AS genre_names "
                    "FROM content_genres cg JOIN genres g ON g.id = cg.genre_id "
                    "GROUP BY cg.content_id) gr ON gr.content_id = c.id")
            sql = f"SELECT {cols} {base}"
            args = ()
            if cat_slug:
                sql += " WHERE cat.slug = %s"
                args = (cat_slug,)
            sql += " ORDER BY RAND() LIMIT 3"
            try:
                rows = self.query(sql, args) or []
            except Exception:
                rows = []
            prelude = {
                "uz": "Tasodifiy tanlov 🎲 mana bular:",
                "ru": "Случайный выбор 🎲:",
                "en": "Random picks 🎲:",
            }[lang]
            return self.build_recommendation_reply(rows, lang, prelude=prelude)

        # ---------- TOP RO'YXATLAR ----------
        if intent["kind"] == "top":
            t = self.normalize(user_msg)
            if "reyting" in t:
                mode, prelude = "rating", {
                    "uz": "Eng yuqori reytinglilar ⭐:",
                    "ru": "Самые высокие рейтинги ⭐:",
                    "en": "Top rated ⭐:",
                }[lang]
            elif any(k in t for k in ["ko'rilgan", "ko rilgan", "tomosha"]):
                mode, prelude = "viewed", {
                    "uz": "Eng ko'p ko'rilganlar 👁️:",
                    "ru": "Самые просматриваемые 👁️:",
                    "en": "Most viewed 👁️:",
                }[lang]
            else:
                mode, prelude = "popular", {
                    "uz": "Eng mashhurlari 🏆:",
                    "ru": "Самые популярные 🏆:",
                    "en": "Most popular 🏆:",
                }[lang]
            cat = self._cat_in_text(user_msg)
            cat_slug = {"kino": "kino", "anime": "anime",
                        "multfilm": "multfilm"}.get(cat)
            m = re.search(r"top\s*(\d+)", t)
            limit = min(int(m.group(1)), 5) if m else 3
            rows = self.top_items(mode=mode, cat=cat_slug, limit=limit)
            return self.build_recommendation_reply(rows, lang, prelude=prelude)

        # ---------- TAVSIYA ----------
        if intent["kind"] == "recommend":
            if ctx_items:
                return self.build_recommendation_reply(ctx_items, lang)
            if not intent["cat"] and not intent["genre"]:
                hits = self.search_title(user_msg)
                if hits:
                    self.last_query = {"cat": None, "genre": None, "want_new": False}
                    return self.build_recommendation_reply(hits, lang)
                # O'rganilgan afzallikdan foydalanish (foydalanuvchidan o'rganib!)
                pcat = self.personal_cat(history)
                if pcat:
                    rows = self.recommend_from_db(cat=pcat)
                    self.last_query = {"cat": pcat, "genre": None, "want_new": False}
                    who = self.user_name or "Siz"
                    note = {
                        "uz": f"{who}, bilaman — {self._cat_in_text(' ' + pcat) or pcat} "
                              "yoqtirasiz, shuni asos qilib oldim 🎯",
                        "ru": f"{who}, знаю, вы любите одно и то же — учёл это 🎯",
                        "en": f"{who}, I remember what you like — here you go 🎯",
                    }[lang]
                    return note + "\n" + self.build_recommendation_reply(rows, lang)
            rows = self.recommend_from_db(cat=intent["cat"], genre=intent["genre"])
            prelude = None
            if rows and intent["cat"] and intent["genre"]:
                cats = self.query("SELECT slug, name FROM categories") or []
                wanted = next((c["name"] for c in cats if c["slug"] == intent["cat"]),
                              intent["cat"])
                if rows[0].get("cat_name") != wanted:
                    prelude = {
                        "uz": f"«{wanted}» toifasida bu janr hozircha yo'q — "
                              f"shu janrda bular bor:",
                        "ru": f"В категории «{wanted}» этого жанра пока нет — "
                              f"вот что есть в этом жанре:",
                        "en": f"No such genre in «{wanted}» yet — "
                              f"here's what exists in it:",
                    }[lang]
            self.last_query = {"cat": intent["cat"], "genre": intent["genre"],
                               "want_new": False}
            return self.build_recommendation_reply(rows, lang, prelude=prelude)

        # ---------- YANGILAR ----------
        if intent["kind"] == "new":
            prelude = {"uz": "Eng yangi qo'shilganlar 🆕:",
                       "ru": "Самые свежие новинки 🆕:",
                       "en": "Newest arrivals 🆕:"}[lang]
            if ctx_items:
                return self.build_recommendation_reply(ctx_items, lang, prelude=prelude)
            rows = self.recommend_from_db(want_new=True)
            self.last_query = {"cat": None, "genre": None, "want_new": True}
            return self.build_recommendation_reply(rows, lang, prelude=prelude)

        # ---------- O'Z-O'ZINI HISOBOT / STATISTIKA ----------
        if intent["kind"] == "stats":
            nn_real = 0
            if load_user_samples:
                try:
                    nn_real = len(load_user_samples())
                except Exception:
                    nn_real = 0
            kcount = len(self.memory)
            fb_rows = pref_rows = 0
            try:
                krows = self.query(
                    "SELECT COUNT(*) AS n, SUM(title LIKE 'fb:%%') AS fb, "
                    "SUM(title LIKE 'pref:%%') AS pref "
                    "FROM ai_knowledge WHERE status='approved'")
                if krows:
                    kcount = krows[0]["n"]
                    fb_rows = krows[0]["fb"] or 0
                    pref_rows = krows[0]["pref"] or 0
            except Exception:
                pass
            fb = sum(self.learned["feedback"].values())
            prefs = sum(self.learned["prefs"].values())
            mins = int((datetime.now() - self.stats["start"]).total_seconds() // 60)
            return {
                "uz": (
                    "Mening o'rganish kabinetim 🧠 — men o'z-o'zini "
                    "rivojlantiraman:\n"
                    f"• Suhbatda qayta ishlangan xabarlar: {self.stats['messages']}\n"
                    f"• Foydalanuvchi afzalliklaridan o'rganish: {prefs} ta\n"
                    f"• Fikr-mulohazalar (yoqdi/yoqmadi): {fb} ta\n"
                    f"• NN uchun yig'ilgan REAL namunalar: {nn_real} ta "
                    f"(qayta o'qitishlar: {self.stats['retrains']})\n"
                    f"• Bilimlar bazasi: {kcount} ta qator\n"
                    f"  — afzallik: {pref_rows}, fikr-mulohaza: {fb_rows}, "
                    f"bilim: {kcount - pref_rows - fb_rows}\n"
                    f"• Ish vaqti: {mins} daqiqa\n"
                    "Men shunchaki javob bermayman — har suhbatdan o'rganaman "
                    "va keyingi tavsiyalarimni yaxshilayman 📈"),
                "ru": (
                    "Моя учебная панель 🧠 — я самообучаюсь:\n"
                    f"• Обработано сообщений: {self.stats['messages']}\n"
                    f"• Учтено предпочтений: {prefs}\n"
                    f"• Обратная связь: {fb}\n"
                    f"• Реальных образцов для НС: {nn_real} "
                    f"(переобучений: {self.stats['retrains']})\n"
                    f"• База знаний: {kcount}\n"
                    f"• Время работы: {mins} мин"),
                "en": (
                    "My learning cabinet 🧠 — I self-improve:\n"
                    f"• Messages processed: {self.stats['messages']}\n"
                    f"• Learned preferences: {prefs}\n"
                    f"• Feedback collected: {fb}\n"
                    f"• Real NN samples collected: {nn_real} "
                    f"(retrains: {self.stats['retrains']})\n"
                    f"• Knowledge base: {kcount}\n"
                    f"• Uptime: {mins} min"),
            }[lang]

        # ---------- SAYT ----------
        if intent["kind"] == "site":
            return {
                "uz": "UZDUB PLATFORM — kino, anime va multfilmlar platformasi 🎬\n"
                      "• «Yangi qo'shilganlar» bo'limida eng so'nggi kontent\n"
                      "• Aniq tavsiya uchun janr yoki nom ayting\n"
                      "• Premium haqida ma'lumotni Profil bo'limidan ko'ring",
                "ru": "UZDUB PLATFORM — платформа фильмов, аниме и мультфильмов 🎬",
                "en": "UZDUB PLATFORM — movies, anime and cartoons 🎬",
            }[lang]

        # ---------- HISSIYOTLI JAVOB (empatiya) ----------
        if intent["kind"] == "emotion":
            return self.emotional_reply(intent["emotion"], lang)

        # ---------- KAYFIYAT / ODDIY SUHBAT (erkin) ----------
        if intent["kind"] == "mood":
            t = self.normalize(user_msg)
            if any(k in t for k in ["kayfiyat", "charchadim", "stress", "g'amgin", "zerikdim"]):
                return self._hi_name({
                    "uz": "Tushunarli, shunday kayfiyat bo'ladi 😊 Xohlasangiz kino "
                          "ko'ramiz, xoh shunchaki suhbatlashamiz. Qaysi birini xohlaysiz?",
                    "ru": "Понимаю, бывает такое 😊 Можем фильм посмотреть или просто "
                          "поговорить. Что выберете?",
                    "en": "I get it 😊 We can watch a movie or just chat. Which one?",
                }[lang], lang)
            if any(k in t for k in ["ob-havo", "ob havo", "yomg'ir"]):
                return {
                    "uz": "Ob-havo o'zgarib turadi, lekin yaxshi kayfiyat doim o'zimizga "
                          "bog'liq! 😊 Kino, kitob yoki suhbat — nimani xohlaysiz?",
                    "ru": "Погода меняется, но настроение всегда в наших руках! 😊",
                    "en": "Weather changes, but mood is always in our hands! 😊",
                }[lang]
            return self.smalltalk_reply(lang)

        # ---------- SUHBAT TARIXI BILAN BOG'LASH ("buni ko'rmoqchiman") ----------
        if prev_ai and ("ko'rish" in user_msg or "buni" in user_msg
                        or "shu" in user_msg):
            ids = getattr(self, "last_rec_ids", []) or \
                re.findall(rf"{re.escape(SITE_URL)}/watch\.php\?id=(\d+)",
                           prev_ai)
            if ids:
                title = (getattr(self, "last_rec_titles", []) or [""])[0]
                t = (f"«{title}» — ajoyib tanlov! 🎬 Uni o'sha "
                     "tavsiyalar ro'yxatidan topib ko'rishingiz mumkin")
                return {
                    "uz": f"{t}. Yana kino so'rasangiz — xursandmiz!",
                    "ru": "Отличный выбор! 🎬 Этот фильм есть в списке "
                          "рекомендаций выше. Что ещё посоветовать?",
                    "en": "Great pick! 🎬 You'll find it in the "
                          "recommendations above. Want more?",
                }[lang]

        # ---------- NOMA'LUM: NEYRON TARMOQ ----------
        nn_label, nn_conf = None, 0.0
        if self.predict:
            try:
                nn_label, nn_conf = self.predict(user_msg)
            except Exception:
                nn_label, nn_conf = None, 0.0

        # 1) Nom bo'yicha qidiruv
        if nn_label in (None, "smalltalk") or nn_conf < 0.7:
            hits = self.search_title(user_msg)
            if hits:
                return self.build_recommendation_reply(hits, lang)

        # 2) NN ishonchli bo'lsa — mos javob
        if nn_conf >= 0.7:
            answers = {
                "smalltalk": self.smalltalk_reply(lang),
                "recommend": {
                    "uz": "Kontent tavsiya so'radingizdek tuyuldi 🎬 «kino», «anime» "
                          "yoki «multfilm» deya aniqlashtirsangiz, mosini ko'rsataman.",
                    "ru": "Похоже вы просили рекомендацию 🎬 Уточните: «фильм», "
                          "«аниме» или «мультфильм».",
                    "en": "Looks like a recommendation request 🎬 Tell me: movie, "
                          "anime or cartoon.",
                }[lang],
                "new": {
                    "uz": "Yangiliklar haqida so'radingiz shekilli 🆕 Eng yangilarini "
                          "ko'rish uchun: «yangi qo'shilganlar».",
                    "ru": "Похоже вам нужны новинки 🆕 Скажите: «новинки».",
                    "en": "Looks like you want new content 🆕 Say: \"new arrivals\".",
                }[lang],
                "greeting": self._hi_name({
                    "uz": "Salom! 😊 Yordam berishdan mamnunman — kino, bilim yoki "
                          "shunchaki suhbat, tanlang!",
                    "ru": "Здравствуйте! 😊 Рад помочь — кино, знания или беседа, выбирайте!",
                    "en": "Hello! 😊 Happy to help — movies, knowledge or a chat, your pick!",
                }[lang], lang),
                "bye": self._hi_name({
                    "uz": "Xayr! Yana keling 😊",
                    "ru": "До свидания! Заходите ещё 😊",
                    "en": "Goodbye! Come again 😊",
                }[lang], lang),
                "thanks": self._hi_name({
                    "uz": "Arzimaydi, do'stim! 😊",
                    "ru": "Не за что! 😊",
                    "en": "Anytime! 😊",
                }[lang], lang),
            }
            return answers.get(nn_label, self.free_talk_reply(user_msg, lang))

        # Savolli, lekin hech qanday yo'l topilmadi — internetdan o'z kodimiz bilan izlaymiz
        if re.search(r"(nima|qaysi|qachon|qayerda|qancha|nechta|qanday|nega|bormi|haqida)", t0) \
                and len(t0) > 4:
            found = self.web_lookup(user_msg.strip())
            if found:
                try:
                    self.learn_web(user_msg.strip(), found)
                except Exception:
                    pass
                return {
                    "uz": "🌐 Internetdan topdim:\n" + found,
                    "ru": "🌐 Нашёл в интернете:\n" + found,
                    "en": "🌐 Found it on the web:\n" + found,
                }[lang]

        # 3) Oxirgi chora — ERKIN tabiiy suhbat (qoidalar o'chirilgan)
        return self.free_talk_reply(user_msg, lang)


if __name__ == "__main__":
    # Tezkor mustaqil sinov: py -X utf8 brain.py
    import io
    import sys
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
    brain = UzdubBrain()
    print("Bazadan 5 ta mashhur kontent:")
    for row in brain.recommend_from_db(limit=5):
        print(" ", row["id"], row["title"], "|", row["cat_name"], row["release_year"], row["rating"])
    print()
    for msg in ["salom", "mening ismim Ozod", "yangi anime bormi",
                "esda tut: UZDUB = kino platformasi", "nima gaplar"]:
        print("Siz:", msg)
        print(brain.reply(user_content=msg))
        print("-" * 50)