# -*- coding: utf-8 -*-
"""
UZDUB AI SERVER — LOKAL NEYRON TARMOQ (OLLAMA) + SUHBAT XOTIRASI + OVOZLI YORDAMCHI
=================================================================================
Uchta katta muammo hal qilindi:
  1. XOTIRA: har bir foydalanuvchi (session_id) uchun suhbat tarixi
     saqlanadi — system prompt + oxirgi 10-15 xabar (MAX_HISTORY).
  2. LOKAL AI: kompyuterda o'rnatilgan Ollama (Qwen3:8b — 0-dan emas,
     ochiq manbali neyron tarmoq) bilan `requests` orqali ishlaydi —
     API kalit YO'Q, to'liq OFLAYN, erkin fikrlaydi. joylashuvi:
     Ollama   -> http://127.0.0.1:11435  (OLLAMA_HOST shu portda)
     bu server -> 11434 (ovozli sahifa aynan shu manzilga ulanadi)
  3. OVOZ: javoblar qisqa, me'yoriy o'zbek tilida bo'ladi
     (system prompt — ovozli o'qishga mos), /api/tts esa Edge
     neyron o'zbek ovozi bilan aytadi.

Ishga tushirish:
  • Ollama o'rnatilgan bo'lsa: setx OLLAMA_HOST 127.0.0.1:11435  (bir marta)
      keyin `ollama serve` (yoki Ollama dasturini ishga tushirish)
  • bu serverni ishga tushirish:       py -X utf8 ai_server.py       (11434)
  • Ollama yo'q bo'lsa: brain.py qoidalari + (internet bo'lsa) OpenRouter
      bepul LLM zaxira bo'lib xatosiz javob beradi (to'liq UTF-8).

Qo'llab-quvvatlanadigan interfeyslar:
  POST /api/chat            — Ollama native (stream: true/false)
  GET  /api/history         — sessiya tarixini olish
  POST /api/history/clear   — sessiya tarixini tozalash
  POST /api/tts             — matnni o'zbek neyron ovozga aylantirish
  GET  /api/tags, /api/version, /health — Ollama-mos
"""
import json
import os
import re
import threading
import time
from datetime import datetime, timezone

from flask import Flask, Response, request, jsonify

try:
    import requests as _requests          # Ollama'ga so'rovlar uchun
except ImportError:
    _requests = None

from brain import UzdubBrain

app = Flask(__name__)
brain = UzdubBrain()          # 0-dan qurilgan AI miya (zaxira va stats uchun)

PORT = int(os.environ.get("PORT", "11434"))
HOST = os.environ.get("HOST", "127.0.0.1")
MODEL_NAME = os.environ.get("OLLAMA_MODEL", "qwen3:8b")
OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://127.0.0.1:11435/api/chat")

# ============================================================
# SYSTEM PROMPT — ovozli yordamchi uchun
# Javoblar qisqa, me'yoriy o'zbek tilida, ovozli o'qishga mos.
# ============================================================
SYSTEM_PROMPT = (
    "Siz UZDUB PLATFORM'ning muloyim, xushmuomala, samimiy va bilimli "
    "ovozli yordamchisisiz. Foydalanuvchi bilan O'ZBEK tilida erkin va "
    "tabiiy suhbatlashesiz — huddi aqlli do'stingizdek. "
    "Har doim me'yoriy (adabiy) o'zbek tilida, qisqa va tushunarli "
    "javob bering: 1–4 jumla (javob ovozli o'qiladi). Emoji va ortiqcha "
    "belgilarga berilmang. Foydalanuvchi kino, anime yoki multfilm "
    "so'rasa — mashhur va yaxshi tanilgan asarlarni tavsiya qiling; "
    "boshqa mavzuda savol bersa — ishonchli va bilimdon javob bering; "
    "savol noaniq bo'lsa — qarshi savol bering va suhbatni davom ettiring. "
    "Suhbat tarixini eslab qoling — foydalanuvchining ismini, didini, "
    "aytganlarini keyingi javoblarda ishlating."
)

MAX_HISTORY = 15             # xotira: system promptdan keyin shuncha xabar
SESSION_TTL = 60 * 60 * 2    # sessiya 2 soatdan keyin avtomatik tozalanadi

