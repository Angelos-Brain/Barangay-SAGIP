"""Unit tests for the Viracnon segmenter.

Run from the project root:  python -m unittest discover -s tests -v
"""

import json
import sys
import unicodedata
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from tokenizer import Segmenter  # noqa: E402
from tokenizer import orthography as orth  # noqa: E402

GOLDEN = "Ngatà daw ta dài nagḽayog an gamgam ni Pedro maski na daing kandado su hawla?"


class GoldenSentenceTest(unittest.TestCase):
    """Golden test case, a CONFIRMED-VIRAC sentence supplied in the project brief."""

    @classmethod
    def setUpClass(cls):
        cls.seg = Segmenter()
        cls.out = cls.seg.segment(GOLDEN)

    def test_surface_tokens(self):
        self.assertEqual(
            [t["surface"] for t in self.seg.tokenize(GOLDEN)],
            ["Ngatà", "daw", "ta", "dài", "nagḽayog", "an", "gamgam", "ni", "Pedro",
             "maski", "na", "daing", "kandado", "su", "hawla", "?"],
        )

    def test_segmented_tokens_and_status(self):
        self.assertEqual(
            [(t["surface"], t["status"]) for t in self.out],
            [("Ngatà", "LEXICON"), ("daw", "PARTICLE"), ("ta", "LEXICON"), ("dài", "LEXICON"),
             ("nagḽayog", "LEXICON"), ("an", "UNK"), ("gamgam", "LEXICON"), ("ni", "UNK"),
             ("Pedro", "UNK"), ("maski na", "LEXICON"), ("daing", "LEXICON"),
             ("kandado", "LEXICON"), ("su", "LEXICON"), ("hawla", "LEXICON"), ("?", "PUNCT")],
        )

    def test_confirmed_glosses(self):
        by_surface = {t["surface"]: t for t in self.out}
        expected = {"Ngatà": "why", "dài": "not/no", "daing": "without/there is no",
                    "nagḽayog": "flew", "gamgam": "bird", "maski na": "although/even though",
                    "kandado": "lock", "hawla": "cage"}
        for surface, gloss in expected.items():
            self.assertEqual(by_surface[surface]["gloss"], gloss)
            self.assertEqual(by_surface[surface]["lexicon_confidence"], "CONFIRMED-VIRAC")
        self.assertEqual(by_surface["ta"]["lexicon_confidence"], "CONFIRMED-VIRAC")  # packet Non-Subject Set

    def test_nagḽayog_segmentation(self):
        tok = next(t for t in self.out if t["surface"] == "nagḽayog")
        seg = tok["segmentation"]
        self.assertEqual(seg["prefixes"], ["nag-"])
        self.assertEqual(seg["root"], "ḽayog")
        self.assertEqual(seg["root_lemma"], "layog")
        self.assertEqual(orth.graphemes(seg["root"])[0], "ḽ")  # ḽ kept as one grapheme

    def test_gamgam_not_split_as_reduplication(self):
        tok = next(t for t in self.out if t["surface"] == "gamgam")
        self.assertIsNone(tok["segmentation"]["reduplicant"])
        self.assertEqual(tok["segmentation"]["root"], "gamgam")

    def test_unknown_words_have_no_gloss(self):
        for t in self.out:
            if t["status"] == "UNK":
                self.assertIsNone(t["gloss"])
                self.assertEqual(t["confidence"], "low")
                self.assertEqual(t["segmentation"]["prefixes"], [])
                self.assertEqual(t["segmentation"]["suffixes"], [])

    def test_an_is_flagged_as_naga_form_not_glossed(self):
        tok = next(t for t in self.out if t["surface"] == "an")
        self.assertIn("non_virac_contrast_form", tok["flags"])
        self.assertEqual(tok["segmentation"]["root"], "an")  # not stripped as suffix -an

    def test_pedro_flagged_as_possible_proper_noun(self):
        tok = next(t for t in self.out if t["surface"] == "Pedro")
        self.assertIn("capitalized_non_initial_possible_proper_noun", tok["flags"])

    def test_multiword_span(self):
        tok = next(t for t in self.out if t["surface"] == "maski na")
        start, end = tok["span"]
        self.assertEqual(GOLDEN[start:end], "maski na")

    def test_nothing_needs_review(self):
        self.assertFalse(any(t.get("needs_review") for t in self.out))


