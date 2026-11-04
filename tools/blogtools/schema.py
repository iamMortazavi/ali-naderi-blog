"""Validate data against the JSON Schemas in schema/.

Implements the subset of JSON Schema those files use, so the build needs no
extra packages: type, required, properties (nested), enum, pattern, minLength,
maxLength, items, maxItems, "not": {} (forbidden property) and the custom
format "date-or-datetime".
"""

import json
import os
import re

from . import REPO_ROOT, SCHEMA_PATH

SETTINGS_SCHEMA_PATH = os.path.join(REPO_ROOT, "schema", "settings.schema.json")
DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?$")

TYPES = {
    "string": str,
    "boolean": bool,
    "array": list,
    "object": dict,
}


def load_schema(path=SCHEMA_PATH):
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def _check(value, rule, name):
    if rule.get("not") == {}:
        return [f"property '{name}' is not allowed. {rule.get('description', '')}".strip()]
    expected = rule.get("type")
    if expected and not isinstance(value, TYPES[expected]):
        return [f"'{name}' must be a {expected}, got {type(value).__name__} ({value!r})"]
    errors = []
    if "enum" in rule and value not in rule["enum"]:
        errors.append(f"'{name}' must be one of {rule['enum']}, got {value!r}")
    if isinstance(value, str):
        if "minLength" in rule and len(value) < rule["minLength"]:
            errors.append(f"'{name}' is empty")
        if "maxLength" in rule and len(value) > rule["maxLength"]:
            errors.append(f"'{name}' is longer than {rule['maxLength']} characters")
        if "pattern" in rule and not re.search(rule["pattern"], value):
            errors.append(f"'{name}' has an invalid format: {value!r} (expected {rule['pattern']})")
        if rule.get("format") == "date-or-datetime" and not DATE_RE.match(value):
            errors.append(f"'{name}' must look like 2026-11-04 or 2026-11-04T09:30, got {value!r}")
    if isinstance(value, list):
        if "maxItems" in rule and len(value) > rule["maxItems"]:
            errors.append(f"'{name}' has more than {rule['maxItems']} items")
        if "items" in rule:
            for i, item in enumerate(value):
                errors.extend(_check(item, rule["items"], f"{name}[{i}]"))
    if isinstance(value, dict):
        prefix = f"{name}." if name else ""
        for key in rule.get("required", []):
            if value.get(key) is None:
                errors.append(f"required property '{prefix}{key}' is missing")
        for key, item in value.items():
            sub = rule.get("properties", {}).get(key)
            if sub is not None and item is not None:
                errors.extend(_check(item, sub, prefix + key))
    return errors


def validate(data, schema):
    """Return a list of error messages; empty when data is valid."""
    return _check(data, schema, "")
