"""Rule-based word + morpheme segmenter for Viracnon (Southern Catanduanes Bikol, bln).

Pipeline for each input text:
  1. Normalize (NFC) and split into surface tokens: words, numbers, punctuation.
  2. Merge multiword lexicon entries (e.g. "maski na").
  3. For each word, in order:
       a. exact lexicon lookup                      -> LEXICON   (confidence high)
       b. discourse particle list                   -> PARTICLE  (never affix-stripped)
       c. accent-insensitive lexicon/particle match -> LEXICON_FOLDED / PARTICLE (medium)
       d. otherwise UNK: reduplication is checked on the whole word BEFORE affix
          stripping, then affixes are stripped and reduplication is checked again
          on the remaining stem (CV- reduplication sits between prefix and root).
          The best guess is returned with confidence "low" and gloss None.

The lexicon is a SEED lexicon, not a complete dictionary. Unknown words are never
given a gloss; if a candidate root happens to be in the lexicon, that root's own
lexicon entry is reported separately under `root_lexicon`.
"""

import argparse
import json
import re
import sys
from collections import defaultdict
from pathlib import Path

try:
    from . import orthography as orth
except ImportError:  # executed as a plain script
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    import orthography as orth

PROJECT_ROOT = Path(__file__).resolve().parent.parent
DEFAULT_LEXICON = PROJECT_ROOT / "lexicon" / "viracnon_seed.json"
DEFAULT_RULES = PROJECT_ROOT / "rules" / "bikol_morphology.json"

_LETTER = r"(?:[^\W\d_]|[̀-ͯ])"
_TOKEN_RE = re.compile(
    rf"(?P<word>{_LETTER}+(?:[-'’]{_LETTER}+)*)"
    r"|(?P<number>\d+(?:[.,]\d+)*)"
    r"|(?P<punct>\S)"
)
_SENTENCE_FINAL = {".", "?", "!"}


