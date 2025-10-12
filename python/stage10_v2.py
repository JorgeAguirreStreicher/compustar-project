#!/usr/bin/env python3
"""Stage 10 v2 executor for applying WooCommerce payloads via WP-CLI."""

from __future__ import annotations

import argparse
import json
import os
import shlex
import subprocess
import sys
import time
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, MutableMapping, Optional


class Stage10Error(RuntimeError):
    """Base error for Stage 10 v2."""


class WPCLIError(Stage10Error):
    """Raised when executing WP-CLI fails."""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 10 v2 - WooCommerce fast apply")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--report", required=True)
    parser.add_argument("--dry-run", type=int, choices=[0, 1], default=0)
    parser.add_argument("--summary", action="append", default=[])
    parser.add_argument("--writer", choices=["sim", "wp"], default=None)
    parser.add_argument("--wp-path", dest="wp_path", default=None)
    parser.add_argument("--wp-args", dest="wp_args", default=None)
    parser.add_argument("--run-id", default="")
    parser.add_argument("--fast-php", default=None)
    return parser.parse_args()


def env_flag(name: str, default: bool = False) -> bool:
    raw = os.environ.get(name)
    if raw is None or raw == "":
        return default
    normalized = raw.strip().lower()
    if normalized == "":
        return default
    return normalized in {"1", "true", "yes", "on"}


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def load_payloads(input_path: Path) -> List[Mapping[str, Any]]:
    payloads: List[Mapping[str, Any]] = []
    with input_path.open("r", encoding="utf-8") as handle:
        for line in handle:
            text = line.strip()
            if not text:
                continue
            try:
                payload = json.loads(text)
            except json.JSONDecodeError as exc:
                raise Stage10Error(f"invalid_jsonl:{exc}") from exc
            if not isinstance(payload, Mapping):
                raise Stage10Error("invalid_payload_type")
            payloads.append(payload)
    return payloads


def get_wp_bin() -> str:
    candidate = os.environ.get("WP_BIN") or "/usr/local/bin/wp"
    candidate_path = Path(candidate)
    if candidate_path.is_dir():
        raise Stage10Error(f"wp_bin_is_directory:{candidate}")
    return candidate


def build_wp_args(wp_root: Path) -> List[str]:
    raw = os.environ.get("WP_PATH_ARGS", "")
    args = shlex.split(raw) if raw else []
    has_path = False
    index = 0
    while index < len(args):
        token = args[index]
        if token.startswith("--path="):
            has_path = True
            break
        if token == "--path" and index + 1 < len(args):
            has_path = True
            break
        index += 1
    if not has_path:
        args.append(f"--path={wp_root}")
    return args


def _looks_transient(stderr: str, stdout: str) -> bool:
    text = f"{stderr}\n{stdout}".lower()
    transient_markers = [
        "another update is currently in progress",
        "temporarily unavailable",
        "timeout",
        "connection reset",
        "deadlock",
    ]
    return any(marker in text for marker in transient_markers)


def _extract_last_json(stdout: str) -> Optional[MutableMapping[str, Any]]:
    last: Optional[MutableMapping[str, Any]] = None
    for line in stdout.splitlines():
        text = line.strip()
        if not text:
            continue
        try:
            parsed = json.loads(text)
        except json.JSONDecodeError:
            continue
        if isinstance(parsed, MutableMapping):
            last = parsed
    return last


def run_fast(payload: Mapping[str, Any], fast_php: str, wp_root: Path) -> Mapping[str, Any]:
    if env_flag("DRY_RUN", False):
        result: Dict[str, Any] = {
            "sku": payload.get("sku"),
            "id": payload.get("id"),
            "actions": [],
            "skipped": ["dry_run"],
            "errors": [],
        }
        return result

    wp_bin = get_wp_bin()
    args = build_wp_args(wp_root)
    print(
        f"[FAST] resolve: wp_bin={wp_bin}, wp_root={wp_root}, args={' '.join(args)}",
        file=sys.stderr,
    )

    payload_json = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
    cmd = [
        wp_bin,
        "--no-color",
        *args,
        "eval-file",
        fast_php,
        "--",
        payload_json,
    ]
    print(f"[FAST] exec: {shlex.join(cmd)}", file=sys.stderr)

    attempts = 0
    last_error: Optional[str] = None
    while attempts < 2:
        attempts += 1
        try:
            completed = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=60,
                check=False,
            )
        except FileNotFoundError as exc:
            raise WPCLIError(f"wp_cli_failed:{exc}") from exc

        stdout = completed.stdout or ""
        stderr = completed.stderr or ""
        parsed = _extract_last_json(stdout)
        if parsed is not None:
            parsed.setdefault("actions", [])
            parsed.setdefault("errors", [])
            parsed.setdefault("skipped", [])
            return dict(parsed)

        message = stderr.strip() or stdout.strip() or f"rc={completed.returncode}"
        last_error = message
        if attempts < 2 and _looks_transient(stderr, stdout):
            time.sleep(2)
            continue
        break

    raise WPCLIError(f"wp_cli_failed:{last_error}")


