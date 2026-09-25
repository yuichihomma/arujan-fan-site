#!/usr/bin/env python3
import datetime as dt
import html
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path


TIMESTAMP_RE = re.compile(
    r"(?P<h>\d{2}):(?P<m>\d{2}):(?P<s>\d{2})(?:[.,](?P<ms>\d{3}))?\s+-->\s+"
    r"(?P<eh>\d{2}):(?P<em>\d{2}):(?P<es>\d{2})(?:[.,](?P<ems>\d{3}))?"
)

RESULT_PATTERNS = [
    ("crew", re.compile(r"(クルー|クルーメイト|村人|市民).{0,16}(勝利|win)", re.I), 82),
    ("impostor", re.compile(r"(インポスター|インポスタ|人狼|狼).{0,16}(勝利|win)", re.I), 82),
    ("neutral", re.compile(r"(ジャッカル|第三陣営|恋人|ラバーズ|アーソニスト|ジェスター|テロリスト).{0,16}(勝利|win)", re.I), 80),
    ("unknown", re.compile(r"(勝利しました|勝利です|敗北しました|敗北です)", re.I), 50),
]


def main() -> int:
    if len(sys.argv) < 2:
        print(json.dumps({"drafts": []}, ensure_ascii=False))
        return 0

    with open(sys.argv[1], "r", encoding="utf-8") as f:
        payload = json.load(f)

    roles = payload.get("roles", [])
    options = payload.get("analysis_options", {})
    drafts = []

    for source in payload.get("sources", []):
        try:
            transcript = fetch_transcript(source["source_video_url"], use_whisper=bool(options.get("use_whisper")))
            drafts.extend(analyze_source(source, transcript, roles))
        except Exception as exc:
            drafts.append(make_error_draft(source, str(exc)))

    print(json.dumps({"drafts": drafts}, ensure_ascii=False))
    return 0


def fetch_transcript(video_url: str, use_whisper: bool = False) -> list[dict]:
    with tempfile.TemporaryDirectory(prefix="amongus-subtitles-") as tmp:
        output = str(Path(tmp) / "%(id)s.%(ext)s")
        command = [
            "yt-dlp",
            "--skip-download",
            "--no-js-runtimes",
            "--js-runtimes",
            "node:/usr/bin/node",
            "--write-subs",
            "--write-auto-subs",
            "--sub-langs",
            "ja.*,ja,en.*",
            "--sub-format",
            "vtt",
            "--output",
            output,
            video_url,
        ]
        result = subprocess.run(command, text=True, capture_output=True, timeout=240)

        vtt_files = sorted(Path(tmp).glob("*.vtt"))

        if not vtt_files:
            if use_whisper:
                return transcribe_audio(video_url)

            message = (result.stderr or result.stdout).strip()
            raise RuntimeError(f"字幕または自動字幕を取得できませんでした。--use-whisper で音声解析が必要です。{message}")

        preferred = pick_preferred_vtt(vtt_files)
        return parse_vtt(preferred)


def transcribe_audio(video_url: str) -> list[dict]:
    if shutil.which("whisper") is None:
        raise RuntimeError("Whisperがインストールされていません。pip install openai-whisper が必要です。")

    with tempfile.TemporaryDirectory(prefix="amongus-whisper-") as tmp:
        audio_path = str(Path(tmp) / "audio.%(ext)s")
        download = subprocess.run(
            [
                "yt-dlp",
                "--no-js-runtimes",
                "--js-runtimes",
                "node:/usr/bin/node",
                "--extract-audio",
                "--audio-format",
                "mp3",
                "--output",
                audio_path,
                video_url,
            ],
            text=True,
            capture_output=True,
            timeout=1800,
        )

        audio_files = sorted(Path(tmp).glob("audio.*"))

        if download.returncode != 0 or not audio_files:
            raise RuntimeError((download.stderr or download.stdout).strip() or "音声を取得できませんでした。")

        whisper = subprocess.run(
            [
                "whisper",
                str(audio_files[0]),
                "--language",
                "Japanese",
                "--model",
                "base",
                "--output_format",
                "vtt",
                "--output_dir",
                tmp,
            ],
            text=True,
            capture_output=True,
            timeout=7200,
        )

        if whisper.returncode != 0:
            raise RuntimeError((whisper.stderr or whisper.stdout).strip() or "Whisper解析に失敗しました。")

        vtt_files = sorted(Path(tmp).glob("*.vtt"))

        if not vtt_files:
            raise RuntimeError("WhisperのVTT出力が見つかりません。")

        return parse_vtt(vtt_files[0])


