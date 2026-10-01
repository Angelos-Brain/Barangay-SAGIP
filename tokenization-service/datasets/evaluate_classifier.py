"""
Feature 5 — evaluate the tokenization classifier against the labeled test set.

Usage (from the tokenization-service directory):

    python datasets/evaluate_classifier.py                 # held-out split only
    python datasets/evaluate_classifier.py --split all     # every row
    python datasets/evaluate_classifier.py --export-json   # also write the JSON copy
    python datasets/evaluate_classifier.py --show-errors   # list each miss

The JSON export is generated from the CSV rather than maintained alongside it,
so the two can never drift apart. The CSV is the single source of truth.
"""
from __future__ import annotations

import argparse
import csv
import json
import os
import sys
from collections import Counter, defaultdict
from typing import Dict, List

HERE = os.path.dirname(os.path.abspath(__file__))
SERVICE_ROOT = os.path.dirname(HERE)
sys.path.insert(0, SERVICE_ROOT)

from tokenizer_classifier import classify_category, classify_urgency  # noqa: E402

CSV_PATH = os.path.join(HERE, "incident_reports.csv")
JSON_PATH = os.path.join(HERE, "incident_reports.json")

CATEGORIES = ["medical", "fire", "peace_order", "disaster", "general_assistance"]
URGENCIES = ["critical", "high", "average", "low"]


def load_rows(split: str = "test") -> List[Dict[str, str]]:
    with open(CSV_PATH, encoding="utf-8", newline="") as handle:
        rows = list(csv.DictReader(handle))

    if split != "all":
        rows = [row for row in rows if row["split"] == split]

    return rows


def validate(rows: List[Dict[str, str]]) -> None:
    """Fail loudly on a malformed dataset rather than reporting nonsense metrics."""
    seen_ids = set()

    for row in rows:
        assert row["id"], "row is missing an id"
        assert row["id"] not in seen_ids, f"duplicate id {row['id']}"
        seen_ids.add(row["id"])

        assert row["text"].strip(), f"{row['id']} has empty text"
        assert row["category"] in CATEGORIES, f"{row['id']} has bad category {row['category']!r}"
        assert row["urgency"] in URGENCIES, f"{row['id']} has bad urgency {row['urgency']!r}"
        assert row["split"] in {"train", "test"}, f"{row['id']} has bad split {row['split']!r}"


def score(rows, labels, predict, label_key):
    correct = 0
    confusion = defaultdict(Counter)
    misses = []

    for row in rows:
        predicted, confidence, _ = predict(row["text"])
        expected = row[label_key]
        confusion[expected][predicted] += 1

        if predicted == expected:
            correct += 1
        else:
            misses.append((row, expected, predicted, confidence))

    accuracy = correct / len(rows) if rows else 0.0
    return accuracy, confusion, misses


def per_label_metrics(confusion, labels):
    """Precision, recall, and F1 per label, computed from the confusion matrix."""
    report = {}

    for label in labels:
        true_positive = confusion[label][label]
        actual = sum(confusion[label].values())
        predicted = sum(confusion[other][label] for other in labels)

        precision = true_positive / predicted if predicted else 0.0
        recall = true_positive / actual if actual else 0.0
        f1 = (2 * precision * recall / (precision + recall)) if (precision + recall) else 0.0

        report[label] = {
            "support": actual,
            "precision": round(precision, 3),
            "recall": round(recall, 3),
            "f1": round(f1, 3),
        }

    return report


def print_report(title, accuracy, confusion, labels):
    print(f"\n{title}")
    print("-" * len(title))
    print(f"accuracy: {accuracy:.3f}")

    report = per_label_metrics(confusion, labels)
    width = max(len(label) for label in labels)

    print(f"\n{'label'.ljust(width)}  support  precision  recall     f1")
    for label in labels:
        metrics = report[label]
        print(
            f"{label.ljust(width)}  {str(metrics['support']).rjust(7)}"
            f"  {metrics['precision']:>9.3f}  {metrics['recall']:>6.3f}  {metrics['f1']:>5.3f}"
        )

    print("\nconfusion matrix (rows = expected, columns = predicted)")
    print("".ljust(width + 2) + "  ".join(label[:6].rjust(6) for label in labels))
    for expected in labels:
        cells = "  ".join(str(confusion[expected][predicted]).rjust(6) for predicted in labels)
        print(expected.ljust(width + 2) + cells)


def export_json(rows) -> None:
    payload = {
        "name": "Barangay SAGIP labeled incident reports",
        "version": 1,
        "categories": CATEGORIES,
        "urgencies": URGENCIES,
        "count": len(rows),
        "records": rows,
    }

    with open(JSON_PATH, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, ensure_ascii=False, indent=2)
        handle.write("\n")

    print(f"wrote {JSON_PATH} ({len(rows)} records)")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--split", default="test", choices=["train", "test", "all"])
    parser.add_argument("--export-json", action="store_true")
    parser.add_argument("--show-errors", action="store_true")
    args = parser.parse_args()

    all_rows = load_rows("all")
    validate(all_rows)
    print(f"dataset: {len(all_rows)} rows, {CSV_PATH}")
    print("per category:", dict(Counter(row["category"] for row in all_rows)))
    print("per split:   ", dict(Counter(row["split"] for row in all_rows)))

    if args.export_json:
        export_json(all_rows)

    rows = load_rows(args.split)
    print(f"\nevaluating the {args.split!r} split ({len(rows)} rows)")

    category_accuracy, category_confusion, category_misses = score(
        rows, CATEGORIES, classify_category, "category"
    )
    print_report("CATEGORY", category_accuracy, category_confusion, CATEGORIES)

    urgency_accuracy, urgency_confusion, urgency_misses = score(
        rows, URGENCIES, classify_urgency, "urgency"
    )
    print_report("URGENCY", urgency_accuracy, urgency_confusion, URGENCIES)

    if args.show_errors:
        for title, misses in (("CATEGORY", category_misses), ("URGENCY", urgency_misses)):
            print(f"\n{title} misses ({len(misses)})")
            for row, expected, predicted, confidence in misses:
                print(f"  {row['id']}  expected={expected:<19} got={predicted:<19} conf={confidence:.2f}")
                print(f"           {row['text']}")
                if row["notes"]:
                    print(f"           note: {row['notes']}")

    print(
        f"\nsummary: category accuracy {category_accuracy:.3f}, "
        f"urgency accuracy {urgency_accuracy:.3f} on the {args.split!r} split"
    )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
