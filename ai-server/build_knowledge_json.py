# -*- coding: utf-8 -*-
"""
BUILD KNOWLEDGE JSON — OFFLAYN BILIM BAZASI
============================================
MySQL'siz ham to'liq bilim bazasi ishlashi uchun barcha seed
ma'lumotlarni ai_knowledge.json'ga jamlaydi.

  Qayerda ishlatiladi:  brain.load_knowledge() — MySQL o'chiq bo'lganda
  shu fayldan o'qiydi (xatosiz degradatsiya).

Ishga tushirish:  py -X utf8 build_knowledge_json.py
"""
import io
import json
import pathlib
import random
import sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")

HERE = pathlib.Path(__file__).parent

# --- mega bilimlar (8 fayl) ---
import mega_knowledge_a
import mega_knowledge_b
import mega_knowledge_c
import mega_knowledge_d
import mega_knowledge_e
import mega_knowledge_f
import mega_knowledge_g
import mega_knowledge_h
import seed_knowledge

MEGA = (mega_knowledge_a, mega_knowledge_b, mega_knowledge_c,
        mega_knowledge_d, mega_knowledge_e, mega_knowledge_f,
        mega_knowledge_g, mega_knowledge_h)

CONTENT_IDS = [42, 45, 48, 49, 50, 51, 52, 53, 54, 56,
               57, 58, 59, 60, 61, 62, 63, 64, 65, 100003]


def build_pref_rows():
    """seed_mega.build_pref_rows bilan bir xil (deterministik)."""
    rows = []
    cats = ["kino", "anime", "multfilm"]
    rnd = random.Random(2026)
    for i in range(1, 101):
        cat = cats[(i - 1) % 3]
        neg = rnd.random() < 0.22
        tag = f"{cat}:no" if neg else cat
        rows.append((f"pref:u{i:03d}:{tag}", "1"))
    rows += [
        ("pref:kino", "12"), ("pref:kino:no", "3"),
        ("pref:anime:no", "2"), ("pref:multfilm:no", "4"),
    ]
    return rows


def build_fb_rows():
    """seed_mega.build_fb_rows bilan bir xil (deterministik, DB'siz)."""
    rnd = random.Random(7)
    rows = []
    for cid in CONTENT_IDS:
        rows.append((f"fb:like:{cid}", str(rnd.randint(2, 7))))
        rows.append((f"fb:dislike:{cid}", str(rnd.randint(1, 3))))
    return rows


def main():
    seen = set()
    data = []

    def add(title, content):
        title = (title or "").strip()
        content = (content or "").strip()
        if not title or not content:
            return
        if title in seen:                     # dublikatlarni tashlaymiz
            return
        seen.add(title)
        data.append({"title": title, "content": content})

    for mod in MEGA:
        for t, c in getattr(mod, "KNOWLEDGE", []):
            add(t, c)
    for t, c in getattr(seed_knowledge, "KNOWLEDGE", []):
        add(t, c)
    for t, c in build_pref_rows():
        add(t, c)
    for t, c in build_fb_rows():
        add(t, c)

    out = HERE / "ai_knowledge.json"
    with open(out, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)

    n_pref = sum(1 for x in data if x["title"].startswith("pref:"))
    n_fb = sum(1 for x in data if x["title"].startswith("fb:"))
    print(f"✅ ai_knowledge.json yozildi — jami {len(data)} qator")
    print(f"   bilim: {len(data) - n_pref - n_fb} | pref: {n_pref} | fb: {n_fb}")


if __name__ == "__main__":
    main()