# ============================================================
# HAQIQIY AI (Cloud LLM) — saytning .env kalitlari bilan
# UZDUB sayti Groq/Cerebras/... dan ishlaydi; ovozli AI ham
# o'sha provayderlardan foydalanadi. Kalitlar kodda YO'Q —
# faqat saytning .env faylidan runtime'da o'qiladi.
# ============================================================
def _load_dotenv_keys():
    """Saytning .env faylidan API kalitlarini o'qiydi (faqat Python uchun)."""
    keys = {}
    for path in (r"C:\xampp\htdocs\uzdub\.env",
                 os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")):
        try:
            with open(path, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith("#") or "=" not in line:
                        continue
                    k, v = line.split("=", 1)
                    keys[k.strip()] = v.strip().strip('"').strip("'")
        except Exception:
            pass
    return keys


_env_keys = _load_dotenv_keys()

CLOUD_PROVIDERS = []          # ishlaydigan provayderlar ro'yxati


def _add_provider(name, url, models, key_env):
    """Kalit bor bo'lsa provayderni qo'shadi. Har provayderda bir nechta
    model bo'lishi mumkin — birinchi ishlagani olinadi."""
    key = _env_keys.get(key_env, "")
    if len(key) > 3:
        CLOUD_PROVIDERS.append({"name": name, "url": url,
                                "key": key, "models": list(models)})


# Tartib: BIRINCHI bo'lib ishlaydigan provayder sinanadi.
# 2026-09 sinov natijasi: Groq/Cerebras/Together kalitlari 403 (bloklangan),
# SambaNova 429 (limit) — faqat OpenRouter bepul modellari ishlayapti
# (nvidia/nemotron-x:free tasdiqlandi). Shuning uchun OpenRouter BIRINCHI.
# UZDUB_CLOUD=1 bo'lganda kino/anime/multfilm so'rovlari baribir brain.py
# (REAL sayt bazasi) orqali o'tadi — LLM uydirma kontent bermaydi.
_add_provider("openrouter", "https://openrouter.ai/api/v1/chat/completions",
              ["nvidia/nemotron-3-super-120b-a12b:free",   # ~1.2s, 120B — asosiy
               "google/gemma-4-26b-a4b-it:free",           # ~1.1s zaxira
               "openrouter/free",                           # istalgan bepulga yo'naltiradi
               "nvidia/nemotron-3-ultra-550b-a55b:free"],   # ~9s, sifatli zaxira
              "OPENROUTER_API_KEY")
# Quyidagilar zaxira — bir kun kalit yangilansa, avtomatik ishga tushadi:
_add_provider("groq", "https://api.groq.com/openai/v1/chat/completions",
              ["qwen/qwen3.8-27b", "llama-3.3-70b-versatile"],
              "GROQ_API_KEY")
_add_provider("cerebras", "https://api.cerebras.ai/v1/chat/completions",
              ["qwen-3.8-27b", "llama-3.3-70b"], "CEREBRAS_API_KEY")
_add_provider("sambanova", "https://api.sambanova.ai/v1/chat/completions",
              ["DeepSeek-V3.2", "DeepSeek-V3.1",
               "Meta-Llama-3.3-70B-Instruct"], "SAMBANOVA_API_KEY")
_add_provider("together", "https://api.together.xyz/v1/chat/completions",
              ["meta-llama/Llama-3.3-70B-Instruct-Turbo"], "TOGETHER_API_KEY")

_working_cloud = None         # ishlagan "provayder|model" (tezlik uchun xotirada)

# Cloud AI (haqiqiy LLM — OpenRouter bepul modellari) — STANDART YOQIQ.
# Yoqish kerak bo'lsa:  UZDUB_CLOUD=1  py -X utf8 ai_server.py
# (kino/anime/multfilm so'rovlari baribir sayt bazasidan — xavfsiz)
_USE_CLOUD = os.environ.get("UZDUB_CLOUD", "0") == "1"

# ============================================================
# SUHBAT XOTIRASI (har bir foydalanuvchi uchun)
# ============================================================
_sessions = {}               # session_id -> {"history": [...], "last": ts}
_lock = threading.Lock()


def _cleanup_sessions():
    """Muddati o'tgan sessiyalarni xotiradan tozalaydi."""
    now = time.time()
    with _lock:
        for k in [k for k, v in _sessions.items()
                  if now - v["last"] > SESSION_TTL]:
            _sessions.pop(k, None)


def get_session(sid):
    """Sessiya tarixini qaytaradi (yo'q bo'lsa yangisini yaratadi)."""
    _cleanup_sessions()
    sid = str(sid or "default")
    with _lock:
        s = _sessions.get(sid)
        if not s:
            s = {"history": [], "last": time.time()}
            _sessions[sid] = s
        s["last"] = time.time()
        return s


def clear_session(sid):
    """Foydalanuvchi suhbat tarixini tozalaydi."""
    with _lock:
        _sessions.pop(str(sid or "default"), None)


# ============================================================
# JAVOB YARATISH: sayt kontenti -> haqiqiy AI -> zaxira brain.py
# ============================================================
# DB kontentga bog'liq niyatlar — REAL sayt kontentidan javob
# (LLM uydirma kino nomlari bermasligi uchun).
_DB_INTENTS = {"recommend", "top", "new", "more", "count",
               "genres", "random"}

_snap_cache = {"ts": 0.0, "text": ""}


def _site_snapshot():
    """Saytning haqiqiy kontent ro'yxati — LLM kontekstiga qo'shiladi.
    Xotirada 10 daqiqa turadi; MySQL o'chiganda bo'sh qaytadi (xato emas)."""
    if time.time() - _snap_cache["ts"] < 600 and _snap_cache["text"]:
        return _snap_cache["text"]
    lines = []
    try:
        rows = brain.query(
            "SELECT cat.slug AS slug, COUNT(*) AS n FROM content c "
            "JOIN categories cat ON c.category_id = cat.id "
            "GROUP BY cat.slug") or []
        counts = {r["slug"]: r["n"] for r in rows}
        cnt = ", ".join("%s: %s ta" % (k, counts[k])
                        for k in ("kino", "anime", "multfilm") if k in counts)
        if cnt:
            lines.append("SAYTDA MAVJUD: " + cnt + ".")

        g = brain.query("SELECT name FROM genres ORDER BY name") or []
        if g:
            lines.append("Janrlar: " + ", ".join(r["name"] for r in g) + ".")

        top = []
        for cat in ("kino", "anime", "multfilm"):
            for it in brain.top_items(mode="popular", cat=cat, limit=3) or []:
                yr = it.get("release_year") or it.get("year") or "?"
                rt = it.get("rating")
                top.append("• %s (%s, %s%s)" % (
                    it["title"], yr, it.get("cat_name", cat),
                    (", ★%s" % rt) if rt else ""))
        if top:
            lines.append("MASHHUR KONTENT:")
            lines.extend(top[:9])

        newr = brain.recommend_from_db(cat=None, want_new=True, limit=5) or []
        new_lines = ["• %s (%s, %s)" % (
            it["title"], it.get("release_year") or "?",
            it.get("cat_name", "")) for it in newr]
        if new_lines:
            lines.append("YANGI QO'SHILGANLAR:")
            lines.extend(new_lines[:5])
    except Exception:
        pass
    text = "\n".join(lines)
    _snap_cache.update(ts=time.time(), text=text)
    return text


def _system_with_context(system):
    """Asosiy system promptga sayt kontenti qo'shiladi (MYTH yo'q: LLM
    faqat haqiqiy nomlardan tavsiya beradi)."""
    snap = _site_snapshot()
    if not snap:
        return system
    return (system +
            "\n\n=== UZDUB SAYTIDAGI HAQIQIY KONTENT ===\n" + snap +
            "\nFoydalanuvchi kino/anime/multfilm so'rasa — faqat yuqoridagi "
            "haqiqiy nomlardan yoki sayt bazasidagi kontentdan tavsiya bering. "
            "Uydirma (mavjud emas) kino/anime nomini hech qachon bermang.")


def _self_port_in_ollama_url():
    """Ollama URL biz o'zimiz turgan portga qarasa — o'zimizga so'rov
    yubormaymiz (aks holda cheksiz sikllanadi)."""
    try:
        from urllib.parse import urlparse
        return str(urlparse(OLLAMA_URL).port) == str(PORT)
    except Exception:
        return True


def _call_cloud(system, prev_msgs, user_text):
    """Haqiqiy AI: Groq -> Cerebras -> SambaNova -> Together -> OpenRouter.

    Saytning .env kalitlari bilan OpenAI-mos chat/completions API'ga
    so'rov yuboradi. Har provayderda bir nechta model sinaladi; biror
    kombigatsiya ishlasa — uning javobini qaytaradi; hammasi ishlamasa
    — None (keyingi zanjirga o'tamiz).
    """
    global _working_cloud
    if not CLOUD_PROVIDERS:
        return None
    msgs = ([{"role": "system", "content": system}] +
            list(prev_msgs)[-8:] +               # token tejash: so'nggi 8 xabar
            [{"role": "user", "content": user_text}])

    # Ishlagan "provayder|model" juftligini birinchi o'ringa qo'yamiz
    first = None
    if _working_cloud:
        name, _, model = _working_cloud.partition("|")
        for p in CLOUD_PROVIDERS:
            if p["name"] == name and model in p["models"]:
                first = (p, model)
                break

    seen, order = set(), []
    for item in ([first] if first else []) + \
                 [(p, m) for p in CLOUD_PROVIDERS for m in p["models"]]:
        if item is None:
            continue
        tag = item[0]["name"] + "|" + item[1]
        if tag in seen:
            continue
        seen.add(tag)
        order.append(item)

    for p, model in order:
        try:
            payload = {"model": model, "messages": msgs,
                       "temperature": 0.7, "max_tokens": 700,
                       "stream": False}
            if p["name"] == "openrouter":
                # OpenRouter: reasoning izini O'CHIRADI — javob faqat yakuniy
                # matndan iborat bo'ladi (aks holda model CoT izini chiqaradi).
                payload["reasoning"] = {"enabled": False}
            resp = _requests.post(
                p["url"],
                json=payload,
                headers={"Content-Type": "application/json",
                         "Authorization": "Bearer " + p["key"]},
                timeout=60)
            if resp.status_code == 200:
                data = resp.json()
                content = ((data.get("choices") or [{}])[0]
                           .get("message", {}).get("content", ""))
                if content and content.strip():
                    _working_cloud = p["name"] + "|" + model
                    return content.strip()
        except Exception:
            continue                        # xatosiz: keyingi provayder
    _working_cloud = None
    return None


def _needs_site_content(user_text):
    """Kino/anime/multfilm so'rovlari — REAL sayt kontentidan javob beriladi."""
    try:
        return brain.detect_intent(user_text).get("kind") in _DB_INTENTS
    except Exception:
        return False


def _needs_web_fresh(user_text):
    """«Qachon chiqadi / premiera / release date» kabi vaqtga bog'liq
    savollar — LLM uydirma sana aytmasligi uchun brain.py (internet
    qidiruvi + o'rganish) ishga tushiriladi."""
    try:
        return bool(re.search(
            r"qachon chiqadi|qachon chiqariladi|chiqish sanasi|chiqish kuni|"
            r"premyera|reliz|release date|когда выйдет|когда выходит|"
            r"дата выхода|when (will|is)|coming out|new season|"
            r"2-mavsum|3-mavsum|yangi mavsum",
            (user_text or "").lower()))
    except Exception:
        return False


def _generate(system, prev_msgs, user_text):
    """Javob matnini oladi.

    Zanjir:
      1) Sayt kontenti so'rovi (tavsiya/top/yangi/soni/janrlar...) →
         brain.py — REAL sayt bazasidagi kontent + watch.php havolalar.
      2) Haqiqiy AI (Groq/Cerebras/...) → real Ollama (sayt kontenti
         kontekstda — uydirma nom bermaydi).
      3) brain.py zaxira — oflayn ham xatosiz ishlaydi.
    """
    # 1) REAL SAYT KONTENTI + vaqtga bog'liq (internet) savollar
    if _needs_site_content(user_text) or _needs_web_fresh(user_text):
        return brain.reply(system_prompt=system,
                           user_content=user_text,
                           history=prev_msgs).strip()

    # LLM konteksti: system prompt + sayt kontenti
    ctx_system = _system_with_context(system)

    # 2) HAQIQIY AI (cloud LLM) — faqat UZDUB_CLOUD=1 bo'lsa (standart O'CHIQ)
    if _USE_CLOUD and _requests is not None:
        txt = _call_cloud(ctx_system, prev_msgs, user_text)
        if txt:
            return txt

    # 3) Real Ollama (agar alohida portda o'rnatilgan bo'lsa)
    if not _self_port_in_ollama_url() and _requests is not None:
        try:
            msgs = [{"role": "system", "content": ctx_system}] + \
                   list(prev_msgs) + [{"role": "user", "content": user_text}]
            resp = _requests.post(
                OLLAMA_URL,
                json={"model": MODEL_NAME, "stream": False,
                      "messages": msgs,
                      "think": False,           # Qwen3: faqat yakuniy javob
                      "options": {"temperature": 0.7,
                                  "num_predict": 320}},
                timeout=90)
            if resp.status_code == 200:
                data = resp.json()
                return ((data.get("message") or {}).get("content") or "").strip()
        except Exception:
            pass                             # xatosiz: brain.py ga o'tamiz

    # 4) ZAXIRA: qoidaga asoslangan brain.py (oflayn ishonchli)
    return brain.reply(system_prompt=system,
                       user_content=user_text,
                       history=prev_msgs).strip()


def _ts():
    """Ollama mos vaqt formati."""
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "Z"


def _chunk_text(text, size=14):
    """Javobni kichik bo'laklarga bo'lib, real oqim (stream) hissi beradi."""
    if not text:
        yield ""
        return
    for i in range(0, len(text), size):
        yield text[i:i + size]


# ============================================================
# CORS — ovozli sahifa serverga to'g'ridan-to'g'ri ulanadi
# ============================================================
@app.after_request
def _cors(resp):
    resp.headers.setdefault("Access-Control-Allow-Origin", "*")
    resp.headers.setdefault("Access-Control-Allow-Methods",
                            "GET, POST, OPTIONS")
    resp.headers.setdefault("Access-Control-Allow-Headers",
                            "Content-Type, Authorization, X-CSRF-Token")
    return resp


@app.route("/api/chat", methods=["OPTIONS"])
def _cors_preflight():
    return Response("", status=204)


# ============================================================
# ASOSIY CHAT ENDPOINTI (xotira bilan)
# ============================================================
@app.post("/api/chat")
def api_chat():
    """Ollama'ning native /api/chat interfeysi — xotira (chat history)
    bilan. Body: {model, messages, session_id, stream, system}

    messages to'liq tarix bo'lsa — sessiya tarixini almashtiradi;
    bitta yangi xabar bo'lsa — sessiya tarixiga qo'shiladi.
    """
    data = request.get_json(silent=True) or {}
    stream = bool(data.get("stream", False))
    model = data.get("model") or MODEL_NAME
    sid = data.get("session_id") or data.get("sessionId") or "default"
    messages = data.get("messages") or []
    system = data.get("system") or SYSTEM_PROMPT

    incoming = [{"role": m.get("role", ""),
                 "content": m.get("content", "").strip()}
                for m in messages
                if m.get("role") in ("user", "assistant") and m.get("content")]

    # --- XOTIRA: sessiya tarixini yangilash ---
    s = get_session(sid)
    if len(incoming) > 1:
        # Klient butun tarixni yubordi (sayt) -> almashtiramiz
        s["history"] = incoming[-MAX_HISTORY:]
    elif incoming:
        # Ovozli sahifa: bitta yangi xabar -> qo'shamiz
        s["history"] = (s["history"] + incoming)[-MAX_HISTORY:]

    # Joriy (oxirgi) foydalanuvchi savoli
    if s["history"] and s["history"][-1].get("role") == "user":
        user_text = s["history"][-1]["content"]
    else:
        user_text = incoming[-1]["content"] if incoming else ""
        s["history"].append({"role": "user", "content": user_text})
        s["history"] = s["history"][-MAX_HISTORY:]

    prev_msgs = s["history"][:-1]     # brain uchun: joriy xabarsiz

    try:
        reply = _generate(system, prev_msgs, user_text)
    except Exception as e:
        return jsonify({"error": f"AI ichki xatolik: {e}"}), 500

    # Javobni ham xotiraga yozamiz
    s["history"].append({"role": "assistant", "content": reply})
    s["history"] = s["history"][-MAX_HISTORY:]

    if stream:
        def generate():
            for piece in _chunk_text(reply):
                yield json.dumps({
                    "model": model,
                    "created_at": _ts(),
                    "message": {"role": "assistant", "content": piece},
                    "done": False,
                }, ensure_ascii=False) + "\n"
            yield json.dumps({
                "model": model,
                "created_at": _ts(),
                "message": {"role": "assistant", "content": ""},
                "done": True,
                "done_reason": "stop",
                "total_duration": 0,
                "load_duration": 0,
                "prompt_eval_count": 0,
                "eval_count": 0,
            }, ensure_ascii=False) + "\n"

        return Response(generate(), mimetype="application/x-ndjson")

    return jsonify({
        "model": model,
        "created_at": _ts(),
        "message": {"role": "assistant", "content": reply},
        "done": True,
        "done_reason": "stop",
        "total_duration": 0,
        "load_duration": 0,
        "prompt_eval_count": 0,
        "eval_count": 0,
    })


# ============================================================
# TARIXNI O'QISH / TOZALASH
# ============================================================
@app.get("/api/history")
def api_history():
    """Sessiya suhbat tarixini qaytaradi."""
    sid = request.args.get("session_id") or request.args.get("sessionId") or "default"
    s = get_session(sid)
    return jsonify({"session_id": str(sid), "messages": s["history"],
                    "max": MAX_HISTORY})


@app.post("/api/history/clear")
def api_history_clear():
    """Foydalanuvchi suhbat tarixini tozalaydi."""
    data = request.get_json(silent=True) or {}
    sid = data.get("session_id") or data.get("sessionId") or "default"
    clear_session(sid)
    return jsonify({"ok": True, "session_id": str(sid)})


# ============================================================
# OVOZLI JAVOB (TTS) — Edge neyron o'zbek ovozi
# ============================================================
TTS_VOICES = {
    "uz-UZ-MadinaNeural": "O'zbek — Madina (ayol)",
    "uz-UZ-SardorNeural": "O'zbek — Sardor (erkak)",
}

# --- TTS kesh: bir xil matn qayta so'ralsa — darhol qaytaramiz (prefetch ishlashi uchun) ---
_TTS_CACHE = {}
_TTS_CACHE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "tts_cache")
try:
    os.makedirs(_TTS_CACHE_DIR, exist_ok=True)
except OSError:
    pass


def _tts_cache_key(voice, text, rate):
    import hashlib
    return hashlib.md5((voice + "|" + rate + "|" + text).encode("utf-8")).hexdigest()


def _tts_cache_get(voice, text, rate):
    key = _tts_cache_key(voice, text, rate)
    data = _TTS_CACHE.get(key)
    if data is not None:
        return data
    path = os.path.join(_TTS_CACHE_DIR, key + ".mp3")
    try:
        with open(path, "rb") as f:
            data = f.read()
        _TTS_CACHE[key] = data
        return data
    except OSError:
        return None


def _tts_cache_set(voice, text, rate, data):
    key = _tts_cache_key(voice, text, rate)
    _TTS_CACHE[key] = data
    try:
        with open(os.path.join(_TTS_CACHE_DIR, key + ".mp3"), "wb") as f:
            f.write(data)
    except OSError:
        pass


@app.route("/api/tts", methods=["GET", "POST"])
def api_tts():
    """Matnni o'zbekcha neyron ovozga (MP3) aylantiradi."""
    import asyncio
    import io

    if request.method == "POST":
        data = request.get_json(silent=True) or {}
        text = (data.get("text") or "").strip()
        voice = data.get("voice") or "uz-UZ-MadinaNeural"
        rate = data.get("rate") or "+0%"
    else:
        text = (request.args.get("text") or "").strip()
        voice = request.args.get("voice") or "uz-UZ-MadinaNeural"
        rate = request.args.get("rate") or "+0%"

    if not text:
        return jsonify({"error": "text parametri kerak"}), 400
    if voice not in TTS_VOICES:
        voice = "uz-UZ-MadinaNeural"
    text = text[:1500]

    cached = _tts_cache_get(voice, text, rate)
    if cached:
        return Response(cached, mimetype="audio/mpeg")

    try:
        import edge_tts
        com = edge_tts.Communicate(text, voice, rate=rate)
        buf = io.BytesIO()

        async def _run():
            async for chunk in com.stream():
                if chunk.get("type") == "audio":
                    buf.write(chunk["data"])

        asyncio.run(_run())
        data = buf.getvalue()
        if not data:
            return jsonify({"error": "Ovoz yaratilmadi"}), 502
        _tts_cache_set(voice, text, rate, data)
        return Response(data, mimetype="audio/mpeg")
    except Exception as exc:
        return jsonify({"error": f"TTS xatolik: {exc}"}), 502


# ============================================================
# OLLAMA-MOS YORDAMCHI ENDPOINTLAR
# ============================================================
@app.post("/v1/chat/completions")
def api_openai_completions():
    """OpenAI-mos interfeys (boshqa kutubxonalar uchun)."""
    data = request.get_json(silent=True) or {}
    messages = data.get("messages") or []
    sid = data.get("session_id") or data.get("sessionId") or "default"
    for m in messages:
        if m.get("role") == "user":
            user_text = m["content"]
    try:
        s = get_session(sid)
        reply = _generate(SYSTEM_PROMPT, s["history"], user_text)
        s["history"] = (s["history"] +
                        [{"role": "user", "content": user_text},
                         {"role": "assistant", "content": reply}])[-MAX_HISTORY:]
    except Exception as e:
        return jsonify({"error": f"AI ichki xatolik: {e}"}), 500

    return jsonify({
        "id": "chatcmpl-uzdub-ai",
        "object": "chat.completion",
        "created": int(datetime.now().timestamp()),
        "model": data.get("model", MODEL_NAME),
        "choices": [{
            "index": 0,
            "message": {"role": "assistant", "content": reply},
            "finish_reason": "stop",
        }],
        "usage": {"prompt_tokens": 0, "completion_tokens": 0,
                  "total_tokens": 0},
    })


@app.get("/api/tags")
def api_tags():
    return jsonify({
        "models": [{
            "name": MODEL_NAME,
            "model": MODEL_NAME,
            "modified_at": _ts(),
            "size": 0,
            "digest": "uzdub-ai",
            "details": {
                "parent_model": "", "format": "custom",
                "family": "uzdub-ai", "families": ["uzdub-ai"],
                "parameter_size": "0 B", "quantization_level": "",
            },
        }]
    })


@app.post("/api/show")
def api_show():
    return jsonify({
        "model": MODEL_NAME,
        "details": {
            "parent_model": "", "format": "custom",
            "family": "uzdub-ai", "families": ["uzdub-ai"],
            "parameter_size": "0 B", "quantization_level": "",
        },
        "modelfile": "# uzdub-ai — 0 dan qurilgan AI",
        "parameters": "temperature 0.4",
        "template": "{{ .Prompt }}",
        "license": "MIT",
        "capabilities": ["completion"],
    })


@app.get("/api/version")
def api_version():
    return jsonify({"version": "0.1.0-uzdub-ai",
                    "ai": "brain.py + ollama-ready", "utf8": True})


@app.get("/health")
def health():
    return {"status": "ok", "ai": "uzdub-ai",
            "xotira": len(_sessions), "max_history": MAX_HISTORY}


if __name__ == "__main__":
    print("=" * 58)
    print("  UZDUB AI server — XOTIRA + Ollama + ovoz")
    print(f"  Endpoit: http://{HOST}:{PORT}/api/chat")
    print(f"  Ollama:  {OLLAMA_URL}")
    print(f"  Xotira:  har sessiya uchun system + oxirgi {MAX_HISTORY} xabar")
    print("=" * 58)
    app.run(host=HOST, port=PORT, threaded=True, debug=False)