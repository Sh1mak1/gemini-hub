#!/usr/bin/env python3
"""Build a Pushover message summarizing what Cursor Agent implemented."""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from pathlib import Path

MAX_MESSAGE_CHARS = 950
MAX_INSTRUCTION_CHARS = 200
MAX_SUMMARY_CHARS = 350
MAX_GIT_LINES = 12
MAX_FILE_LINES = 8

EDIT_TOOLS = {
    "Write",
    "StrReplace",
    "Delete",
    "EditNotebook",
    "ApplyPatch",
}


def main() -> int:
    raw = sys.stdin.read()
    if not raw.strip():
        return 1

    try:
        hook = json.loads(raw)
    except json.JSONDecodeError:
        return 1

    if hook.get("status") != "completed":
        return 0

    workspace = first_workspace(hook)
    transcript_path = hook.get("transcript_path")

    instruction = ""
    summary = ""
    edited_files: list[str] = []

    if isinstance(transcript_path, str) and transcript_path:
        instruction, summary, edited_files = parse_transcript(
            Path(transcript_path),
            workspace,
        )

    git_lines = git_status_lines(workspace) if workspace else []

    message = format_message(
        hook=hook,
        instruction=instruction,
        edited_files=edited_files,
        git_lines=git_lines,
        summary=summary,
    )

    sys.stdout.write(message)
    return 0


def first_workspace(hook: dict) -> Path | None:
    roots = hook.get("workspace_roots")
    if not isinstance(roots, list):
        return None

    for root in roots:
        if isinstance(root, str) and root.strip():
            path = Path(root).expanduser()
            if path.is_dir():
                return path

    return None


def parse_transcript(
    transcript_path: Path,
    workspace: Path | None,
) -> tuple[str, str, list[str]]:
    if not transcript_path.is_file():
        return "", "", []

    instruction = ""
    summary_candidates: list[tuple[int, str]] = []
    edited: list[str] = []
    seen_files: set[str] = set()

    for line in transcript_path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line:
            continue

        try:
            entry = json.loads(line)
        except json.JSONDecodeError:
            continue

        role = entry.get("role")
        message = entry.get("message")
        if not isinstance(message, dict):
            continue

        content = message.get("content")
        if not isinstance(content, list):
            continue

        text_parts: list[str] = []
        has_edit_tools = False

        for block in content:
            if not isinstance(block, dict):
                continue

            block_type = block.get("type")

            if block_type == "text":
                text = block.get("text")
                if isinstance(text, str) and text.strip():
                    text_parts.append(text)

            if block_type != "tool_use":
                continue

            tool_name = block.get("name")
            tool_input = block.get("input")
            if tool_name in EDIT_TOOLS:
                has_edit_tools = True

            if tool_name not in EDIT_TOOLS or not isinstance(tool_input, dict):
                continue

            for key in ("path", "target_notebook"):
                value = tool_input.get(key)
                if isinstance(value, str) and value.strip():
                    rel = relativize_path(value, workspace)
                    if rel not in seen_files:
                        seen_files.add(rel)
                        edited.append(rel)

        if role == "user" and text_parts:
            instruction = clean_user_text("\n".join(text_parts))

        if role == "assistant" and text_parts:
            candidate = clean_assistant_text("\n".join(text_parts))
            if not candidate or is_investigation_message(candidate):
                continue

            score = summary_score(candidate, has_edit_tools)
            summary_candidates.append((score, candidate))

    summary = ""
    if summary_candidates:
        summary = max(summary_candidates, key=lambda item: item[0])[1]

    return instruction, summary, edited


def is_investigation_message(text: str) -> bool:
    patterns = (
        "確認します",
        "調べ",
        "読んで",
        "整理します",
        "Let me ",
        "I'll ",
        "I will ",
    )
    lowered = text.lower()
    return any(pattern in text or pattern.lower() in lowered for pattern in patterns)


def summary_score(text: str, has_edit_tools: bool) -> int:
    score = min(len(text), 500)

    if not has_edit_tools:
        score += 1000

    if any(marker in text for marker in ("##", "完了", "実装", "変更", "追加", "修正")):
        score += 200

    return score


def clean_user_text(text: str) -> str:
    text = re.sub(r"<user_query>\s*", "", text, flags=re.IGNORECASE)
    text = re.sub(r"</user_query>\s*", "", text, flags=re.IGNORECASE)
    text = re.sub(r"<[^>]+>", "", text)
    text = re.sub(r"\s+", " ", text).strip()
    return truncate(text, MAX_INSTRUCTION_CHARS)


def clean_assistant_text(text: str) -> str:
    text = text.replace("[REDACTED]", "")
    text = re.sub(r"```[\s\S]*?```", "", text)
    text = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", text)
    text = re.sub(r"^#+\s*", "", text, flags=re.MULTILINE)
    text = re.sub(r"\s+", " ", text).strip()
    return truncate(text, MAX_SUMMARY_CHARS)


def relativize_path(path_str: str, workspace: Path | None) -> str:
    path = Path(path_str).expanduser()
    if workspace is not None:
        try:
            return path.resolve().relative_to(workspace.resolve()).as_posix()
        except ValueError:
            pass

    return path.as_posix()


def git_status_lines(workspace: Path) -> list[str]:
    try:
        result = subprocess.run(
            ["git", "-C", str(workspace), "status", "--porcelain"],
            capture_output=True,
            text=True,
            timeout=5,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return []

    if result.returncode != 0:
        return []

    lines = [line.rstrip() for line in result.stdout.splitlines() if line.strip()]
    return lines[:MAX_GIT_LINES]


def format_message(
    hook: dict,
    instruction: str,
    edited_files: list[str],
    git_lines: list[str],
    summary: str,
) -> str:
    event = hook.get("hook_event_name", "stop")
    title_prefix = "Subagent" if event == "subagentStop" else "Agent"

    sections: list[str] = [f"{title_prefix} への指示が完了しました。"]

    if instruction:
        sections.append(f"【指示】\n{instruction}")

    if summary:
        sections.append(f"【実装概要】\n{summary}")

    if edited_files:
        file_lines = edited_files[:MAX_FILE_LINES]
        body = "\n".join(f"• {path}" for path in file_lines)
        if len(edited_files) > MAX_FILE_LINES:
            body += f"\n…他 {len(edited_files) - MAX_FILE_LINES} 件"
        sections.append(f"【編集ファイル】({len(edited_files)}件)\n{body}")

    if git_lines and not edited_files:
        git_body = "\n".join(f"• {line}" for line in git_lines)
        sections.append(f"【git 変更】\n{git_body}")

    model = hook.get("model")
    if isinstance(model, str) and model.strip():
        sections.append(f"【モデル】\n{model.strip()}")

    return truncate("\n\n".join(sections), MAX_MESSAGE_CHARS)


def truncate(text: str, limit: int) -> str:
    if len(text) <= limit:
        return text

    return text[: limit - 1].rstrip() + "…"


if __name__ == "__main__":
    raise SystemExit(main())