class Segmenter:
    def __init__(self, lexicon_path=DEFAULT_LEXICON, rules_path=DEFAULT_RULES, lexicon_entries=None):
        if lexicon_entries is None:
            with open(lexicon_path, encoding="utf-8") as f:
                lexicon_entries = json.load(f)["entries"]
        with open(rules_path, encoding="utf-8") as f:
            rules = json.load(f)

        self.prefixes = rules["prefixes"]
        self.suffixes = rules["suffixes"]
        self.particles = {orth.normalize(p).lower() for p in rules["discourse_particles"]}
        self.particles_folded = {orth.fold(p): orth.normalize(p) for p in rules["discourse_particles"]}
        self.contrast_forms = {orth.fold(k): v for k, v in rules["non_virac_contrast_forms"].items()}
        affixable_pos = set(rules["affixable_pos"])

        self.exact = {}
        self.folded = defaultdict(list)
        self.multiword = {}
        self.roots = defaultdict(list)
        for entry in lexicon_entries:
            word = orth.normalize(entry["word"]).lower()
            parts = word.split()
            if len(parts) > 1:
                self.multiword[tuple(orth.fold(p) for p in parts)] = entry
                continue
            self.exact[word] = entry
            self.folded[orth.fold(word)].append(entry)
            if entry["pos"] in affixable_pos:
                self._add_root(word, entry["gloss"], entry, via="lexicon entry")
            analysis = entry.get("affix_analysis") or {}
            if analysis.get("root"):
                self._add_root(analysis["root"], analysis.get("root_gloss"), entry,
                               via=f"affix_analysis of '{entry['word']}'")
        self.max_multiword = max((len(k) for k in self.multiword), default=1)

    def _add_root(self, root, gloss, entry, via):
        self.roots[orth.fold(root)].append({
            "root": orth.normalize(root),
            "gloss": gloss,
            "lexicon_confidence": entry["confidence"],
            "via": via,
        })

    # ------------------------------------------------------------------ tokens
    def tokenize(self, text):
        """Surface tokenization. Spans refer to the NFC-normalized text."""
        text = orth.normalize(text)
        return [
            {"surface": m.group(), "type": m.lastgroup, "span": [m.start(), m.end()]}
            for m in _TOKEN_RE.finditer(text)
        ]

    # ----------------------------------------------------------- segmentation
    def segment(self, text):
        text = orth.normalize(text)
        tokens = self.tokenize(text)
        results = []
        i = 0
        prev = None
        while i < len(tokens):
            tok = tokens[i]
            if tok["type"] != "word":
                results.append(self._non_word(tok))
                prev = tok
                i += 1
                continue

            sentence_initial = prev is None or (prev["type"] == "punct" and prev["surface"] in _SENTENCE_FINAL)
            merged = self._match_multiword(text, tokens, i)
            if merged:
                result, consumed = merged
            else:
                result, consumed = self.analyze_word(tok["surface"], sentence_initial), 1
                result["span"] = tok["span"]
            results.append(result)
            prev = tokens[i + consumed - 1]
            i += consumed
        return results

    def _non_word(self, tok):
        return {
            "surface": tok["surface"], "span": tok["span"],
            "status": "NUMBER" if tok["type"] == "number" else "PUNCT",
            "confidence": "high",
        }

    def _match_multiword(self, text, tokens, i):
        for n in range(self.max_multiword, 1, -1):
            window = tokens[i:i + n]
            if len(window) < n or any(t["type"] != "word" for t in window):
                continue
            # only merge across plain whitespace, never across punctuation
            if any(text[a["span"][1]:b["span"][0]].strip() for a, b in zip(window, window[1:])):
                continue
            key = tuple(orth.fold(t["surface"]) for t in window)
            entry = self.multiword.get(key)
            if not entry:
                continue
            surface = text[window[0]["span"][0]:window[-1]["span"][1]]
            exact = " ".join(t["surface"].lower() for t in window) == orth.normalize(entry["word"]).lower()
            result = self._lexicon_result(surface, entry, exact)
            result["span"] = [window[0]["span"][0], window[-1]["span"][1]]
            return result, n
        return None

    def analyze_word(self, surface, sentence_initial=True):
        word = orth.normalize(surface).lower()
        folded = orth.fold(word)

        if word in self.exact:
            return self._lexicon_result(surface, self.exact[word], exact=True)
        if word in self.particles:
            return self._particle_result(surface, word, exact=True)

        hits = self.folded.get(folded, [])
        if hits:
            result = self._lexicon_result(surface, hits[0], exact=False)
            if len(hits) > 1:
                result["needs_review"] = True
                result["flags"].append("ambiguous_accent_folded_match")
                result["alternatives"] = [self._lexicon_result(surface, h, exact=False) for h in hits[1:]]
            self._attach_contrast_note(result, folded)
            return result
        if folded in self.particles_folded:
            return self._particle_result(surface, self.particles_folded[folded], exact=False)

        result = self._unknown_result(surface, word, sentence_initial)
        self._attach_contrast_note(result, folded)
        return result

    def _attach_contrast_note(self, result, folded):
        note = self.contrast_forms.get(folded)
        if note:
            result["notes"].append(note)
            result["flags"].append("non_virac_contrast_form")
            if result["status"] != "UNK":
                # e.g. unaccented 'dai' = Naga form AND accent-folded Virac 'dài'
                result["needs_review"] = True

    # ---------------------------------------------------------------- results
    @staticmethod
    def _base(surface):
        return {
            "surface": surface, "normalized": orth.normalize(surface).lower(),
            "status": None, "lemma": None, "gloss": None, "pos": None,
            "lexicon_confidence": None, "segmentation": None,
            "confidence": None, "needs_review": False,
            "alternatives": [], "flags": [], "notes": [],
        }

    def _lexicon_result(self, surface, entry, exact):
        r = self._base(surface)
        r.update({
            "status": "LEXICON" if exact else "LEXICON_FOLDED",
            "lemma": entry["word"], "gloss": entry["gloss"], "pos": entry["pos"],
            "lexicon_confidence": entry["confidence"],
            "segmentation": self._segmentation_from_entry(entry),
            "confidence": "high" if exact else "medium",
        })
        if not exact:
            r["flags"].append("accent_insensitive_match")
        if entry.get("notes"):
            r["notes"].append(entry["notes"])
        return r

    def _particle_result(self, surface, particle, exact):
        r = self._base(surface)
        r.update({
            "status": "PARTICLE", "lemma": particle, "pos": "discourse particle",
            "lexicon_confidence": "COGNATE-PROBABLE",
            "segmentation": {"prefixes": [], "reduplicant": None, "root": particle, "suffixes": []},
            "confidence": "high" if exact else "medium",
        })
        r["notes"].append("Listed in the brief's discourse-particle set; no gloss supplied.")
        if not exact:
            r["flags"].append("accent_insensitive_match")
        return r

    @staticmethod
    def _segmentation_from_entry(entry):
        analysis = entry.get("affix_analysis")
        word = orth.normalize(entry["word"])
        if not analysis:
            return {"prefixes": [], "reduplicant": None, "root": word, "suffixes": []}
        if analysis.get("type") == "multiword":
            return {"components": analysis["components"]}
        return {
            "prefixes": analysis.get("prefixes", []),
            "reduplicant": analysis.get("reduplication"),
            "root": analysis.get("surface_root") or analysis["root"],
            "root_lemma": analysis["root"],
            "suffixes": analysis.get("suffixes", []),
        }

    def _unknown_result(self, surface, word, sentence_initial):
        r = self._base(surface)
        r.update({"status": "UNK", "confidence": "low"})
        if surface[:1].isupper() and not sentence_initial:
            r["flags"].append("capitalized_non_initial_possible_proper_noun")

        candidates = self._candidates(word)
        unsegmented = self._candidate(None, None, word, None)
        supported = [c for c in candidates if c["root_in_lexicon"]]

        if supported:
            pool = supported
            r["flags"].append("root_found_in_lexicon")
        else:
            pool = candidates + [unsegmented]
        pool.sort(key=self._score, reverse=True)
        best, rest = pool[0], pool[1:]

        if len(supported) > 1:
            r["needs_review"] = True
            r["flags"].append("ambiguous_segmentation")
        elif not supported and rest and self._score(rest[0]) == self._score(best):
            r["needs_review"] = True
            r["flags"].append("ambiguous_segmentation")

        r["segmentation"] = best
        r["alternatives"] = rest
        return r

    # ---------------------------------------------------------- morphology
    def _valid_root(self, root):
        return (bool(root) and not root.startswith("-") and not root.endswith("-")
                and len(orth.graphemes(root)) >= 2 and orth.has_vowel(root))

    def _candidate(self, prefix, reduplicant, root, suffix):
        lex = self.roots.get(orth.fold(root), [])
        return {
            "prefixes": [prefix] if prefix else [],
            "reduplicant": reduplicant,
            "root": root,
            "suffixes": [suffix] if suffix else [],
            "root_in_lexicon": bool(lex),
            "root_lexicon": lex,
        }

    @staticmethod
    def _score(c):
        # lexicon support > longer prefix > reduplication found > fewer suffixes.
        # Unsupported suffix guesses rank below "unsegmented" because root-final
        # -an/-on is common in roots (e.g. seed 'uran').
        prefix_len = len(c["prefixes"][0].strip("-")) if c["prefixes"] else 0
        return (c["root_in_lexicon"], prefix_len, c["reduplicant"] is not None, -len(c["suffixes"]))

    def _reduplications(self, stem):
        """Return (reduplicant, root) pairs. A reduplicated form is one root."""
        out = []
        if "-" in stem:
            parts = stem.split("-")
            if len(parts) == 2 and orth.fold(parts[0]) == orth.fold(parts[1]) and self._valid_root(parts[1]):
                out.append(({"type": "full", "form": parts[0] + "-"}, parts[1]))
            return out
        g = orth.graphemes(stem)
        half = len(g) // 2
        if len(g) % 2 == 0 and half >= 2:
            first, second = "".join(g[:half]), "".join(g[half:])
            if orth.fold(first) == orth.fold(second) and self._valid_root(second):
                out.append(({"type": "full", "form": first}, second))
        if (len(g) >= 4 and not orth.is_vowel(g[0]) and orth.is_vowel(g[1])
                and orth.fold(g[2]) == orth.fold(g[0]) and orth.fold(g[3]) == orth.fold(g[1])):
            root = "".join(g[2:])
            if self._valid_root(root):
                out.append(({"type": "CV", "form": g[0] + g[1]}, root))
        return out

    def _candidates(self, word):
        found, seen = [], set()

        def add(prefix, red, root, suffix):
            key = (prefix, red["form"] if red else None, root, suffix)
            if key not in seen and self._valid_root(root):
                seen.add(key)
                found.append(self._candidate(prefix, red, root, suffix))

        # 1. reduplication on the whole word, before any affix stripping
        for red, root in self._reduplications(word):
            add(None, red, root, None)

        # 2. affix stripping, then reduplication on the remaining stem
        for prefix in [None] + self.prefixes:
            rest = word
            if prefix:
                p = prefix.rstrip("-")
                if not word.startswith(p):
                    continue
                rest = word[len(p):]
                if rest.startswith("-"):  # hyphenated spelling, e.g. prefix-root
                    rest = rest[1:]
            for suffix in [None] + self.suffixes:
                stem = rest
                if suffix:
                    s = suffix.lstrip("-")
                    if not rest.endswith(s):
                        continue
                    stem = rest[:-len(s)]
                if not prefix and not suffix:
                    continue
                add(prefix, None, stem, suffix)
                for red, root in self._reduplications(stem):
                    add(prefix, red, root, suffix)
        return found


def main(argv=None):
    parser = argparse.ArgumentParser(description="Segment Viracnon text (seed lexicon; rule-based).")
    parser.add_argument("text", nargs="?", help="text to segment (default: read stdin)")
    parser.add_argument("--tokens-only", action="store_true", help="only print surface tokens")
    args = parser.parse_args(argv)
    text = args.text if args.text is not None else sys.stdin.read()
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    seg = Segmenter()
    out = seg.tokenize(text) if args.tokens_only else seg.segment(text)
    print(json.dumps(out, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