def pick_preferred_vtt(vtt_files: list[Path]) -> Path:
    for keyword in [".ja.", ".ja-", ".ja_"]:
        for path in vtt_files:
            if keyword in path.name:
                return path

    return vtt_files[0]


def parse_vtt(path: Path) -> list[dict]:
    entries = []
    lines = path.read_text(encoding="utf-8", errors="ignore").splitlines()
    index = 0

    while index < len(lines):
        match = TIMESTAMP_RE.search(lines[index])

        if not match:
            index += 1
            continue

        start = to_seconds(match.group("h"), match.group("m"), match.group("s"), match.group("ms"))
        index += 1
        text_lines = []

        while index < len(lines) and lines[index].strip():
            text_lines.append(clean_caption_text(lines[index]))
            index += 1

        text = normalize_text(" ".join(text_lines))

        if text:
            entries.append({"start": start, "text": text})

        index += 1

    return dedupe_captions(entries)


def to_seconds(hours: str, minutes: str, seconds: str, millis: str | None) -> int:
    return int(hours) * 3600 + int(minutes) * 60 + int(seconds)


def clean_caption_text(text: str) -> str:
    text = re.sub(r"<[^>]+>", "", text)
    text = html.unescape(text)
    text = re.sub(r"\{\\.*?\}", "", text)
    return text.strip()


def normalize_text(text: str) -> str:
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def dedupe_captions(entries: list[dict]) -> list[dict]:
    deduped = []
    previous = None

    for entry in entries:
        if entry["text"] == previous:
            continue

        deduped.append(entry)
        previous = entry["text"]

    return deduped


def analyze_source(source: dict, transcript: list[dict], roles: list[dict]) -> list[dict]:
    if not transcript:
        return [make_error_draft(source, "字幕が空でした。")]

    result_hits = find_result_hits(transcript)

    if not result_hits:
        return [make_no_result_draft(source, transcript)]

    drafts = []

    for match_number, hit in enumerate(result_hits, start=1):
        window_text = collect_window_text(transcript, hit["timestamp"], before=120, after=60)
        participant_name = infer_participant_name(window_text, source.get("participants", []))
        role = infer_role(window_text, roles)

        drafts.append(
            make_result_draft(
                source=source,
                match_number=match_number,
                timestamp=hit["timestamp"],
                win_side=hit["win_side"],
                evidence=window_text,
                confidence=hit["confidence"],
                member_name=participant_name,
                role_name=role["name"] if role else None,
                result=infer_member_result(hit["win_side"], role),
            )
        )

    return drafts


def find_result_hits(transcript: list[dict]) -> list[dict]:
    hits = []

    for entry in transcript:
        text = entry["text"]

        for win_side, pattern, confidence in RESULT_PATTERNS:
            if pattern.search(text):
                if hits and entry["start"] - hits[-1]["timestamp"] < 240:
                    if confidence > hits[-1]["confidence"]:
                        hits[-1] = {
                            "timestamp": entry["start"],
                            "win_side": win_side,
                            "confidence": confidence,
                            "text": text,
                        }
                    break

                hits.append(
                    {
                        "timestamp": entry["start"],
                        "win_side": win_side,
                        "confidence": confidence,
                        "text": text,
                    }
                )
                break

    return hits


def collect_window_text(transcript: list[dict], timestamp: int, before: int, after: int) -> str:
    start = max(0, timestamp - before)
    end = timestamp + after
    texts = [entry["text"] for entry in transcript if start <= entry["start"] <= end]
    text = " ".join(texts)
    return text[:1200]


