# -*- coding: utf-8 -*-
"""
REYTING BASHORATCHISI — PYTORCH NEYRON TARMOQ (kattaroq model)
=============================================================
Saytdagi kontentlar haqidagi ma'lumotlarga qarab (kategoriya, yil,
janrlar, ko'rishlar soni, holat) kontentning reytingini bashorat qiladi.

NEGA KERAK?
  Bazadagi ba'zi kontentlarning reytingi 0 yoki noma'lum. Bu model
  o'xshash kontentlarga qarab "taxminiy reyting" beradi — AI tavsiyani
  yaxshilaydi (masalan: "★~7.2 (AI bahosi)").

QANDAY ISHLAYDI?
  1. MySQL'dan barcha kontentlar olinadi (20+ fayl)
  2. Har bir kontent raqamli vektorga aylantiriladi:
       - kategoriya (kino/anime/multfilm) — one-hot
       - holat (completed/ongoing/upcoming) — one-hot
       - chiqish yili (normalizatsiyalangan)
       - ko'rishlar soni (log-normalizatsiyalangan)
       - premium yoki yo'q
       - eng keng tarqalgan 12 janr — multi-hot
  3. 3 qatlamli MLP (Linear→ReLU→Dropout→Linear→ReLU→Linear) MSE
     yo'qotish funksiyasi bilan o'qitiladi
  4. Model rating_nn.pth ga saqlanadi

Dastlabki ma'lumot kam (20 ga yaqin) — shuning uchun bu model kichik
demonstratsiya/ouchun emas, balki REAL MA'LUMOTLAR BILAN ishlaydi;
ma'lumot ko'paygan sayin aniqligi oshadi.
"""

import math
import os
import random

import pymysql
import torch
import torch.nn as nn

MODEL_PATH = os.path.join(os.path.dirname(__file__), "rating_nn.pth")
MODEL_VERSION = 1

DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "localhost"),
    "port": int(os.environ.get("DB_PORT", "3306")),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASS", ""),
    "database": os.environ.get("DB_NAME", "uzdub"),
    "charset": "utf8mb4",
}

TOP_GENRES = 12          # modelga kiritiladigan eng mashhur janrlar soni
YEAR_MIN = 1990
YEAR_MAX = 2026


# ---------------------------------------------------------------------------
# MA'LUMOTLARNI BAZADAN OLISH
# ---------------------------------------------------------------------------
def _load_rows():
    """MySQL'dan kontent + janrlarini yuklab oladi."""
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor(pymysql.cursors.DictCursor) as cur:
            cur.execute(
                "SELECT c.id, c.title, c.category_id, cat.slug AS cat_slug, "
                "c.release_year, c.rating, c.views, c.is_premium, c.status, "
                "(SELECT GROUP_CONCAT(g.slug) FROM content_genres cg "
                " JOIN genres g ON g.id = cg.genre_id "
                " WHERE cg.content_id = c.id) AS genre_slugs "
                "FROM content c JOIN categories cat ON c.category_id = cat.id")
            return cur.fetchall()
    finally:
        conn.close()


