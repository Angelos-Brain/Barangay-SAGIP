# Viracnon rule-based tokenizer / morpheme segmenter

A rule-based word and morpheme segmenter for **Viracnon — Southern Catanduanes Bikol
(ISO 639-3: `bln`)**, the Coastal Bikol language spoken in and around Virac, Catanduanes.
Viracnon is a sister language of Central Bikol / Bikol Naga (`bcl`), **not** the same language.

> **The lexicon here is a SEED lexicon — a small starting point, not a complete or full
> dictionary.** No fully digitized, complete Viracnon dictionary is publicly available.
> Expand it only from the primary sources listed below.

## Layout

| Path | Purpose |
|---|---|
| `lexicon/viracnon_seed.json` | Seed lexicon: `{word, gloss, pos, affix_analysis, confidence, source, notes?}` |
| `rules/bikol_morphology.json` | Affixes, discourse particles, Naga contrast forms (all from the project brief) |
| `tokenizer/orthography.py` | NFC normalization, accent folding, grapheme splitting (`ḽ` and `ng` = one unit) |
| `tokenizer/segmenter.py` | Tokenizer + segmenter (`Segmenter` class and command-line tool) |
| `tests/test_segmenter.py` | Unit tests (golden sentence + seed-only sentences + rule tests) |

No third-party dependencies: only the Python 3 standard library (`re`, `unicodedata`, `json`).

## Usage

```bash
python tokenizer/segmenter.py "Ngatà daw ta dài nagḽayog an gamgam ni Pedro maski na daing kandado su hawla?"
python tokenizer/segmenter.py --tokens-only "Dài su gamgam."
python -m unittest discover -s tests -v
```

```python
from tokenizer import Segmenter
seg = Segmenter()
seg.tokenize(text)   # surface tokens with spans
seg.segment(text)    # per-token analysis
```

## How segmentation works

1. **Normalize** to NFC (decomposed `l` + U+032D becomes the single code point `ḽ`, U+1E3D).
2. **Tokenize** into words (letters, combining marks, internal `-`/`'`), numbers and punctuation.
3. **Multiword entries** (`maski na`) are merged, but only across whitespace, never across punctuation.
4. For each word:
   - exact lexicon match → `LEXICON` (confidence `high`)
   - discourse particle (`daw man na pa gayod garo dawà maski bagá`) → `PARTICLE`, never affix-stripped
   - accent-insensitive match (`ngata` → `ngatà`) → `LEXICON_FOLDED` (confidence `medium`).
     `ḽ` is **never** folded to `l`.
   - otherwise → `UNK`, confidence `low`, **`gloss: null`**, plus a best-guess split:
     1. reduplication (full `X X` / `X-X`, or `CV-`) is checked on the whole word **before** affix stripping;
     2. prefixes (`mang- nang- mag- nag- pag- ig- ma- na- i-`) and suffixes (`-on -an`) are stripped,
        then reduplication is checked again on the remaining stem (e.g. `nag-` + `ta` + `taram`);
     3. candidates whose root is in the lexicon win. Otherwise the tokenizer prefers a longer prefix,
        then a reduplication, then fewer suffixes. A suffix-only guess with no lexicon root ranks below
        leaving the word unsegmented, because root-final *-an* is common (seed: *uran*).
   - If a candidate root is in the lexicon, **that root's** entry is shown under `root_lexicon`.
     The unknown word itself never gets a gloss.
5. **`needs_review: true`** is set when two lexicon-supported analyses compete, when unsupported
   guesses tie, or when an accent-folded match is also a Naga contrast form (e.g. unaccented `dai`).
6. Function words (pronouns, articles, negation, interrogatives) are not used as roots inside
   unknown words.

Lexicon entries are checked first, so lexemes that only *look* reduplicated (`gamgam`, `lalaki`, `babayi`)
are never split.

## Confirmed vs. cognate-probable

### CONFIRMED-VIRAC (34 entries)
Marked as attested for Virac in the project brief (9):
`ngatà` (why), `dài` (not/no), `daing` (without/there is no), `nagḽayog` (flew; `nag-` + root `layog` "fly"),
`gamgam` (bird), `su` (definite/topic marker), `maski na` (although), `kandado` (lock), `hawla` (cage).

Pronouns upgraded after checking the Peace Corps *Viracnon Language Packet* (1990) (25). Each entry's
`source` field gives the exact page.
- **"Learning Viracnon Fast", List of Pronouns, printed p. 15 (7):** `ako` "I", `ika` "You (singular)", `siya` "He, She",
  `kami` "We (excluding listener)", `kita` "We (including listener)", `kamo` "You (plural)", `sinda` "They".
