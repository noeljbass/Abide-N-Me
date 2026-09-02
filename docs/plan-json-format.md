# Reading plan JSON format

The Plans tab has a third build mode, **JSON**, which accepts a pasted plan,
previews it, and creates it. This file is the contract that paste has to satisfy.
It is written to be handed to a bot as its instructions.

A pasted plan becomes a *manual* plan: one reading day per entry in `days`, on
consecutive calendar dates starting from the start date. There are no rest days.

## The shape

```json
{
  "name": "Advent in Luke",
  "description": "Four weeks in the infancy narratives, a short reading each morning.",
  "translation": "BSB",
  "start_date": "2026-12-01",
  "days": [
    {
      "title": "A promise in the temple",
      "passages": ["Luke 1:1-25"],
      "note": "Luke begins with an ordinary man at his work.",
      "question": "Where are you being asked to wait?"
    },
    {
      "title": "The angel and Mary",
      "passages": ["Luke 1:26-38"],
      "question": "What does Mary's yes cost her?"
    },
    {
      "passages": ["Luke 1:39-56", "Psalm 113"]
    }
  ]
}
```

That example is complete and valid. Day 1 shows every field; day 2 omits the
note; day 3 is the bare minimum and carries two passages.

## Fields

| Field | Required | Limit | Notes |
| --- | --- | --- | --- |
| `name` | yes | 2–150 characters | The plan's title. |
| `days` | yes | 1–730 entries | One entry per reading day. |
| `description` | no | ≤ 5000 characters | Shown on the plan. |
| `translation` | no | — | `DRA1899`, `KJV`, `BSB` or `WEB-C`. Defaults to `DRA1899`. |
| `start_date` | no | `YYYY-MM-DD` | Can also be chosen in the app; a value here fills that field in. |

Each entry in `days`:

| Field | Required | Limit | Notes |
| --- | --- | --- | --- |
| `passages` | yes | 1–20 references | An array of strings. A single string is accepted and split on newlines. |
| `title` | no | ≤ 150 characters | A heading for the day. |
| `note` | no | ≤ 5000 characters | A short reflection shown under the passages. |
| `question` | no | ≤ 5000 characters | A discussion question for the group. |

Omit an optional field or set it to `null`. Do not invent other fields; they are
ignored.

## Writing passage references

References are parsed on the server, and an unrecognised one rejects the whole
plan. These forms all work:

| Form | Example | Means |
| --- | --- | --- |
| Whole chapter | `Luke 1` | all of Luke 1 |
| Single verse | `Luke 1:5` | one verse |
| Verses in a chapter | `Luke 1:1-25` | a range inside one chapter |
| Chapter range | `Luke 1-3` | three whole chapters |
| Across chapters | `Luke 1:26-2:20` | from 1:26 through 2:20 |
| Across books | `Genesis 1:1-Exodus 2:3` | rare, but accepted |

Rules that matter:

- **Use the full English book name**: `Genesis`, `1 Samuel`, `Psalms`, `Song of
  Solomon`, `Revelation`. Abbreviations often resolve but full names always do.
- A number in a book name is separated by a space: `1 Samuel`, never `1Samuel`.
- A range cannot end before it starts.
- A range that gives an ending verse must give a starting verse: `Luke 1:1-25` is
  fine, `Luke 1-1:25` is not.
- Every reference must exist in the chosen translation. `DRA1899` includes the
  deuterocanonical books; `KJV`, `BSB` and `WEB-C` are 66-book translations, so a
  plan naming Sirach or Wisdom must use `DRA1899`.

## What the preview shows

Pasting and choosing **Preview this plan** reports the reading day count, the
date range, the translation, the books covered, and the total passage count,
then every day in order with its title, passages, note and question. Nothing is
saved until **Create and assign plan** is chosen, and the group is always picked
in the app rather than in the JSON.

## Instructions for a bot

> You produce reading plans for the Abide N Me app as a single JSON object and
> nothing else — no commentary, no markdown fence, no explanation.
>
> Follow the schema above exactly. Every day needs at least one passage. Use full
> English book names. Keep daily readings to a length a family can read aloud in
> the time the user asks for, roughly 25–40 verses for ten minutes. Write titles
> as short phrases rather than sentences. Where the user asks for reflections or
> discussion questions, put one per day in `note` and `question`; otherwise omit
> them. If the user names books outside the 66-book canon, set `translation` to
> `DRA1899`.
>
> Before answering, check: is it valid JSON, does every day have passages, is
> every reference in a supported form, and is the day count what the user asked
> for?