class SeedOnlySentenceTest(unittest.TestCase):
    """Token sequences built ONLY from seed-lexicon words to exercise the segmenter.
    They are NOT attested and NOT verified as grammatical Viracnon."""

    @classmethod
    def setUpClass(cls):
        cls.seg = Segmenter()

    def statuses(self, text):
        return [(t["surface"], t["status"]) for t in self.seg.segment(text)]

    def test_seed_sentence_1(self):
        self.assertEqual(
            self.statuses("Dài su gamgam."),
            [("Dài", "LEXICON"), ("su", "LEXICON"), ("gamgam", "LEXICON"), (".", "PUNCT")],
        )

    def test_seed_sentence_2(self):
        out = self.seg.segment("Ngatà daing tubig su harong?")
        self.assertEqual([t["status"] for t in out], ["LEXICON"] * 5 + ["PUNCT"])
        self.assertEqual([t.get("gloss") for t in out[:5]],
                         ["why", "without/there is no", "water", "the (definite/topic marker)", "house"])

    def test_seed_sentence_3_multiword_and_lookalike_reduplication(self):
        out = self.seg.segment("Maski na uran, dakul su lalaki, babayi.")
        surfaces = [t["surface"] for t in out]
        self.assertEqual(surfaces[0], "Maski na")
        self.assertFalse(any(t["status"] == "UNK" for t in out))
        by_surface = {t["surface"]: t for t in out}
        for w in ("lalaki", "babayi"):
            self.assertEqual(by_surface[w]["status"], "LEXICON")
            self.assertIsNone(by_surface[w]["segmentation"]["reduplicant"])
        self.assertEqual(by_surface["uran"]["segmentation"]["suffixes"], [])

    def test_seed_sentence_4_numbers(self):
        out = self.seg.segment("Sarô, duwá, tuló, apát, limá, anóm, pitó, waló, siyám, sampulò.")
        words = [t for t in out if t["status"] != "PUNCT"]
        self.assertEqual(len(words), 10)
        self.assertTrue(all(t["status"] == "LEXICON" and t["pos"] == "numeral" for t in words))

    def test_multiword_not_merged_across_punctuation(self):
        surfaces = [t["surface"] for t in self.seg.segment("maski, na")]
        self.assertEqual(surfaces, ["maski", ",", "na"])
        self.assertEqual(self.seg.segment("maski, na")[0]["status"], "PARTICLE")


class OrthographyTest(unittest.TestCase):
    def setUp(self):
        self.seg = Segmenter()

    def test_decomposed_lh_is_normalized(self):
        decomposed = "nag" + "ḽ" + "ayog"
        self.assertNotEqual(decomposed, "nagḽayog")
        r = self.seg.segment(decomposed)
        self.assertEqual(len(r), 1)
        self.assertEqual(r[0]["status"], "LEXICON")
        self.assertEqual(r[0]["gloss"], "flew")

    def test_lh_is_one_grapheme_and_never_folded_to_l(self):
        self.assertEqual(orth.graphemes("ḽayog"), ["ḽ", "a", "y", "o", "g"])
        self.assertEqual(orth.fold("NAGḼAYOG"), "nagḽayog")
        self.assertNotEqual(orth.fold("nagḽayog"), "naglayog")

    def test_ng_is_one_grapheme(self):
        self.assertEqual(orth.graphemes("ngatà")[0], "ng")

    def test_plain_l_does_not_match_lh_entry(self):
        r = self.seg.analyze_word("naglayog")
        self.assertEqual(r["status"], "UNK")
        self.assertIsNone(r["gloss"])

    def test_accent_insensitive_match_is_medium(self):
        r = self.seg.analyze_word("ngata")
        self.assertEqual(r["status"], "LEXICON_FOLDED")
        self.assertEqual(r["lemma"], "ngatà")
        self.assertEqual(r["confidence"], "medium")

    def test_unaccented_dai_needs_review(self):
        r = self.seg.analyze_word("dai")
        self.assertEqual(r["lemma"], "dài")
        self.assertTrue(r["needs_review"])
        self.assertIn("non_virac_contrast_form", r["flags"])