- **Grammar Notes appendix, I. Pronouns, printed pp. 290–294 (18):**
  - Subject Set: `ka` (listed as KA/IKA; "KA may never be used at the beginning of the sentence").
  - Non-Subject Set (the brief's ergative): `ko`, `mo`, `niya`, `mi` (exclusive), `ta` (inclusive), `nindo`, `ninda`.
  - Possessive / Location / Benefactive sets (the brief's oblique): `sakuya`, `saimo`, `saiya`, `satuya` (inclusive),
    `samuya` (exclusive), `saindo`, `sainda`, `sakô`, `satô` (inclusive), `samô` (exclusive).

The packet confirms each form, its person/number, and inclusive vs. exclusive. It does **not** use the labels
"absolutive", "ergative" or "oblique"; those still come from the brief's paradigm table.
**Spelling:** the packet uses no accent marks. It writes SAKO, SATO and SAMO, and the circumflexes in `sakô`,
`satô` and `samô` come from the brief. Those entries carry a `SPELLING:` note.
**Packet misprints** (noted on `saindo`): p. 292 prints the pre-posted "your (plural)" form as NINDO + NG, and
p. 293 labels SA SAINDO as "us (inclusive)". The packet's own examples contradict both.

### COGNATE-PROBABLE (36 entries)
Bikol-wide forms, **not** independently confirmed for Virac:
- **Pronouns (3):** `niato`, `niamo`, `sìmo`. None of them appears in the packet's Grammar Notes: its Non-Subject Set
  gives only TA and MI for "we", and it has SAIMO but no sìmo. `sìmo` comes from the brief's pronoun table
  rather than the seed list and was added at user request.
- **Numerals (10):** sarô, duwá, tuló, apát, limá, anóm, pitó, waló, siyám, sampulò
- **Roots (18):** taram, hiling, kapot, kamot, mata, payo, tubig, uran, bagyo, sira, harong, balay,
  lalaki, babayi, maray, marhay, dakul, dakol
- **Loanwords (5):** swerte (luck), karne (meat), litro (liter), pero (but), krimen (crime).
  The brief gave no glosses. These glosses were added at user request and are the meanings of the Spanish
  source words (suerte, carne, litro, pero, crimen). They have **not** been verified for Virac usage.

The affixes and discourse particles in `rules/bikol_morphology.json` are also COGNATE-PROBABLE
(shared Bikol morphology).

### Not in the lexicon
- Naga (`bcl`) forms `an`, `dai`, `mayò`, `tâno` are **not** Viracnon entries. The segmenter only attaches a
  "Naga contrast form" note to them.

## Known gaps (need source data, deliberately NOT implemented)
- l ~ ḽ alternation (root `layog` → surface `ḽayog`): there is only one datum, so it is not a rule.
- Infixes (e.g. -um-, -in-), nasal assimilation of `mang-`/`nang-`, and suffix allomorphs (e.g. after
  vowel-final roots) are absent from the brief and unimplemented.
- Stacked prefixes and word-final u/o spelling variation are not handled.
- The golden sentence contains `an` (listed in the brief as the Naga marker) alongside Virac `su`,
  plus `ni` and `Pedro`. All three are output as `UNK`. `an` gets its contrast note, and `Pedro` is
  flagged as a possible proper noun.

## Primary sources for expanding the lexicon
Consult these before adding **any** entry. Record the source and a page or entry reference in the `source` field.

1. **SIL Language & Culture Archives**: "Bicolano (Virac, Catanduanes) wordlist" (1966 Expanded
   Philippine Word List), entry #76825. <https://www.sil.org/resources/archives/76825>
2. **Peace Corps (1990)**: *Viracnon Language Packet*, with a Viracnon–English glossary.
   <https://www.livelingua.com/course/peace-corps/Viracnon_Language_Packet>
3. **McFarland, C. (1974)**: *The Dialects of the Bikol Area*. PhD dissertation, Yale University.
   Available via ASJP: <https://asjp.clld.org/languages/SOUTHERN_CATANDUANES>
4. **Lobel, J., Tria, W., & Carpio, J. (2000)**: *An Satuyang Tataramon / A Study of the Bikol Language*.
   Naga City: Lobel & Tria Partnership.

Rules for expansion:
- A new entry is `CONFIRMED-VIRAC` only if a Virac-specific source (1, 2 or 3) attests it.
  Bikol-wide or Naga-based evidence (e.g. source 4) gives at most `COGNATE-PROBABLE`.
- Never upgrade `COGNATE-PROBABLE` to `CONFIRMED-VIRAC` without a citation.
- Show a diff before editing or overwriting `lexicon/viracnon_seed.json`.
