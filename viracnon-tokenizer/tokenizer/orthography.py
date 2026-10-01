"""Orthographic helpers for Viracnon text: normalization, diacritic folding,
and grapheme segmentation.

Key constraint: the palatalized lateral ⟨ḽ⟩ (U+1E3D, l + combining circumflex
below U+032D) is ONE grapheme/phoneme. It is never split into h+l or l+y, and
diacritic folding never reduces it to plain ⟨l⟩.
"""

import unicodedata

LH_LOWER = "ḽ"  # ḽ
LH_UPPER = "Ḽ"  # Ḽ
COMBINING_CIRCUMFLEX_BELOW = "̭"

VOWELS = frozenset("aeiou")  # /a i u/ native + marginal /e/, and <o> spelling of /u/


def normalize(text: str) -> str:
    """NFC-normalize. Decomposed 'l' + U+032D composes to the single code point ḽ."""
    return unicodedata.normalize("NFC", text)


def fold(text: str) -> str:
    """Lowercase and strip accent marks (à á â ô ...) EXCEPT the circumflex below
    that forms ḽ. Used for accent-insensitive lookup only; never for output."""
    decomposed = unicodedata.normalize("NFD", normalize(text).lower())
    out = []
    for ch in decomposed:
        if unicodedata.combining(ch):
            if ch == COMBINING_CIRCUMFLEX_BELOW and out and out[-1] == "l":
                out.append(ch)
            continue
        out.append(ch)
    return unicodedata.normalize("NFC", "".join(out))


def graphemes(word: str) -> list:
    """Split a word into orthographic units: 'ng' (/ŋ/) and 'ḽ' are single units;
    a base letter keeps any combining marks attached to it."""
    word = normalize(word)
    units = []
    i = 0
    while i < len(word):
        ch = word[i]
        if ch.lower() == "n" and i + 1 < len(word) and word[i + 1].lower() == "g":
            unit = word[i:i + 2]
            i += 2
        else:
            unit = ch
            i += 1
        while i < len(word) and unicodedata.combining(word[i]):
            unit += word[i]
            i += 1
        units.append(unit)
    return units


def is_vowel(unit: str) -> bool:
    return fold(unit) in VOWELS


def has_vowel(word: str) -> bool:
    return any(is_vowel(g) for g in graphemes(word))
