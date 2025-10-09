import json
from pathlib import Path
from typing import Any, Dict, Iterable, Iterator, Mapping, MutableMapping


def ensure_parent_dir(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def read_jsonl(path: Path) -> Iterator[Dict[str, Any]]:
    with path.open("r", encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line:
                continue
            yield json.loads(line)


def write_jsonl(path: Path, records: Iterable[Mapping[str, Any]]) -> None:
    ensure_parent_dir(path)
    with path.open("w", encoding="utf-8") as fh:
        for record in records:
            fh.write(json.dumps(record, ensure_ascii=False) + "\n")


def parse_float(value: Any, default: float = 0.0) -> float:
    if value is None:
        return default
    if isinstance(value, (int, float)):
        return float(value)
    try:
        text = str(value).strip()
        if not text:
            return default
        return float(text)
    except (TypeError, ValueError):
        return default


def parse_int(value: Any, default: int = 0) -> int:
    if value is None:
        return default
    if isinstance(value, int):
        return value
    if isinstance(value, float):
        return int(value)
    try:
        text = str(value).strip()
        if not text:
            return default
        return int(float(text))
    except (TypeError, ValueError):
        return default


def load_margin_rules(path: Path) -> Dict[str, Dict[str, Any]]:
    with path.open("r", encoding="utf-8") as fh:
        data = json.load(fh)
    rules: Dict[str, Dict[str, Any]] = {}
    if isinstance(data, Mapping):
        default = data.get("default")
        if isinstance(default, Mapping):
            rules["default"] = {
                "type": str(default.get("type", "PERCENT")).upper(),
                "value": float(default.get("value", 0.0)),
            }
        rule_map = data.get("rules") if isinstance(data.get("rules"), Mapping) else None
        if isinstance(rule_map, Mapping):
            for key, payload in rule_map.items():
                if not isinstance(payload, Mapping):
                    continue
                rules[str(key)] = {
                    "type": str(payload.get("type", "PERCENT")).upper(),
                    "value": float(payload.get("value", 0.0)),
                }
    return rules


def round_059(value: int) -> int:
    if value <= 0:
        return max(0, value)
    endings = (0, 5, 9)
    base_tens = value // 10
    best = None
    best_diff = None
    for tens_delta in range(-2, 3):
        tens = base_tens + tens_delta
        if tens < 0:
            continue
        for ending in endings:
            candidate = tens * 10 + ending
            if candidate <= 0:
                continue
            diff = abs(candidate - value)
            if best is None or diff < best_diff or (diff == best_diff and candidate < best):
                best = candidate
                best_diff = diff
    if best is None:
        return max(0, value)
    return best


def update_summary(paths: Iterable[Path], stage_key: str, metrics: Mapping[str, Any]) -> None:
    for path in paths:
        ensure_parent_dir(path)
        if path.exists():
            with path.open("r", encoding="utf-8") as fh:
                try:
                    summary = json.load(fh)
                except json.JSONDecodeError:
                    summary = {}
        else:
            summary = {}
        if not isinstance(summary, MutableMapping):
            summary = {}
        summary[stage_key] = metrics
        with path.open("w", encoding="utf-8") as fh:
            json.dump(summary, fh, indent=2, ensure_ascii=False, sort_keys=True)