def infer_participant_name(text: str, participants: list[dict]) -> str | None:
    for participant in participants:
        name = participant.get("name")
        if name and name in text:
            return name

    return None


def infer_role(text: str, roles: list[dict]) -> dict | None:
    matches = []

    for role in roles:
        name = role.get("name")
        if name and name in text:
            matches.append(role)

    if not matches:
        return None

    matches.sort(key=lambda role: len(role.get("name", "")), reverse=True)
    return matches[0]


def infer_member_result(win_side: str, role: dict | None) -> str | None:
    if not role or win_side == "unknown":
        return None

    role_type = role.get("type")

    if win_side == "crew":
        return "win" if role_type == "crew" else "lose"

    if win_side == "impostor":
        return "win" if role_type == "impostor" else "lose"

    if win_side == "neutral":
        return "win" if role_type == "neutral" else "lose"

    return None


def make_result_draft(
    source: dict,
    match_number: int,
    timestamp: int,
    win_side: str,
    evidence: str,
    confidence: int,
    member_name: str | None,
    role_name: str | None,
    result: str | None,
) -> dict:
    return {
        "archive_section_id": source.get("archive_section_id"),
        "member_id": source.get("source_member_id"),
        "video_url": source.get("source_video_url"),
        "match_number": match_number,
        "video_timestamp_seconds": timestamp,
        "video_timestamp_label": format_timestamp(timestamp),
        "estimated_real_time": estimate_real_time(source.get("estimated_start_at"), timestamp),
        "member_name": member_name,
        "role_name": role_name,
        "result": result,
        "win_side": win_side,
        "evidence_text": evidence,
        "confidence": confidence if member_name and role_name and result else min(confidence, 55),
        "status": "pending",
        "memo": None,
        "raw_payload": {
            "source": source,
            "parser": "yt-dlp-vtt-heuristic",
            "matched_win_side": win_side,
        },
    }


def make_no_result_draft(source: dict, transcript: list[dict]) -> dict:
    sample = " ".join(entry["text"] for entry in transcript[:20])[:1200]

    return {
        "archive_section_id": source.get("archive_section_id"),
        "member_id": source.get("source_member_id"),
        "video_url": source.get("source_video_url"),
        "match_number": None,
        "video_timestamp_seconds": 0,
        "video_timestamp_label": "00:00:00",
        "estimated_real_time": source.get("estimated_start_at"),
        "member_name": None,
        "role_name": None,
        "result": None,
        "win_side": None,
        "evidence_text": f"字幕は取得できましたが、勝敗候補を検出できませんでした。字幕冒頭: {sample}",
        "confidence": 10,
        "status": "pending",
        "memo": None,
        "raw_payload": {"source": source, "parser": "yt-dlp-vtt-heuristic"},
    }


def make_error_draft(source: dict, message: str) -> dict:
    return {
        "archive_section_id": source.get("archive_section_id"),
        "member_id": source.get("source_member_id"),
        "video_url": source.get("source_video_url"),
        "match_number": None,
        "video_timestamp_seconds": 0,
        "video_timestamp_label": "00:00:00",
        "estimated_real_time": source.get("estimated_start_at"),
        "member_name": None,
        "role_name": None,
        "result": None,
        "win_side": None,
        "evidence_text": f"動画解析に失敗しました: {message}",
        "confidence": 0,
        "status": "pending",
        "memo": None,
        "raw_payload": {"source": source, "error": message},
    }


def format_timestamp(seconds: int) -> str:
    hours = seconds // 3600
    minutes = (seconds % 3600) // 60
    secs = seconds % 60
    return f"{hours:02d}:{minutes:02d}:{secs:02d}"


def estimate_real_time(start_at: str | None, offset_seconds: int) -> str | None:
    if not start_at:
        return None

    try:
        start = dt.datetime.fromisoformat(start_at)
    except ValueError:
        return None

    return (start + dt.timedelta(seconds=offset_seconds)).strftime("%Y-%m-%d %H:%M:%S")


if __name__ == "__main__":
    raise SystemExit(main())
