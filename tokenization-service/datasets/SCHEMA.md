# Barangay SAGIP labeled incident-report dataset

A labeled set of resident incident reports for training and evaluating the
Barangay SAGIP incident classifier (`tokenizer_classifier.py`).

| | |
|---|---|
| Canonical file | `incident_reports.csv` (UTF-8, `\n` line endings, RFC 4180 quoting) |
| Generated copy | `incident_reports.json` |
| Rows | 150 (30 per category) |
| Split | 105 train / 45 test, stratified 21/9 within every category |
| Provenance | Synthetic, hand-written to mirror how residents of Virac, Catanduanes actually phrase reports |

The CSV is the **single source of truth**. The JSON file is regenerated from it
by `evaluate_classifier.py --export-json`, and `tests/test_dataset.py` fails if
the two drift apart. Never hand-edit the JSON.

## Columns

| Column | Type | Required | Description |
|---|---|---|---|
| `id` | string, `SAGIP-NNNN` | yes | Stable unique identifier. Never reuse an id; append new rows with the next number. |
| `text` | string, ≥15 chars | yes | The report exactly as a resident would submit it, including code-switching, typos-in-spirit, and honorifics (`po`, `ho`). |
| `category` | enum | yes | Ground-truth incident category. See **Category vocabulary**. |
| `urgency` | enum | yes | Ground-truth urgency. See **Urgency vocabulary**. |
| `language` | enum | yes | `fil` (Filipino/Tagalog), `bikol`, `en` (English), `mixed` (code-switched). |
| `split` | enum | yes | `train` or `test`. Evaluate on `test` only; report `all` for a full-corpus figure. |
| `source` | enum | yes | `synthetic` (written for this set) or `historical` (transcribed from a real logged report). |
| `notes` | string | no | Why the row is interesting — populated for deliberate hard cases. |

### Category vocabulary

These are the labels the service actually emits, so the dataset is directly
usable against `classify_category()` with no translation layer.

| Label | Covers | Responder specialization |
|---|---|---|
| `medical` | Injury, illness, childbirth, ambulance requests, health-centre needs | Medical |
| `fire` | Active fire, smoke, LPG leaks, electrical burning, fire-safety requests | Fire / Disaster |
| `peace_order` | Violence, weapons, theft, harassment, noise, blotter requests | Peace & Order, Tanod |
| `disaster` | Flooding, landslide, earthquake, typhoon and storm damage, drainage failure | Fire / Disaster, Weather |
| `general_assistance` | Aid, certificates, permits, utilities, livelihood, everything else | General Assistant |

**On "weather":** the feature brief names the categories as *weather, medical,
fire, peace & order, general*. This application has always used `disaster` for
the weather/storm/flood class, and the classifier's keyword dictionary is keyed
on it (`bagyo`, `ulan`, `baha`, `lindol`, `typhoon`). The dataset therefore uses
`disaster`, and the application exposes a separate **Weather** *specialization
tag* (`App\Enums\Specialization::Weather`) that routes to the `disaster`
category. `weather` is a responder tag; `disaster` is the incident label. There
is no third concept.

### Urgency vocabulary

| Label | Meaning |
|---|---|
| `critical` | Life is in danger right now; dispatch immediately. |
| `high` | Serious and worsening; dispatch within the hour. |
| `average` | Real need, no immediate danger; handle on the normal queue. |
| `low` | An enquiry or a request for information; no dispatch. |

Distribution: 33 critical, 41 high, 45 average, 31 low.

## Labeling rules

1. **Label the response the barangay owes, not the words used.** "Napaso ang bata
   sa kumukulong tubig" contains the fire keyword `paso` but needs a medic, so it
   is `medical`.
2. **When two services are needed, label the one that must arrive first.**
   "Binaril ang kapitbahay namin, dumudugo siya" is `peace_order` — the scene has
   to be made safe before a medic can work in it. The `notes` column records the
   ambiguity.
3. **An enquiry about a topic is not an incident of that topic.** "Kailan po ang
   fire safety seminar" is `fire` by subject but `low` urgency — it is a
   question, and urgency is what keeps it off the dispatch queue.
4. **Aid after an incident is `general_assistance`.** "Nawalan ng bahay sa sunog,
   saan po kami pwedeng manatili" is a shelter request, not a fire report.
5. **Ground truth is never copied from the classifier's output.** Six rows
   (`notes` populated) exist specifically because the keyword matcher gets them
   wrong.

## Running the evaluation

From the `tokenization-service` directory:

```bash
python datasets/evaluate_classifier.py                 # held-out test split
python datasets/evaluate_classifier.py --split all     # whole corpus
python datasets/evaluate_classifier.py --show-errors   # list every miss
python datasets/evaluate_classifier.py --export-json   # regenerate the JSON copy
```

Guard the dataset itself with:

```bash
PYTHONPATH=. python tests/test_dataset.py
```

## Baseline measurements

Measured against `tokenizer_classifier.py` as it stands today. These are a
*baseline to improve on*, not a target that must be held.

| Split | Category accuracy | Urgency accuracy |
|---|---|---|
| `test` (45 rows) | 0.844 | 0.600 |
| `all` (150 rows) | 0.813 | 0.633 |

Per-category on the full corpus:

| Label | Precision | Recall | F1 |
|---|---|---|---|
| `medical` | 0.900 | 0.900 | 0.900 |
| `fire` | 0.960 | 0.800 | 0.873 |
| `peace_order` | 1.000 | 0.767 | 0.868 |
| `disaster` | 0.920 | 0.767 | 0.836 |
| `general_assistance` | 0.532 | 0.833 | 0.649 |

### What the numbers already tell us

- **`general_assistance` precision is the weak point (0.532).** It is the
  classifier's fallback label, so every report whose wording matches no keyword
  lands there. Of the 47 reports it claims, 25 are correct and 22 are false
  positives — and 20 of those 22 matched *no keyword at all* (confidence 0.0),
  meaning they were not misclassified so much as never classified. Precision
  here is a direct measure of dictionary coverage, and it is the number to watch
  when adding keywords.
- **Urgency `high` recall is 0.195.** `average` is the urgency fallback, and
  32 of the 41 `high` rows collapse into it. The urgency dictionary needs many
  more mid-severity phrases before urgency-based prioritisation can be trusted.
- **`critical` precision is 1.000 with recall 0.515.** The classifier never
  cries wolf, but it misses roughly half of the genuinely critical reports —
  which is precisely why the application treats an SOS as critical by
  construction instead of asking the classifier
  (`App\Services\SosService`), and why low-confidence results are routed to
  manual review.

## Extending the dataset

- Append rows; never renumber or reuse an `id`.
- Keep the categories balanced at an equal count, and keep the per-category
  train/test ratio at 21/9 — `tests/test_dataset.py` enforces both.
- Real reports are more valuable than synthetic ones. When transcribing one,
  set `source` to `historical` and strip every name, phone number, and house
  number first.
- After any change: regenerate the JSON, run the dataset tests, then re-run the
  evaluation and update the baseline table above.
