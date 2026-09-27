# -*- coding: utf-8 -*-
"""
NIYAT KLASSIFIKATORI — PYTORCH NEYRON TARMOQ
=============================================
Bu modul foydalanuvchi xabarining "niyatini" (salom, tavsiya, yangilik,
xayr, suhbat...) aniqlaydigan kichik neyron tarmoqni o'qitadi.

QANDAY ISHLAYDI?
  1. O'zbekcha so'z birikmalaridan sintetik (o'rgatish) ma'lumotlari
     yasaladi
  2. Har bir xabar "so'zlar sumkasi" (bag-of-words) vektoriga aylanadi
  3. Kichik MLP (2 qatlam) tarmoq shu vektorlardan niyatni (softmax
     ehtimollik) o'rganadi
  4. Model intent_nn.pth fayliga saqlanadi

O'Z-O'ZINI RIVOJLANTIRISH (continual learning):
  Haqiqiy foydalanuvchi xabarlaridan qoidalarning ishonchli qarorlari
  "pseudo-metka" sifatida yig'iladi (data/user_messages.jsonl) va model
  periyodik shu real namunalar bilan qayta o'qitiladi. Ya'ni chatbot
  vaqt o'tishi bilan foydalanuvchilarning o'z so'zlarida yaxshiroq
  tushunadigan bo'ladi.

Muhim: bu neyron tarmoq AI miyasida YORDAMCHI sifatida ishlaydi —
asosiy javob qoidalar (0 dan) bilan beriladi, NN esa noaniq holatlarda
yo'nalish beradi. Agar PyTorch bo'lmasa ham miya ishlayveradi!
"""

import json
import os
import random
import re

import torch
import torch.nn as nn

MODEL_PATH = os.path.join(os.path.dirname(__file__), "intent_nn.pth")
MODEL_VERSION = 2   # yangi namunalar qo'shilsa, bu raqam oshiriladi -> qayta o'qitiladi

# O'z-o'zini rivojlantirish sozlamalari
DATA_DIR = os.path.join(os.path.dirname(__file__), "data")
USER_SAMPLES_PATH = os.path.join(DATA_DIR, "user_messages.jsonl")
RETRAIN_AFTER = 5      # shuncha yangi real namunadan keyin qayta o'qitamiz
MAX_USER_SAMPLES = 500  # yig'iladigan real namunalar chegarasi

# ---------------------------------------------------------------------------
# NIYATLAR
# ---------------------------------------------------------------------------
INTENTS = [
    "greeting",   # salomlashish
    "bye",        # xayrlashish
    "thanks",     # rahmat
    "recommend",  # tavsiya so'rash
    "new",        # yangi kontent
    "smalltalk",  # oddiy suhbat
]

# ---------------------------------------------------------------------------
# SINTAKTIK O'RGATISH MA'LUMOTLARI (o'zbekcha namunalar)
# ---------------------------------------------------------------------------
PHRASES = {
    "greeting": [
        "salom", "assalomu alaykum", "alik", "hayrli kun", "hey salom",
        "salom bot", "qalaysiz", "qalaysan", "yaxshimisiz", "holingiz qanday",
        "tanishganimdan xursandman", "xayrli tong", "xayrli kech",
    ],
    "bye": [
        "xayr", "sog bo'l", "ko'rishguncha", "alvido", "keyin gaplashamiz",
        "hozircha xayr", "tugatdik", "ketishim kerak", "guruk", "xayr xozircha",
    ],
    "thanks": [
        "rahmat", "tashakkur", "arziydi", "katta rahmat", "minnatdorchilik",
        "rahmat sizga", "yordam uchun rahmat",
    ],
    "recommend": [
        "qanday kino ko'rish mumkin", "menga filmlar tavsiya qil",
        "yaxshi anime bormi", "multfilm ko'rsat", "nima ko'rsam bo'ladi",
        "zo'r kino topib ber", "qo'rqinchli narsa tavsiya qil",
        "komediya kino qidir", "menga sarguzasht tavsiya qil",
        "biror narsa ko'rmoqchiman", "eng yaxshi filmlar qaysi",
        "tavsiya bermaysizmi", "shuni ko'rganmisiz",
    ],
    "new": [
        "yangi qo'shilganlar qaysi", "yangi filmlar bormi", "so'nggi qo'shilganlar",
        "yangi anime keldimi", "bugungi yangiliklar", "nima yangilik",
        "yangi kontent ko'rsat", "oxirgi qo'shilgan narsa",
    ],
    "smalltalk": [
        "nima gaplar", "bugun kun qanday o'tdi", "ob-havo qanday",
        "men zerikdim", "qiziqarli gap ayt", "musiqa eshitay",
        "bugun dars bo'ldi", "ishlar zo'rmi", "dam oldim",
        "suhbatlashamizmi", "boringa nima", "nahot shunday",
        "charchadim", "bugun juda issiq", "kunlar qisqarib qoldi",
        "yoqimli suhbat bo'ldi", "ular nima deb o'ylaysan",
        "o'ylab ko'rsam", "dam olish kerak ekan", "qiziq",
        "mazali ovqat yedim", "uyga qaytdim", "ertaga imtihon bor",
        "telefonim qotdi", "nima qilib yuribsan", "yaxshi kunda yuring",
        "baho olaman deyman", "ovqat pishirdim", "ko'chada yomg'ir yog'yapti",
        "bugun stress bo'ldi", "ko'nglim g'amgin", "xursandman bugun",
        "ehtimol", "balki", "qarang-a", "mana bu gap qiziq",
    ],
}

