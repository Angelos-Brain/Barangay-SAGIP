"""
Feature 5 — guards the labeled dataset itself.

These tests do not assert a particular classifier accuracy (that is expected to
move as the keyword dictionaries are tuned). They assert the dataset stays
well-formed, balanced, and stratified, because a silently corrupted evaluation
set produces confident nonsense.
"""
import csv
import io
import json
import os
import unittest
from collections import Counter

DATASET_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "datasets")
CSV_PATH = os.path.join(DATASET_DIR, "incident_reports.csv")
JSON_PATH = os.path.join(DATASET_DIR, "incident_reports.json")

EXPECTED_FIELDS = ["id", "text", "category", "urgency", "language", "split", "source", "notes"]
CATEGORIES = ["medical", "fire", "peace_order", "disaster", "general_assistance"]
URGENCIES = ["critical", "high", "average", "low"]
LANGUAGES = ["fil", "bikol", "en", "mixed"]


def load_csv_rows():
    with io.open(CSV_PATH, encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


class DatasetSchemaTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.rows = load_csv_rows()

    def test_the_csv_has_exactly_the_documented_columns(self):
        with io.open(CSV_PATH, encoding="utf-8", newline="") as handle:
            header = next(csv.reader(handle))

        self.assertEqual(EXPECTED_FIELDS, header)

    def test_every_row_is_complete_and_uniquely_identified(self):
        seen = set()

        for row in self.rows:
            self.assertRegex(row["id"], r"^SAGIP-\d{4}$")
            self.assertNotIn(row["id"], seen, f"duplicate id {row['id']}")
            seen.add(row["id"])

            self.assertTrue(row["text"].strip(), f"{row['id']} has empty text")
            self.assertGreaterEqual(len(row["text"]), 15, f"{row['id']} text is too short to be a real report")

    def test_every_label_is_from_the_documented_vocabulary(self):
        for row in self.rows:
            self.assertIn(row["category"], CATEGORIES, row["id"])
            self.assertIn(row["urgency"], URGENCIES, row["id"])
            self.assertIn(row["language"], LANGUAGES, row["id"])
            self.assertIn(row["split"], ("train", "test"), row["id"])
            self.assertIn(row["source"], ("synthetic", "historical"), row["id"])

    def test_no_duplicate_report_text(self):
        counts = Counter(row["text"].strip().lower() for row in self.rows)
        duplicates = [text for text, count in counts.items() if count > 1]

        self.assertEqual([], duplicates, f"duplicated report text: {duplicates}")

    def test_the_categories_are_balanced(self):
        counts = Counter(row["category"] for row in self.rows)

        self.assertEqual(set(CATEGORIES), set(counts))
        self.assertEqual({30}, set(counts.values()), f"unbalanced categories: {dict(counts)}")

    def test_the_split_is_stratified_across_every_category(self):
        for category in CATEGORIES:
            rows = [row for row in self.rows if row["category"] == category]
            test_rows = [row for row in rows if row["split"] == "test"]

            self.assertEqual(
                9, len(test_rows),
                f"{category} contributes {len(test_rows)} test rows instead of 9",
            )

    def test_every_urgency_level_is_represented(self):
        counts = Counter(row["urgency"] for row in self.rows)

        for urgency in URGENCIES:
            self.assertGreaterEqual(counts[urgency], 20, f"{urgency} is under-represented")

    def test_the_hard_cases_carry_an_explanatory_note(self):
        annotated = [row for row in self.rows if row["notes"].strip()]

        self.assertGreaterEqual(
            len(annotated), 5,
            "the set should keep documented hard cases where a keyword misleads the classifier",
        )


class DatasetJsonExportTest(unittest.TestCase):
    def test_the_json_export_matches_the_csv_row_for_row(self):
        """The JSON copy is generated from the CSV; drift means it went stale."""
        self.assertTrue(
            os.path.exists(JSON_PATH),
            "run `python datasets/evaluate_classifier.py --export-json` to regenerate",
        )

        with io.open(JSON_PATH, encoding="utf-8") as handle:
            payload = json.load(handle)

        csv_rows = load_csv_rows()

        self.assertEqual(CATEGORIES, payload["categories"])
        self.assertEqual(URGENCIES, payload["urgencies"])
        self.assertEqual(len(csv_rows), payload["count"])
        self.assertEqual(csv_rows, payload["records"])


if __name__ == "__main__":
    unittest.main()