# ---------------------------------------------------------------------------
# FEATURE (XUSUSIYAT) QURISH
# ---------------------------------------------------------------------------
class FeatureBuilder:
    """Kontent -> raqamli vektor aylantiruvchi (model bilan saqlanadi)."""

    def __init__(self, rows, top_genres=TOP_GENRES):
        cats = sorted({r["cat_slug"] for r in rows if r.get("cat_slug")})
        statuses = ["completed", "ongoing", "upcoming"]
        # Eng ko'p uchraydigan janrlar
        from collections import Counter
        counter = Counter()
        for r in rows:
            if r.get("genre_slugs"):
                for g in r["genre_slugs"].split(","):
                    counter[g.strip()] += 1
        self.cats = cats or ["kino", "anime", "multfilm"]
        self.statuses = statuses
        self.genres = [g for g, _ in counter.most_common(top_genres)]
        # cats(one-hot) + statuses(one-hot) + [yil, ko'rishlar, premium] + janrlar(multi-hot)
        self.n_feat = (len(self.cats) + len(self.statuses) + 3 + len(self.genres))

    def _onehot(self, value, options):
        vec = [0.0] * len(options)
        if value in options:
            vec[options.index(value)] = 1.0
        return vec

    def build(self, r):
        v = []
        v += self._onehot(r.get("cat_slug") or r.get("category"), self.cats)
        v += self._onehot(r.get("status") or "completed", self.statuses)
        # Yil (normalizatsiya)
        y = r.get("release_year")
        v.append(0.5 if not y else max(0.0, min(1.0, (y - YEAR_MIN) / (YEAR_MAX - YEAR_MIN))))
        # Ko'rishlar (log normalizatsiya)
        views = float(r.get("views") or 0)
        v.append(min(1.0, math.log1p(views) / 8.0))
        # Premium
        v.append(1.0 if r.get("is_premium") else 0.0)
        # Janrlar (multi-hot) — matn yoki ro'yxat bo'lishi mumkin
        gset = set()
        raw = r.get("genre_slugs") or r.get("genres") or ""
        for g in (raw.split(",") if isinstance(raw, str) else raw):
            gset.add(g.strip())
        v += [1.0 if g in gset else 0.0 for g in self.genres]
        return v


# ---------------------------------------------------------------------------
# TARMOQ: 3 QATLAMLI MLP
# ---------------------------------------------------------------------------
class RatingNet(nn.Module):
    def __init__(self, n_feat, hidden1=32, hidden2=16):
        super().__init__()
        self.fc1 = nn.Linear(n_feat, hidden1)
        self.drop = nn.Dropout(0.2)
        self.fc2 = nn.Linear(hidden1, hidden2)
        self.out = nn.Linear(hidden2, 1)

    def forward(self, x):
        x = torch.relu(self.fc1(x))
        x = self.drop(x)
        x = torch.relu(self.fc2(x))
        return self.out(x)


