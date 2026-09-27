# -*- coding: utf-8 -*-
"""
MEGA SEEDER — bilim bazasini 1000+ qatorga yetkazish
=====================================================
1) 7 ta mega fayldagi yangi BILIMlarni qo'shadi (allaqachon borini o'tkazib yuboradi)
2) 100 ta foydalanuvchi AFZALLIGI (pref:uNN:cat) — demo ma'lumot
3) Kategoriya afzalliklarini to'ldiradi (pref:kino va h.k.)
4) Har bir kontentga FIKR-MULOHAZA (fb:like/dislike:cid) qo'shadi
Ishga tushirish:  py -X utf8 seed_mega.py
"""
import random
import pymysql

import mega_knowledge_a
import mega_knowledge_b
import mega_knowledge_c
import mega_knowledge_d
import mega_knowledge_e
import mega_knowledge_f
import mega_knowledge_g
import mega_knowledge_h

DB_CONFIG = {
    "host": "localhost", "port": 3306, "user": "root",
    "password": "", "database": "uzdub", "charset": "utf8mb4",
}

# Barcha kontent IDlari (content jadvalidan)
CONTENT_IDS = [42, 45, 48, 49, 50, 51, 52, 53, 54, 56,
               57, 58, 59, 60, 61, 62, 63, 64, 65, 100003]


def build_pref_rows():
    """100 ta foydalanuvchi afzalligi (pref:uNN:cat) + kategoriya qatorlari."""
    rows = []
    cats = ["kino", "anime", "multfilm"]
    rnd = random.Random(2026)          # takrorlanadigan natija
    for i in range(1, 101):
        cat = cats[(i - 1) % 3]
        neg = rnd.random() < 0.22      # ~22% — yoqmaydi
        tag = f"{cat}:no" if neg else cat
        rows.append((f"pref:u{i:03d}:{tag}", "1"))
    # Kategoriya darajasidagi afzalliklar (tavsiyaga ta'sir qiladi)
    rows += [
        ("pref:kino", "12"),
        ("pref:kino:no", "3"),
        ("pref:anime:no", "2"),
        ("pref:multfilm:no", "4"),
    ]
    return rows


def build_fb_rows(conn):
    """Har kontent uchun yo'q fikr-mulohaza qatorlarini aniqlaydi."""
    cur = conn.cursor()
    rnd = random.Random(7)
    rows = []
    for cid in CONTENT_IDS:
        cur.execute("SELECT id FROM ai_knowledge WHERE title = %s LIMIT 1",
                    (f"fb:like:{cid}",))
        if not cur.fetchone():
            rows.append((f"fb:like:{cid}", str(rnd.randint(2, 7))))
        cur.execute("SELECT id FROM ai_knowledge WHERE title = %s LIMIT 1",
                    (f"fb:dislike:{cid}",))
        if not cur.fetchone():
            rows.append((f"fb:dislike:{cid}", str(rnd.randint(1, 3))))
    return rows


def main():
    knowledge = []
    for mod in (mega_knowledge_a, mega_knowledge_b, mega_knowledge_c,
                mega_knowledge_d, mega_knowledge_e, mega_knowledge_f,
                mega_knowledge_g, mega_knowledge_h):
        knowledge += mod.KNOWLEDGE

    added_k = skipped_k = 0
    added_p = skipped_p = 0
    added_f = skipped_f = 0

    try:
        conn = pymysql.connect(**DB_CONFIG)
    except Exception as e:
        print(f"Bazaga ulanishda xato: {e}\nXAMMP (MySQL) ishlayotganini tekshiring.")
        return

    try:
        cur = conn.cursor(pymysql.cursors.DictCursor)
        # 1) BILIM
        for title, content in knowledge:
            cur.execute("SELECT id FROM ai_knowledge WHERE title = %s "
                        "ORDER BY id DESC LIMIT 1", (title,))
            if cur.fetchone():
                skipped_k += 1
                continue
            cur.execute("INSERT INTO ai_knowledge (title, content, status) "
                        "VALUES (%s, %s, 'approved')", (title, content))
            added_k += 1

        # 2) AFZALLIKLAR
        for title, content in build_pref_rows():
            cur.execute("SELECT id FROM ai_knowledge WHERE title = %s "
                        "ORDER BY id DESC LIMIT 1", (title,))
            if cur.fetchone():
                skipped_p += 1
                continue
            cur.execute("INSERT INTO ai_knowledge (title, content, status) "
                        "VALUES (%s, %s, 'approved')", (title, content))
            added_p += 1

        # 3) FIKR-MULOHAZALAR
        for title, content in build_fb_rows(conn):
            cur.execute("INSERT INTO ai_knowledge (title, content, status) "
                        "VALUES (%s, %s, 'approved')", (title, content))
            added_f += 1

        cur.execute("""
            SELECT COUNT(*) AS n,
                   SUM(title LIKE 'fb:%%') AS fb,
                   SUM(title LIKE 'pref:%%') AS pref,
                   SUM(title NOT LIKE 'fb:%%' AND title NOT LIKE 'pref:%%') AS bilim
            FROM ai_knowledge WHERE status='approved'""")
        total = cur.fetchone()
        conn.commit()
    except Exception as e:
        print(f"Yozishda xato: {e}")
        conn.rollback()
    finally:
        conn.close()

    print("✅ Mega bilim qo'shildi:", added_k, "ta (bor edi:", skipped_k, ")")
    print("✅ Afzallik qo'shildi:", added_p, "ta (bor edi:", skipped_p, ")")
    print("✅ Fikr-mulohaza qo'shildi:", added_f, "ta")
    print("-" * 40)
    print(f"📊 JAMI qatorlar: {total['n']}")
    print(f"   • Bilim: {total['bilim']} | Afzallik: {total['pref']} | "
          f"Fikr-mulohaza: {total['fb']}")
    if total["n"] >= 1000:
        print("🎉 Maqsadga erishildi: 1000 tadan KO'P! (JAMI 1000+ qator)")
    else:
        print(f"📈 Hozircha {total['n']} — yana {1000 - total['n']} qator kerak")


if __name__ == "__main__":
    main()