class MorphologyMechanismTest(unittest.TestCase):
    """Mechanism tests. Strings such as 'nagtaram' or 'tataram' are SYNTHETIC
    combinations of a seed root with an affix from the brief. They exercise the
    rules only and are NOT claimed to be attested Viracnon words."""

    def setUp(self):
        self.seg = Segmenter()

    def test_prefix_stripping_with_lexicon_root(self):
        r = self.seg.analyze_word("nagtaram")
        self.assertEqual(r["status"], "UNK")
        self.assertIsNone(r["gloss"])
        self.assertEqual(r["confidence"], "low")
        self.assertEqual(r["segmentation"]["prefixes"], ["nag-"])
        self.assertEqual(r["segmentation"]["root"], "taram")
        self.assertEqual(r["segmentation"]["root_lexicon"][0]["gloss"], "speak")

    def test_cv_reduplication(self):
        r = self.seg.analyze_word("tataram")
        seg = r["segmentation"]
        self.assertEqual(seg["reduplicant"], {"type": "CV", "form": "ta"})
        self.assertEqual(seg["root"], "taram")

    def test_prefix_then_cv_reduplication(self):
        seg = self.seg.analyze_word("nagtataram")["segmentation"]
        self.assertEqual((seg["prefixes"], seg["reduplicant"]["form"], seg["root"]),
                         (["nag-"], "ta", "taram"))

    def test_full_reduplication_hyphenated(self):
        seg = self.seg.analyze_word("dakul-dakul")["segmentation"]
        self.assertEqual(seg["reduplicant"], {"type": "full", "form": "dakul-"})
        self.assertEqual(seg["root"], "dakul")

    def test_suffix_with_lexicon_root(self):
        seg = self.seg.analyze_word("hilingon")["segmentation"]
        self.assertEqual((seg["root"], seg["suffixes"]), ("hiling", ["-on"]))

    def test_particles_never_stripped(self):
        for p in ("na", "man", "pa", "garo", "gayod", "maski"):
            r = self.seg.analyze_word(p)
            self.assertEqual(r["status"], "PARTICLE", p)
            self.assertEqual(r["segmentation"]["prefixes"], [])

    def test_function_words_not_used_as_roots(self):
        # 'na' + 'ta' must not be analysed as prefix na- + pronoun ta
        r = self.seg.analyze_word("nata")
        self.assertFalse(any(c["root_in_lexicon"] for c in [r["segmentation"]] + r["alternatives"]))

    def test_unsupported_suffix_guess_ranks_below_unsegmented(self):
        r = self.seg.analyze_word("zzzan")  # nonsense string
        self.assertEqual(r["segmentation"]["suffixes"], [])

    def test_two_lexicon_supported_analyses_need_review(self):
        # TEST-ONLY fake lexicon: nonsense roots, not Viracnon
        fake = [
            {"word": "zuzan", "gloss": "TEST-ONLY", "pos": "root", "affix_analysis": None,
             "confidence": "COGNATE-PROBABLE", "source": "test fixture"},
            {"word": "zuz", "gloss": "TEST-ONLY", "pos": "root", "affix_analysis": None,
             "confidence": "COGNATE-PROBABLE", "source": "test fixture"},
        ]
        r = Segmenter(lexicon_entries=fake).analyze_word("magzuzan")
        self.assertTrue(r["needs_review"])
        self.assertIn("ambiguous_segmentation", r["flags"])


class LexiconFileTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with open(ROOT / "lexicon" / "viracnon_seed.json", encoding="utf-8") as f:
            cls.data = json.load(f)
        cls.entries = cls.data["entries"]

    def test_required_fields(self):
        for e in self.entries:
            self.assertEqual(set(e) - {"notes"},
                             {"word", "gloss", "pos", "affix_analysis", "confidence", "source"}, e["word"])

    def test_confidence_values(self):
        for e in self.entries:
            self.assertIn(e["confidence"], {"CONFIRMED-VIRAC", "COGNATE-PROBABLE"})

    def test_confirmed_set_is_exactly_the_brief_plus_cited_upgrades(self):
        confirmed = sorted(e["word"] for e in self.entries if e["confidence"] == "CONFIRMED-VIRAC")
        brief = ["ngatà", "dài", "daing", "nagḽayog", "gamgam", "su", "maski na", "kandado", "hawla"]
        packet_pronouns = ["ako", "ika", "siya", "kami", "kita", "kamo", "sinda",          # Learning Viracnon Fast
                           "ka", "ko", "mo", "niya", "mi", "ta", "nindo", "ninda",          # Grammar Notes appendix
                           "sakuya", "saimo", "saiya", "satuya", "samuya", "saindo", "sainda",
                           "sakô", "satô", "samô"]
        self.assertEqual(confirmed, sorted(brief + packet_pronouns))

    def test_pronouns_absent_from_packet_stay_cognate_probable(self):
        by_word = {e["word"]: e for e in self.entries}
        for w in ("niato", "niamo", "sìmo"):
            self.assertEqual(by_word[w]["confidence"], "COGNATE-PROBABLE")
            self.assertIn("Not listed in the Peace Corps", by_word[w]["notes"])

    def test_accented_upgrades_carry_spelling_note(self):
        by_word = {e["word"]: e for e in self.entries}
        for w in ("sakô", "satô", "samô"):
            self.assertIn("SPELLING:", by_word[w]["notes"])

    def test_upgraded_entries_cite_a_virac_source(self):
        for e in self.entries:
            if e["confidence"] == "CONFIRMED-VIRAC" and "project brief seed list [CONFIRMED-VIRAC]" not in e["source"]:
                self.assertIn("Viracnon Language Packet", e["source"], e["word"])

    def test_loanword_glosses_are_cognate_probable_and_sourced(self):
        by_word = {e["word"]: e for e in self.entries}
        expected = {"swerte": "luck", "karne": "meat", "litro": "liter", "pero": "but", "krimen": "crime"}
        for w, gloss in expected.items():
            self.assertEqual(by_word[w]["gloss"], gloss)
            self.assertEqual(by_word[w]["confidence"], "COGNATE-PROBABLE")
            self.assertIn("Spanish etymon", by_word[w]["source"])

    def test_no_duplicates_and_nfc(self):
        words = [e["word"] for e in self.entries]
        self.assertEqual(len(words), len(set(words)))
        for w in words:
            self.assertEqual(w, unicodedata.normalize("NFC", w))

    def test_not_described_as_complete(self):
        self.assertIn("NOT a complete", self.data["_meta"]["status"])


if __name__ == "__main__":
    unittest.main()