# Qo'shimcha "bezaksiz" so'zlar — namunalarni boyitish uchun
FILLERS = ["", "menga", "iltimos", "buni", "hozir", "sevimli", "yahshi"]


# ---------------------------------------------------------------------------
# FOYDALANUVCHI XABARLARIDAN O'RGANISH (o'z-o'zini rivojlantirish)
# ---------------------------------------------------------------------------
def load_user_samples():
    """data/user_messages.jsonl'dan to'plangan real namunalarni o'qiydi."""
    samples = []
    if not os.path.exists(USER_SAMPLES_PATH):
        return samples
    try:
        with open(USER_SAMPLES_PATH, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                try:
                    obj = json.loads(line)
                except Exception:
                    continue
                text = str(obj.get("text", ""))
                label = str(obj.get("label", ""))
                if label in INTENTS and 3 <= len(text) <= 120:
                    samples.append((text, label))
    except Exception:
        return []
    return samples


def add_user_sample(text, label):
    """Haqiqiy foydalanuvchi xabarini pseudo-metkali namuna sifatida saqlaydi.

    - label INTENTS'da bo'lishi kerak (qoidalar ishonchli aniqlagan niyatlar)
    - takrorlarni tashlaydi va ro'yxatni MAX_USER_SAMPLES bilan cheklaydi
    """
    if label not in INTENTS:
        return False
    text = (text or "").strip()
    if not (3 <= len(text) <= 120):
        return False
    try:
        os.makedirs(DATA_DIR, exist_ok=True)
        samples = load_user_samples()
        if any(t == text for t, _ in samples):
            return False
        samples.append((text, label))
        samples = samples[-MAX_USER_SAMPLES:]
        with open(USER_SAMPLES_PATH, "w", encoding="utf-8") as f:
            for t, l in samples:
                f.write(json.dumps({"text": t, "label": l},
                                   ensure_ascii=False) + "\n")
        return True
    except Exception:
        return False


def maybe_retrain(force=False):
    """Yangi real foydalanuvchi namunalari to'plangan bo'lsa — modelni
    shu REAL ma'lumotlar bilan qayta o'qitadi.

    Qayta o'qitilgan bo'lsa True, aks holda False.
    """
    try:
        if not os.path.exists(MODEL_PATH):
            return False
        data = torch.load(MODEL_PATH, map_location="cpu")
        trained = int(data.get("n_user_train", 0))
        now = len(load_user_samples())
        if force or (now - trained >= RETRAIN_AFTER):
            train()
            return True
    except Exception:
        return False
    return False


def _tokenize(text):
    text = text.lower()
    text = re.sub(r"[^\w\s\u0400-\u04FF]", " ", text)
    return text.split()


def build_dataset(rng):
    """Har bir intent uchun o'rgatish namunalarini (matn, label) yasaydi."""
    samples = []
    for label, phrases in PHRASES.items():
        for p in phrases:
            filler = rng.choice(FILLERS)
            if filler:
                samples.append((f"{filler} {p}", label))
            samples.append((p, label))
            # so'roq belgisi bilan varianti
            samples.append((p + "?", label))
            # "siz" bilan varianti
            if rng.random() < 0.5:
                samples.append((f"siz {p}", label))
    rng.shuffle(samples)
    return samples


def build_vocab(samples, min_freq=1):
    """Lug'at: so'z -> indeks (kampanish takrorlariga chidamli)."""
    from collections import Counter
    counter = Counter()
    for text, _ in samples:
        counter.update(_tokenize(text))
    words = [w for w, c in counter.items() if c >= min_freq]
    return {w: i for i, w in enumerate(words)}


def make_vector(text, vocab):
    """Xabarni 0/1 vektorga aylantiradi (so'z sumkasi)."""
    vec = [0.0] * len(vocab)
    for w in _tokenize(text):
        i = vocab.get(w)
        if i is not None:
            vec[i] = 1.0
    return torch.tensor([vec], dtype=torch.float32)


# ---------------------------------------------------------------------------
# TARMOQ: 2 qatlamli MLP
# ---------------------------------------------------------------------------
class IntentNet(nn.Module):
    def __init__(self, vocab_size, n_intents, hidden=32):
        super().__init__()
        self.fc1 = nn.Linear(vocab_size, hidden)
        self.relu = nn.ReLU()
        self.fc2 = nn.Linear(hidden, n_intents)

    def forward(self, x):
        return self.fc2(self.relu(self.fc1(x)))


def train(samples=None, epochs=400, seed=42):
    """Neyron tarmoqni o'qitib, intent_nn.pth ga saqlaydi.

    O'z-o'zini rivojlantirish: sintetik namunalarga qo'shilib,
    foydalanuvchi xabarlaridan yig'ilgan real ma'lumot ham ishlatiladi.
    """
    torch.manual_seed(seed)
    rng = random.Random(seed)
    samples = list(samples or build_dataset(rng))
    user_samples = load_user_samples()
    if user_samples:
        samples += user_samples   # haqiqiy foydalanuvchi so'zlari bilan boyitamiz

    vocab = build_vocab(samples)
    label_idx = {l: i for i, l in enumerate(INTENTS)}

    X, y = [], []
    for text, label in samples:
        X.append(make_vector(text, vocab)[0])
        y.append(torch.tensor(label_idx[label]))
    X = torch.stack(X)
    y = torch.tensor(y, dtype=torch.long)

    net = IntentNet(len(vocab), len(INTENTS))
    loss_fn = nn.CrossEntropyLoss()
    optimizer = torch.optim.Adam(net.parameters(), lr=0.01)

    net.train()
    batch = 32
    for epoch in range(1, epochs + 1):
        perm = torch.randperm(len(X))
        for s in range(0, len(X), batch):
            idx = perm[s:s + batch]
            optimizer.zero_grad()
            out = net(X[idx])
            loss = loss_fn(out, y[idx])
            loss.backward()
            optimizer.step()

    # O'qitish aniqligi
    net.eval()
    with torch.no_grad():
        preds = net(X).argmax(dim=1)
    acc = (preds == y).sum().item() / len(y) * 100

    torch.save({
        "state_dict": net.state_dict(),
        "vocab": vocab,
        "intents": INTENTS,
        "hidden": 32,
        "version": MODEL_VERSION,
        "train_acc": acc,
        "n_user_train": len(user_samples),   # shu real namunalar bilan o'qitildi
    }, MODEL_PATH)
    return acc


# ---------------------------------------------------------------------------
# YUKLASH VA BASHORAT
# ---------------------------------------------------------------------------
def load_predictor():
    """intent_nn.pth ni yuklab, predict(xabar)->(niyat, ishonch) qaytaradi.

    Agar PyTorch yoki model bo'lmasa — None qaytaradi (miya baribir ishlaydi).
    """
    try:
        import torch  # noqa
    except Exception:
        return None

    if not os.path.exists(MODEL_PATH):
        # Yo'q bo'lsa — o'zi o'qitib olamiz (birinchi ishga tushirishda)
        try:
            train()
        except Exception as e:
            print(f"intent_nn o'qitib bo'lmadi: {e}")
            return None

    data = torch.load(MODEL_PATH, map_location="cpu")
    if data.get("version") != MODEL_VERSION:
        # Eski model — yangi namunalar bilan qayta o'qitamiz
        try:
            train()
            data = torch.load(MODEL_PATH, map_location="cpu")
        except Exception:
            return None

    # O'z-o'zini rivojlantirish: ishga tushganda yangi real namunalar
    # to'plangan bo'lsa, model shu bilan qayta o'qitiladi
    if maybe_retrain():
        try:
            data = torch.load(MODEL_PATH, map_location="cpu")
        except Exception:
            pass

    net = IntentNet(len(data["vocab"]), len(data["intents"]),
                    hidden=data.get("hidden", 32))
    net.load_state_dict(data["state_dict"])
    net.eval()
    vocab, intents = data["vocab"], data["intents"]

    def predict(text):
        vec = make_vector(text, vocab)
        with torch.no_grad():
            probs = torch.softmax(net(vec), dim=1)[0]
        conf, idx = probs.max(dim=0)
        conf = conf.item()
        if conf < 0.6:                 # ishonch past — suhbat deb hisoblaymiz
            return "smalltalk", conf
        return intents[idx.item()], conf

    return predict


if __name__ == "__main__":
    acc = train()
    print(f"O'qitildi! Trainerlardagi aniqlik: {acc:.1f}%")
    pred = load_predictor()
    for t in ["salom", "yangi anime bormi", "komediya kino top", "nima gaplar", "xayr"]:
        print(f"{t!r:20} -> {pred(t)}")