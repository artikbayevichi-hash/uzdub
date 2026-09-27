# -*- coding: utf-8 -*-
"""Test qoldiqlarini tozalash: pref:/fb: qatorlari (barchasi testlardan)."""
import io
import sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")

import pymysql

conn = pymysql.connect(host="localhost", user="root", password="", db="uzdub")
try:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT title, content FROM ai_knowledge "
            "WHERE title LIKE 'pref:%' OR title LIKE 'fb:%' ORDER BY title")
        rows = cur.fetchall()
        print(f"O'chirishdan oldin: {len(rows)} ta test qatori")
        for t, c in rows:
            print("  ", t, "=", c)
        cur.execute(
            "DELETE FROM ai_knowledge WHERE title LIKE 'pref:%' OR title LIKE 'fb:%'")
        conn.commit()
        cur.execute("SELECT COUNT(*) FROM ai_knowledge")
        print("Qolgan umumiy qatorlar:", cur.fetchone()[0])
finally:
    conn.close()
print("TOZALANDI ✅")