# ---------------------------------------------------------------------------
# O'QITISH
# ---------------------------------------------------------------------------
def train(epochs=600, seed=42):
    """Bazadagi kontentlar bilan reyting bashoratchisini o'qitadi."""
    torch.manual_seed(seed)
    random.seed(seed)

    rows = _load_rows()
    fb = FeatureBuilder(rows)

    # Faqat reytingi ma'lum bo'lgan kontentlardan o'rgatamiz
    rated = [r for r in rows if float(r.get("rating") or 0) > 0]
    if len(rated) < 4:
        print("rating_nn: reytingi ma'lum kontentlar juda kam — model yaratilmadi")
        return None

    random.shuffle(rated)
    n_val = max(1, len(rated) // 5)
    val = rated[:n_val]
    train_rows = rated[n_val:]

    def make_xy(part):
        X = torch.tensor([fb.build(r) for r in part], dtype=torch.float32)
        y = torch.tensor([[float(r.get("rating")) / 10.0] for r in part],
                         dtype=torch.float32)
        return X, y

    Xtr, ytr = make_xy(train_rows)
    Xva, yva = make_xy(val)

    net = RatingNet(fb.n_feat)
    loss_fn = nn.MSELoss()
    optimizer = torch.optim.Adam(net.parameters(), lr=5e-3)

    best_loss = float("inf")
    best_state = None
    net.train()
    for epoch in range(1, epochs + 1):
        optimizer.zero_grad()
        out = net(Xtr)
        loss = loss_fn(out, ytr)
        loss.backward()
        optimizer.step()

        if epoch % 100 == 0 or epoch == epochs:
            net.eval()
            with torch.no_grad():
                vl = loss_fn(net(Xva), yva).item()
            if vl < best_loss:
                best_loss = vl
                best_state = {k: v.clone() for k, v in net.state_dict().items()}
            net.train()
        else:
            if epoch == 1:
                best_state = {k: v.clone() for k, v in net.state_dict().items()}

    if best_state:
        net.load_state_dict(best_state)

    # Val to'plamda yaxlitlik (0-10 shkalada o'rtacha xato)
    net.eval()
    with torch.no_grad():
        preds = net(Xva).clamp(0, 1) * 10.0
    mae = (preds - torch.tensor(
        [[float(r.get("rating"))] for r in val])).abs().mean().item()

    torch.save({
        "version": MODEL_VERSION,
        "state_dict": net.state_dict(),
        "feature_builder": {
            "cats": fb.cats,
            "statuses": fb.statuses,
            "genres": fb.genres,
        },
        "val_mae": mae,
        "n_train": len(train_rows),
    }, MODEL_PATH)
    print(f"rating_nn o'qitildi: {len(train_rows)} namunadan, "
          f"val MAE ≈ {mae:.2f} (0-10 shkala)")
    return mae


# ---------------------------------------------------------------------------
# YUKLASH VA BASHORAT
# ---------------------------------------------------------------------------
def load_rating_predictor():
    """rating_nn.pth ni yuklab, predict(kontent)->taxminiy reyting qaytaradi.

    Xato bo'lsa yoki model bo'lmasa — None qaytaradi (miya baribir ishlaydi).
    """
    try:
        import torch  # noqa
    except Exception:
        return None

    if not os.path.exists(MODEL_PATH):
        try:
            if train() is None:
                return None
        except Exception as e:
            print(f"rating_nn yaratilmadi: {e}")
            return None

    try:
        data = torch.load(MODEL_PATH, map_location="cpu")
        if data.get("version") != MODEL_VERSION:
            # Eski model — qayta o'qitamiz
            try:
                train()
                data = torch.load(MODEL_PATH, map_location="cpu")
            except Exception:
                pass
    except Exception:
        return None

    cfg = data["feature_builder"]
    n_feat = len(cfg["cats"]) + len(cfg["statuses"]) + 3 + len(cfg["genres"])
    net = RatingNet(n_feat)
    net.load_state_dict(data["state_dict"])
    net.eval()

    class _FB:
        cats = cfg["cats"]
        statuses = cfg["statuses"]
        genres = cfg["genres"]

    def predict(item):
        """item: lug'at yoki dict-kabi — {category, release_year, views, ...}"""
        fb_build = lambda r: (
            _onehot(r.get("cat_slug") or r.get("category") or "", _FB.cats)
            + _onehot(r.get("status") or "completed", _FB.statuses)
            + [
                0.5 if not r.get("release_year")
                else max(0.0, min(1.0, (r["release_year"] - YEAR_MIN) / (YEAR_MAX - YEAR_MIN))),
                min(1.0, math.log1p(float(r.get("views") or 0)) / 8.0),
                1.0 if r.get("is_premium") else 0.0,
            ]
            + [
                1.0 if g in _tokens(r.get("genre_slugs") or r.get("genres") or "") else 0.0
                for g in _FB.genres
            ]
        )
        with torch.no_grad():
            x = torch.tensor([fb_build(item)], dtype=torch.float32)
            pred = net(x).item() * 10.0
        return max(0.0, min(10.0, pred))

    return predict


def _onehot(value, options):
    return [1.0 if value == o else 0.0 for o in options]


def _tokens(raw):
    if isinstance(raw, list):
        return {str(x).strip() for x in raw}
    return {g.strip() for g in str(raw).split(",")}


if __name__ == "__main__":
    mae = train()
    if mae is not None:
        pred = load_rating_predictor()
        rows = _load_rows()
        print("\nBaho (0-10):")
        for r in rows[:8]:
            real = float(r.get("rating") or 0)
            est = pred(r)
            print(f"  {r['title']!s:30} haqiqiy={real or '?'} taxmin={est:.1f}")