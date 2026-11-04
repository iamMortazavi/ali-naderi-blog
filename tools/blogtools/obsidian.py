"""Convert Obsidian-flavoured Markdown into Markdown that Hugo understands.

Handled here:
  [[Note]], [[Note|text]], [[Note#Heading|text]]   → links to the published post
  ![[image.png]], ![[image.png|alt]]                → copied attachment + image
  ![[episode.mp3]], ![[clip.mp4]]                   → copied attachment (players come from render-image.html)
  [text](Other%20note.md), ![](../attachments/a.png) → same as above, for standard Markdown links
  %% private comment %%                             → removed

Left for Hugo: callouts (> [!note]), ==highlight==, $math$, tables, footnotes.
Embedding one note inside another (![[Other note]]) is reported as an error.

Code blocks and inline code are never changed.
"""

import hashlib
import os
import re
import unicodedata
from urllib.parse import unquote

FENCE_RE = re.compile(r"^ {0,3}(`{3,}|~{3,})")
INLINE_CODE_RE = re.compile(r"(`+)(?:.+?)\1", re.S)
COMMENT_RE = re.compile(r"%%.*?%%", re.S)
WIKILINK_RE = re.compile(r"(!?)\[\[([^\[\]|#^]*)(#\^?[^\[\]|]*)?(?:\|([^\[\]]*))?\]\]")
MDLINK_RE = re.compile(r"(!?)\[([^\]]*)\]\(\s*(<[^>]+>|[^)\s]+)(\s+\"[^\"]*\")?\s*\)")
EXTERNAL_RE = re.compile(r"^(?:[a-z][a-z0-9+.-]*:|#|/)", re.I)
SIZE_RE = re.compile(r"^\d+(?:x\d+)?$")

MEDIA_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".avif", ".svg",
    ".mp3", ".m4a", ".ogg", ".oga", ".wav", ".mp4", ".webm", ".pdf",
}


def split_fenced(text):
    """Yield (is_code, chunk) pairs, separating fenced code blocks from prose."""
    chunks, buf, fence = [], [], None
    for line in text.splitlines(keepends=True):
        m = FENCE_RE.match(line)
        if fence is None and m:
            if buf:
                chunks.append((False, "".join(buf)))
            buf, fence = [line], m.group(1)
        elif fence is not None and m and m.group(1)[0] == fence[0] and len(m.group(1)) >= len(fence) \
                and line.strip() == m.group(1):
            buf.append(line)
            chunks.append((True, "".join(buf)))
            buf, fence = [], None
        else:
            buf.append(line)
    if buf:
        chunks.append((fence is not None, "".join(buf)))
    return chunks


def map_prose(text, fn):
    """Apply fn to prose only: skip fenced blocks and inline code spans."""
    out = []
    for is_code, chunk in split_fenced(text):
        if is_code:
            out.append(chunk)
            continue
        chunk = COMMENT_RE.sub("", chunk)
        pos, parts = 0, []
        for m in INLINE_CODE_RE.finditer(chunk):
            parts.append(fn(chunk[pos:m.start()]))
            parts.append(m.group(0))
            pos = m.end()
        parts.append(fn(chunk[pos:]))
        out.append("".join(parts))
    return "".join(out)


def heading_anchor(heading):
    """Approximate Hugo's default ("github") heading IDs."""
    s = unicodedata.normalize("NFC", heading.strip().lower())
    s = "".join(ch for ch in s if ch.isalnum() or ch in " -_")
    return s.replace(" ", "-")


def safe_filename(name, used):
    """Give an attachment a URL-friendly name that is unique inside the post bundle."""
    stem, ext = os.path.splitext(name)
    ascii_stem = re.sub(r"[^a-z0-9]+", "-", stem.lower()).strip("-")
    if not ascii_stem or not stem.isascii():
        ascii_stem = "file-" + hashlib.sha1(name.encode("utf-8")).hexdigest()[:8]
    candidate, n = f"{ascii_stem}{ext.lower()}", 2
    while candidate in used and used[candidate] != name:
        candidate, n = f"{ascii_stem}-{n}{ext.lower()}", n + 1
    used[candidate] = name
    return candidate


class Converter:
    """Converts one note body. One instance per note."""

    def __init__(self, resolver, note):
        self.resolver = resolver
        self.note = note
        self.attachments = {}  # bundle file name -> absolute source path
        self._used_names = {}
        self.errors = []
        self.warnings = []

    def attach(self, ref):
        """Register an attachment by Obsidian reference; return its bundle name or None."""
        if re.match(r"^https?://", ref):
            return ref
        src = self.resolver.find_attachment(ref, self.note.path)
        if not src:
            self.errors.append(f"attachment not found: {ref}")
            return None
        name = safe_filename(os.path.basename(src), self._used_names)
        self.attachments[name] = src
        return name

    def _note_link(self, target, anchor, text):
        post = self.resolver.find_post(target, self.note.lang)
        label = text or target
        if post is None:
            kind = "unpublished" if self.resolver.note_exists(target) else "missing"
            self.warnings.append(f"link to {kind} note '{target}' shown as plain text")
            return label
        url = post.path
        if anchor and not anchor.startswith("#^"):
            url += "#" + heading_anchor(anchor[1:])
        return f"[{label}]({url})"

    def _embed(self, target, text):
        ext = os.path.splitext(target)[1].lower()
        if ext not in MEDIA_EXTENSIONS:
            self.errors.append(f"embedding a note is not supported: ![[{target}]]. Use a link [[{target}]] instead")
            return ""
        name = self.attach(target)
        if name is None:
            return ""
        alt = "" if (text is None or SIZE_RE.match(text)) else text
        return f"![{alt}]({name})"

    def _wikilink(self, m):
        bang, target, anchor, text = m.group(1), (m.group(2) or "").strip(), m.group(3), m.group(4)
        if not target:  # [[#Heading]] inside the same note
            return f"[{text or anchor[1:]}](#{heading_anchor(anchor[1:])})" if anchor else m.group(0)
        if bang:
            return self._embed(target, text)
        return self._note_link(target, anchor, text)

    def _mdlink(self, m):
        bang, text, target, title = m.group(1), m.group(2), m.group(3), m.group(4) or ""
        target = target[1:-1] if target.startswith("<") else target
        if EXTERNAL_RE.match(target):
            return m.group(0)
        target = unquote(target)
        path, _, anchor = target.partition("#")
        ext = os.path.splitext(path)[1].lower()
        if ext == ".md" or ext == "":
            if bang:
                return self._embed(path, text)
            note = os.path.splitext(os.path.basename(path))[0]
            return self._note_link(note, "#" + anchor if anchor else None, text)
        name = self.attach(path)
        if name is None:
            return m.group(0) if not bang else ""
        return f"{bang}[{text}]({name}{title})"

    def _prose(self, chunk):
        # Standard links first: the links that wikilinks turn into must not be resolved twice.
        chunk = MDLINK_RE.sub(self._mdlink, chunk)
        return WIKILINK_RE.sub(self._wikilink, chunk)

    def convert(self, body):
        return map_prose(body, self._prose)