def update_metrics(metrics: MutableMapping[str, int], result: Mapping[str, Any]) -> None:
    metrics["processed"] += 1

    actions = [str(item) for item in result.get("actions", [])]
    skipped = [str(item) for item in result.get("skipped", [])]
    errors = [str(item) for item in result.get("errors", [])]

    if "created" in actions:
        metrics["created"] += 1
    if "updated" in actions:
        metrics["updated"] += 1
    if "price_updated" in actions:
        metrics["updated_price"] += 1
    if "stock_set" in actions:
        metrics["stock_set"] += 1
    if "cat_assigned" in actions:
        metrics["cat_assigned"] += 1
    if any(flag in {"price_not_lower", "price_kept"} for flag in skipped):
        metrics["kept_price"] += 1
    if skipped:
        metrics["skipped"] += 1
    if errors:
        metrics["errors"] += 1


def collect_summary_paths(run_dir: Path, explicit: Iterable[str]) -> List[Path]:
    paths = [Path(item) for item in explicit] if explicit else []
    if not paths:
        paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]
    return paths


def write_summary(paths: Iterable[Path], metrics: Mapping[str, Any]) -> None:
    for path in paths:
        ensure_parent(path)
        if path.exists():
            try:
                with path.open("r", encoding="utf-8") as handle:
                    existing = json.load(handle)
            except (json.JSONDecodeError, OSError):
                existing = {}
        else:
            existing = {}
        if not isinstance(existing, MutableMapping):
            existing = {}
        existing["stage_10_v2"] = metrics
        with path.open("w", encoding="utf-8") as handle:
            json.dump(existing, handle, indent=2, ensure_ascii=False, sort_keys=True)


def main() -> None:
    args = parse_args()
    run_dir = Path(args.run_dir)
    input_path = Path(args.input)
    log_path = Path(args.log)
    report_path = Path(args.report)

    if args.dry_run:
        os.environ["DRY_RUN"] = "1"

    if args.wp_args:
        os.environ["WP_PATH_ARGS"] = args.wp_args

    wp_root_str = args.wp_path or os.environ.get("WP_ROOT") or "/home/compustar/htdocs"
    wp_root = Path(wp_root_str)

    if not os.environ.get("ST10_GUARD_PRICE_ZERO"):
        os.environ["ST10_GUARD_PRICE_ZERO"] = "1"

    fast_php = (
        args.fast_php
        or os.environ.get("ST10_FAST_PHP")
        or str(
            wp_root
            / "wp-content"
            / "plugins"
            / "compu-import-lego"
            / "includes"
            / "stages"
            / "stage10_apply_fast_v2.php"
        )
    )
    fast_php_path = Path(fast_php)
    if not fast_php_path.exists():
        raise Stage10Error(f"missing_fast_php:{fast_php}")
    fast_php = str(fast_php_path)

    ensure_parent(log_path)
    ensure_parent(report_path)

    if not input_path.exists():
        raise Stage10Error(f"missing_input:{input_path}")
    payloads = load_payloads(input_path)

    results: List[Mapping[str, Any]] = []
    metrics: Dict[str, int] = {
        "processed": 0,
        "created": 0,
        "updated": 0,
        "updated_price": 0,
        "stock_set": 0,
        "cat_assigned": 0,
        "kept_price": 0,
        "skipped": 0,
        "errors": 0,
    }

    if not payloads:
        empty_result = {"sku": None, "id": None, "actions": [], "skipped": ["no_records"], "errors": []}
        results.append(empty_result)
        with log_path.open("w", encoding="utf-8") as handle:
            handle.write(json.dumps(empty_result, ensure_ascii=False) + "\n")
        report_payload = {"results": results, "metrics": metrics}
        with report_path.open("w", encoding="utf-8") as handle:
            json.dump(report_payload, handle, indent=2, ensure_ascii=False, sort_keys=True)
        write_summary(collect_summary_paths(run_dir, args.summary), metrics)
        return

    with log_path.open("w", encoding="utf-8") as log_handle:
        for payload in payloads:
            try:
                result = run_fast(payload, fast_php, wp_root)
            except WPCLIError as exc:
                result = {
                    "sku": payload.get("sku"),
                    "id": payload.get("id"),
                    "actions": [],
                    "skipped": [],
                    "errors": [str(exc)],
                }
            if not isinstance(result, dict):
                result = dict(result)
            result.setdefault("sku", payload.get("sku"))
            if result.get("id") is None and payload.get("id") is not None:
                result["id"] = payload.get("id")
            results.append(result)
            update_metrics(metrics, result)
            log_handle.write(json.dumps(result, ensure_ascii=False) + "\n")

    report_payload = {"results": results, "metrics": metrics}
    with report_path.open("w", encoding="utf-8") as handle:
        json.dump(report_payload, handle, indent=2, ensure_ascii=False, sort_keys=True)

    write_summary(collect_summary_paths(run_dir, args.summary), metrics)


if __name__ == "__main__":
    try:
        main()
    except Stage10Error as exc:
        print(f"[stage10_v2] {exc}", file=sys.stderr)
        sys.exit(1)
