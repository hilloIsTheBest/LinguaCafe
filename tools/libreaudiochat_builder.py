#!/usr/bin/env python3

"""
LibreAudioChat Project Builder

This script scaffolds a LibreAudioChat workspace that integrates:
- LibreChat (cloned) for multi-model chat
- Audiobooks API microservice (Node/Express)
- Audiobookshelf (via Docker Compose)
- Simple web page with chat sidebar and audio player
- Browser extension (MV3) that embeds the chat UI
- Desktop app (PyQt) prototype for offline/local usage

Usage:
  python tools/libreaudiochat_builder.py

It creates a folder named 'LibreAudioChat' next to this repository and
populates it with all components and a docker-compose.yml.

Assumptions:
- git available on PATH (for cloning LibreChat)
- User will run docker-compose separately
- User will install Python deps for desktop app if they want to use it
"""

import os
import sys
import json
import shutil
import subprocess
from pathlib import Path
import json


THIS_DIR = Path(__file__).resolve().parent
OUT_DIR = (THIS_DIR / '..' / 'LibreAudioChat').resolve()


def run(cmd: list[str], cwd: Path | None = None, check: bool = True) -> int:
    print('> ' + ' '.join(cmd))
    proc = subprocess.run(cmd, cwd=str(cwd) if cwd else None)
    if check and proc.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}")
    return proc.returncode


def write_file(path: Path, content: str):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')
    print(f"Wrote {path}")


def clone_librechat(base: Path):
    target = base / 'librechat'
    if target.exists():
        print(f"LibreChat already present at {target}, skipping clone.")
        return
    repo = 'https://github.com/danny-avila/LibreChat.git'
    try:
        run(['git', 'clone', '--depth', '1', repo, str(target)])
    except Exception as e:
        print(f"Warning: failed to clone LibreChat: {e}. You can clone manually into {target}.")


def create_audiobooks_api(base: Path):
    svc = base / 'audiobooks-api'
    package_json = {
        "name": "audiobooks-api",
        "version": "0.1.0",
        "type": "module",
        "main": "src/index.js",
        "scripts": {
            "start": "node src/index.js",
            "dev": "node --watch src/index.js"
        },
        "dependencies": {
            "axios": "^1.6.8",
            "cors": "^2.8.5",
            "express": "^4.19.2",
            "multer": "^1.4.5-lts.1",
            "form-data": "^4.0.0"
        }
    }

    index_js = r"""
import fs from 'fs';
import path from 'path';
import express from 'express';
import cors from 'cors';
import axios from 'axios';
import FormData from 'form-data';

const app = express();
app.use(cors());
app.use(express.json());

// ESM-compatible __dirname
const __dirname = path.dirname(new URL(import.meta.url).pathname);
// Serve simple web UI
app.use('/', express.static(path.join(__dirname, 'web')));

const PORT = process.env.PORT || 3456;
const AUDIOBOOKS_DIR = process.env.AUDIOBOOKS_DIR || '/audiobooks';
const ABS_URL = process.env.ABS_URL || '';
const ABS_TOKEN = process.env.ABS_TOKEN || '';
const LIBRECHAT_URL = process.env.LIBRECHAT_URL || 'http://localhost:3080';
const TRANSCRIPT_DIR = process.env.TRANSCRIPT_DIR || '/data/transcripts';
const STT_PROVIDER = (process.env.STT_PROVIDER || '').toLowerCase(); // 'openai' | 'localai'
const STT_URL = process.env.STT_URL || '';
const STT_API_KEY = process.env.STT_API_KEY || '';
const STT_MODEL = process.env.STT_MODEL || 'whisper-1';
const RAG_INDEX_URL = process.env.RAG_INDEX_URL || '';
const RAG_API_KEY = process.env.RAG_API_KEY || '';

fs.mkdirSync(TRANSCRIPT_DIR, { recursive: true });
const jobs = new Map();

async function transcribeFile(filePath) {
  if (!STT_PROVIDER) throw new Error('STT provider not configured');
  const form = new FormData();
  form.append('file', fs.createReadStream(filePath));
  form.append('model', STT_MODEL);

  const headers = form.getHeaders();
  const url = STT_PROVIDER === 'localai' ? (STT_URL || 'http://localai:8080/v1/audio/transcriptions') : (STT_URL || 'https://api.openai.com/v1/audio/transcriptions');
  if (STT_PROVIDER === 'openai' && STT_API_KEY) {
    headers['Authorization'] = `Bearer ${STT_API_KEY}`;
  }

  const resp = await axios.post(url, form, { headers });
  const data = resp.data;
  if (typeof data === 'string') return data;
  if (data && typeof data.text === 'string') return data.text;
  return JSON.stringify(data);
}

function listLocalTracks(bookId) {
  const dir = path.join(AUDIOBOOKS_DIR, bookId);
  if (!fs.existsSync(dir)) return [];
  const audioExt = new Set(['.mp3', '.m4a', '.m4b', '.ogg', '.flac', '.wav']);
  return fs.readdirSync(dir)
    .filter(f => audioExt.has(path.extname(f).toLowerCase()))
    .sort((a,b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }))
    .map(f => path.join(dir, f));
}

async function indexWithRag(bookId, title, text) {
  if (!RAG_INDEX_URL) return;
  try {
    const headers = { 'Content-Type': 'application/json' };
    if (RAG_API_KEY) headers['Authorization'] = `Bearer ${RAG_API_KEY}`;
    await axios.post(RAG_INDEX_URL, { bookId, title, text }, { headers });
  } catch (e) {
    console.error('RAG indexing failed:', e.message);
  }
}

// List audiobooks from Audiobookshelf if configured, otherwise from local folder
app.get('/api/audiobooks', async (req, res) => {
  try {
    if (ABS_URL && ABS_TOKEN) {
      // Minimal ABS fetch: list libraries, then items from the first library
      const libs = await axios.get(`${ABS_URL}/api/libraries`, { headers: { Authorization: `Bearer ${ABS_TOKEN}` }});
      const firstLib = libs.data?.libraries?.[0];
      if (!firstLib) return res.json([]);
      const items = await axios.get(`${ABS_URL}/api/libraries/${firstLib.id}/items`, { headers: { Authorization: `Bearer ${ABS_TOKEN}` }});
      const books = (items.data?.results || []).filter(x => x.mediaType === 'book').map(x => ({
        id: x.id,
        title: x.media?.metadata?.title || x.title || x.id,
        author: x.media?.metadata?.authorName || '',
        coverUrl: `${ABS_URL}/api/items/${x.id}/cover?token=${ABS_TOKEN}`,
        source: 'audiobookshelf'
      }));
      return res.json(books);
    }

    // Local folder mode: each subfolder is a book
    const entries = fs.existsSync(AUDIOBOOKS_DIR) ? fs.readdirSync(AUDIOBOOKS_DIR, { withFileTypes: true }) : [];
    const books = entries.filter(e => e.isDirectory()).map(dir => ({
      id: dir.name,
      title: dir.name,
      author: '',
      coverUrl: '',
      source: 'local'
    }));
    res.json(books);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: 'Failed to list audiobooks' });
  }
});

// For local source, list tracks
app.get('/api/audiobooks/:bookId/tracks', (req, res) => {
  const bookId = req.params.bookId;
  const dir = path.join(AUDIOBOOKS_DIR, bookId);
  if (!fs.existsSync(dir)) return res.json([]);
  const audioExt = new Set(['.mp3', '.m4a', '.m4b', '.ogg', '.flac', '.wav']);
  const files = fs.readdirSync(dir).filter(f => audioExt.has(path.extname(f).toLowerCase()))
    .sort((a,b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
  const tracks = files.map(f => ({ file: f, url: `/audiobooks/files/${encodeURIComponent(bookId)}/${encodeURIComponent(f)}` }));
  res.json(tracks);
});

// Static streaming for local files
app.get('/audiobooks/files/:bookId/:file', (req, res) => {
  const p = path.join(AUDIOBOOKS_DIR, req.params.bookId, req.params.file);
  if (!fs.existsSync(p)) return res.status(404).end();
  res.sendFile(p);
});

// Serve transcript
app.get('/api/audiobooks/:bookId/transcript', (req, res) => {
  const out = path.join(TRANSCRIPT_DIR, `${req.params.bookId}.txt`);
  if (!fs.existsSync(out)) return res.status(404).json({ error: 'Transcript not found' });
  res.setHeader('Content-Type', 'text/plain; charset=utf-8');
  fs.createReadStream(out).pipe(res);
});

// Job status
app.get('/api/audiobooks/:bookId/transcribe/status', (req, res) => {
  const job = jobs.get(req.params.bookId);
  if (!job) return res.json({ status: 'idle' });
  res.json({ status: job.status, progress: job.progress ?? 0 });
});

// Transcription pipeline (local tracks)
app.post('/api/audiobooks/:bookId/transcribe', async (req, res) => {
  const bookId = req.params.bookId;
  const tracks = listLocalTracks(bookId);
  if (!tracks.length) return res.status(400).json({ error: 'No local tracks found.' });

  const job = { status: 'in_progress', progress: 0 };
  jobs.set(bookId, job);
  res.json({ status: 'in_progress' });

  (async () => {
    const out = path.join(TRANSCRIPT_DIR, `${bookId}.txt`);
    try {
      let text = '';
      for (let i = 0; i < tracks.length; i++) {
        const part = await transcribeFile(tracks[i]);
        text += `\n\n=== Track ${i+1}/${tracks.length}: ${path.basename(tracks[i])} ===\n\n` + part + '\n';
        job.progress = Math.round(((i+1) / tracks.length) * 100);
      }
      fs.writeFileSync(out, text, 'utf-8');
      job.status = 'completed';
      job.progress = 100;
      await indexWithRag(bookId, bookId, text);
    } catch (e) {
      console.error('Transcription failed:', e.message);
      job.status = 'failed';
    }
  })();
});

// Health
app.get('/health', (_, res) => res.json({ ok: true }));

app.listen(PORT, () => console.log(`Audiobooks API listening on :${PORT}`));
"""

    dockerfile = r"""
FROM node:20-alpine
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --production
COPY src ./src
ENV PORT=3456
EXPOSE 3456
CMD ["npm", "start"]
"""

    dockerignore = ".git\nnode_modules\nnpm-debug.log\n"

    write_file(svc / 'package.json', json.dumps(package_json, indent=2))
    write_file(svc / 'src' / 'index.js', index_js)
    write_file(svc / 'Dockerfile', dockerfile)
    write_file(svc / '.dockerignore', dockerignore)


def create_web_page(base: Path):
    html = r"""
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>LibreAudioChat</title>
  <style>
    body { margin: 0; font-family: sans-serif; }
    .layout { display: grid; grid-template-columns: 1fr 420px; height: 100vh; }
    .left { padding: 16px; overflow: auto; }
    .right { border-left: 1px solid #ddd; }
    .player { margin-bottom: 16px; }
    .book { padding: 8px; border: 1px solid #ddd; border-radius: 6px; margin: 4px 0; cursor: pointer; }
    .tracks { margin-top: 8px; }
    iframe { width: 100%; height: 100%; border: 0; }
    .actions { display:flex; gap: 8px; margin-bottom: 8px; }
    .badge { display:inline-block; padding:4px 8px; border:1px solid #999; border-radius:12px; font-size:12px; }
  </style>
  <script>
  async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  }
  async function loadBooks() {
    const list = document.getElementById('books');
    list.innerHTML = 'Loading...';
    try {
      const books = await fetchJSON('/api/audiobooks');
      list.innerHTML = '';
      for (const b of books) {
        const div = document.createElement('div');
        div.className = 'book';
        div.textContent = b.title + (b.author ? (' — ' + b.author) : '');
        div.onclick = () => selectBook(b);
        list.appendChild(div);
      }
    } catch (e) {
      list.textContent = 'Failed to load books: ' + e.message;
    }
  }
  let currentBook = null;
  async function selectBook(book) {
    currentBook = book;
    document.getElementById('currentBook').textContent = book.title;
    const tracksEl = document.getElementById('tracks');
    tracksEl.innerHTML = 'Loading tracks...';
    try {
      const tracks = await fetchJSON(`/api/audiobooks/${encodeURIComponent(book.id)}/tracks`);
      tracksEl.innerHTML = '';
      for (const t of tracks) {
        const a = document.createElement('a');
        a.href = t.url;
        a.textContent = t.file;
        a.onclick = (ev) => { ev.preventDefault(); playTrack(t.url); };
        const li = document.createElement('div');
        li.appendChild(a);
        tracksEl.appendChild(li);
      }
    } catch (e) {
      tracksEl.textContent = 'Failed to load tracks: ' + e.message;
    }
  }
  function playTrack(url) {
    const audio = document.getElementById('audio');
    audio.src = url;
    audio.play();
  }
  async function startTranscription() {
    const status = document.getElementById('status');
    if (!currentBook) { status.textContent = 'Select a book first.'; return; }
    status.textContent = 'Starting transcription...';
    try {
      await fetch(`/api/audiobooks/${encodeURIComponent(currentBook.id)}/transcribe`, { method: 'POST'});
      pollStatus();
    } catch (e) {
      status.textContent = 'Failed to start transcription: ' + e.message;
    }
  }
  async function pollStatus() {
    const status = document.getElementById('status');
    if (!currentBook) return;
    try {
      const s = await fetchJSON(`/api/audiobooks/${encodeURIComponent(currentBook.id)}/transcribe/status`);
      status.textContent = `Status: ${s.status}${s.progress? ' ('+s.progress+'%)':''}`;
      if (s.status === 'in_progress') setTimeout(pollStatus, 2000);
      if (s.status === 'completed') loadTranscript();
    } catch (e) {
      status.textContent = 'Status error: ' + e.message;
    }
  }
  async function loadTranscript() {
    const out = document.getElementById('transcript');
    if (!currentBook) return;
    try {
      const res = await fetch(`/api/audiobooks/${encodeURIComponent(currentBook.id)}/transcript`);
      if (res.ok) {
        out.textContent = await res.text();
      } else {
        out.textContent = 'Transcript not found.';
      }
    } catch (e) {
      out.textContent = 'Load transcript error: ' + e.message;
    }
  }
  window.addEventListener('load', () => {
    loadBooks();
    const chatUrl = (new URLSearchParams(location.search)).get('chat') || 'http://localhost:3080';
    document.getElementById('chat').src = chatUrl;
  });
  </script>
  </head>
  <body>
    <div class="layout">
      <div class="left">
        <h2 id="currentBook">Audiobooks</h2>
        <div class="player">
          <audio id="audio" controls preload="none" style="width:100%"></audio>
        </div>
        <div class="actions">
          <button onclick="startTranscription()">Transcribe Book</button>
          <span id="status" class="badge">Idle</span>
        </div>
        <div id="books"></div>
        <div class="tracks" id="tracks"></div>
        <pre id="transcript" style="white-space:pre-wrap"></pre>
      </div>
      <div class="right">
        <iframe id="chat" src="about:blank" title="Chat"></iframe>
      </div>
    </div>
  </body>
</html>
"""
    write_file(base / 'audiobooks-api' / 'src' / 'web' / 'index.html', html)


def create_extension(base: Path):
    manifest = {
        "manifest_version": 3,
        "name": "LibreAudioChat Sidebar",
        "version": "0.1.0",
        "description": "Quick chat sidebar embedding LibreChat UI.",
        "action": { "default_popup": "popup.html", "default_title": "LibreAudioChat" },
        "permissions": ["storage"],
        "host_permissions": ["http://localhost:3080/*", "https://*/" ]
    }
    popup_html = r"""
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LibreAudioChat</title>
    <style>
      html, body { margin: 0; padding: 0; height: 600px; width: 420px; }
      iframe { width: 100%; height: 100%; border: 0; }
    </style>
  </head>
  <body>
    <iframe src="http://localhost:3080"></iframe>
  </body>
</html>
"""
    write_file(base / 'extension' / 'manifest.json', json.dumps(manifest, indent=2))
    write_file(base / 'extension' / 'popup.html', popup_html)


def create_desktop_app(base: Path):
    desktop_py = r"""
import os
import sys
from pathlib import Path
import json

from PyQt5.QtCore import Qt, QUrl, QTimer
from PyQt5.QtWidgets import QApplication, QWidget, QHBoxLayout, QVBoxLayout, QListWidget, QPushButton, QLabel, QInputDialog
from PyQt5.QtMultimedia import QMediaPlayer, QMediaContent
from PyQt5.QtWebEngineWidgets import QWebEngineView

import requests

API_URL = os.environ.get('AUDIOBOOKS_API_URL', '')
CHAT_URL = os.environ.get('CHAT_URL') or os.environ.get('LIBRECHAT_URL') or 'http://localhost:3080'


class DesktopApp(QWidget):
    def __init__(self):
        super().__init__()
        self.setWindowTitle('LibreAudioChat')
        self.resize(1200, 800)

        layout = QHBoxLayout(self)

        # Left panel: books + tracks + audio
        left = QVBoxLayout()
        self.books = QListWidget()
        self.tracks = QListWidget()
        self.status = QLabel('Ready')
        self.btnTranscribe = QPushButton('Transcribe Book')

        self.player = QMediaPlayer(self)

        left.addWidget(QLabel('Audiobooks'))
        left.addWidget(self.books)
        left.addWidget(QLabel('Tracks'))
        left.addWidget(self.tracks)
        left.addWidget(self.btnTranscribe)
        left.addWidget(self.status)

        # Right panel: web chat
        self.web = QWebEngineView()
        self.web.setUrl(QUrl(CHAT_URL))

        layout.addLayout(left, 1)
        layout.addWidget(self.web, 1)

        self.books.itemSelectionChanged.connect(self.on_book_select)
        self.tracks.itemDoubleClicked.connect(self.on_track_play)
        self.btnTranscribe.clicked.connect(self.on_transcribe)

        self.load_books()

    def load_books(self):
        self.books.clear()
        if not API_URL:
            self.status.setText('Audiobooks API not configured (client-only mode).')
            return
        try:
            r = requests.get(f"{API_URL}/api/audiobooks", timeout=10)
            r.raise_for_status()
            for b in r.json():
                self.books.addItem(f"{b.get('id')}:: {b.get('title')}")
        except Exception as e:
            self.status.setText(f"Failed to load books: {e}")

    def on_book_select(self):
        items = self.books.selectedItems()
        if not items:
            return
        book_id = items[0].text().split('::', 1)[0]
        self.tracks.clear()
        if not API_URL:
            self.status.setText('Audiobooks API not configured.')
            return
        try:
            r = requests.get(f"{API_URL}/api/audiobooks/{book_id}/tracks", timeout=10)
            r.raise_for_status()
            for t in r.json():
                self.tracks.addItem(f"{t.get('url')}")
        except Exception as e:
            self.status.setText(f"Failed to load tracks: {e}")

    def on_track_play(self):
        items = self.tracks.selectedItems()
        if not items:
            return
        url = items[0].text()
        self.player.setMedia(QMediaContent(QUrl(url)))
        self.player.play()

    def on_transcribe(self):
        items = self.books.selectedItems()
        if not items:
            self.status.setText('Select a book first.')
            return
        book_id = items[0].text().split('::',1)[0]
        self.status.setText('Starting transcription...')
        if not API_URL:
            self.status.setText('Audiobooks API not configured.')
            return
        try:
            requests.post(f"{API_URL}/api/audiobooks/{book_id}/transcribe", timeout=10)
            self.status.setText('Transcription started. Polling status...')
            self.poll_status(book_id)
        except Exception as e:
            self.status.setText(f"Failed to start transcription: {e}")

    def poll_status(self, book_id):
        try:
            r = requests.get(f"{API_URL}/api/audiobooks/{book_id}/transcribe/status", timeout=10)
            s = r.json()
            self.status.setText(f"Status: {s.get('status')} ({s.get('progress',0)}%)")
            if s.get('status') == 'in_progress':
                QTimer.singleShot(2000, lambda: self.poll_status(book_id))
        except Exception as e:
            self.status.setText(f"Status error: {e}")


if __name__ == '__main__':
    app = QApplication(sys.argv)
    w = DesktopApp()
    w.show()
    sys.exit(app.exec_())
"""
    req_txt = "PyQt5\nPyQtWebEngine\nrequests\n"
    write_file(base / 'desktop' / 'desktop_app.py', desktop_py)
    write_file(base / 'desktop' / 'requirements.txt', req_txt)


def create_compose(base: Path):
    compose = r"""
version: '3.8'
services:
  audiobooks-api:
    build: ./audiobooks-api
    environment:
      - AUDIOBOOKS_DIR=/audiobooks
      - ABS_URL=${ABS_URL:-}
      - ABS_TOKEN=${ABS_TOKEN:-}
      - LIBRECHAT_URL=${LIBRECHAT_URL:-http://localhost:3080}
      - TRANSCRIPT_DIR=/data/transcripts
      - STT_PROVIDER=${STT_PROVIDER:-localai}
      - STT_URL=${STT_URL:-http://localai:8080/v1/audio/transcriptions}
      - STT_API_KEY=${STT_API_KEY:-}
      - STT_MODEL=${STT_MODEL:-whisper-1}
      - RAG_INDEX_URL=${RAG_INDEX_URL:-}
      - RAG_API_KEY=${RAG_API_KEY:-}
    volumes:
      - ./audiobooks:/audiobooks:ro
      - ./transcripts:/data/transcripts
    ports:
      - "3456:3456"

  audiobookshelf:
    image: ghcr.io/advplyr/audiobookshelf:latest
    container_name: audiobookshelf
    environment:
      - TZ=UTC
    volumes:
      - ./audiobooks:/audiobooks
      - ./abs-config:/config
      - ./abs-metadata:/metadata
    ports:
      - "13378:80"
    restart: unless-stopped

  # Optional offline LLM/STT provider (OpenAI-compatible API)
  localai:
    image: ghcr.io/go-skynet/local-ai:latest
    environment:
      - MODELS_PATH=/models
    volumes:
      - ./models:/models
    ports:
      - "8080:8080"

# Notes:
# - Run LibreChat (from ./librechat) using its own docker-compose or npm scripts
# - This compose launches Audiobookshelf, Audiobooks API and optional LocalAI.
"""
    env_example = (
        "# Example environment overrides\n"
        "ABS_URL=http://localhost:13378\n"
        "ABS_TOKEN=REPLACE_WITH_YOUR_ABS_API_TOKEN\n"
        "LIBRECHAT_URL=http://localhost:3080\n"
        "# STT provider can be 'localai' (offline) or 'openai'\n"
        "STT_PROVIDER=localai\n"
        "STT_URL=http://localhost:8080/v1/audio/transcriptions\n"
        "STT_MODEL=whisper-1\n"
        "# RAG index URL to send transcript text (optional)\n"
        "RAG_INDEX_URL=http://localhost:PORT/path/to/rag/ingest\n"
        "RAG_API_KEY=YOUR_RAG_TOKEN\n"
    )
    write_file(base / 'docker-compose.yml', compose)
    write_file(base / '.env.example', env_example)


def create_readme(base: Path):
    md = r"""
# LibreAudioChat (Builder Output)

This folder was generated by `tools/libreaudiochat_builder.py`.

Components:
- `librechat/` – upstream LibreChat cloned repo (if git clone succeeded)
- `audiobooks-api/` – Node/Express microservice for audiobooks
- `extension/` – Minimal MV3 browser extension embedding LibreChat UI
- `desktop/` – PyQt desktop prototype with built-in audio player and chat webview
- `audiobooks/` – Mount your audiobook files here (local mode)

Run modes:
- Client-only (no Docker): double-click `start_client.bat` and optionally enter a server URL (e.g. a LinguaCafe or LibreChat URL). The desktop app will load that in the chat pane and skip audiobooks.
- Full stack (offline): double-click `start_offline.bat` to start Audiobooks API + Audiobookshelf (+ LocalAI), then launch the desktop app.
  - Run LibreChat separately from `./librechat` per upstream docs if you want its UI.

Web player + chat:
- Open http://localhost:3456 in a browser (serves a simple page with audio + chat sidebar)
  - Provide `?chat=http://host:port` to point the sidebar to a different LibreChat URL

Environment:
- Copy `.env.example` to `.env` and edit values as needed

Desktop app:
- `cd desktop && pip install -r requirements.txt`
- `python desktop_app.py`
  - Environment variables (optional):
    - `CHAT_URL` to point the chat panel at any server (e.g. LinguaCafe URL)
    - `AUDIOBOOKS_API_URL` if you have the audiobooks API running (omit for client-only)

Extension:
- Load `extension/` as an unpacked extension in Chromium-based browsers

STT and RAG:
- Configure STT via `.env`:
  - `STT_PROVIDER=localai` for offline (via LocalAI) or `openai` for cloud
  - `STT_URL` and `STT_MODEL` per your provider
  - Place Whisper models in `./models` if using LocalAI (it can also download if allowed)
- Start transcription: `POST /api/audiobooks/:bookId/transcribe`, then poll `/status` or fetch `/transcript`.
- Optional: set `RAG_INDEX_URL` to POST transcript text into your RAG indexer.
  Adjust endpoint to your LibreChat RAG API ingestion route.
"""
    write_file(base / 'README.md', md)


def create_launchers(base: Path):
    start_bat = r"""
@echo off
setlocal
cd /d %~dp0
echo Starting Audiobooks API and Audiobookshelf...
docker compose up -d --build
if %errorlevel% neq 0 (
  echo Docker compose failed. Make sure Docker Desktop is running.
  pause
  exit /b 1
)
echo Installing desktop app dependencies (first run may take a while)...
python -m pip install -r desktop\requirements.txt
echo Launching desktop app...
python desktop\desktop_app.py
endlocal
"""

    build_exe_bat = r"""
@echo off
setlocal
cd /d %~dp0
python -m pip install --upgrade pip
python -m pip install pyinstaller -r requirements.txt
pyinstaller --noconfirm --onefile --name LibreAudioChat desktop_app.py
echo Built exe under dist\LibreAudioChat.exe
endlocal
"""

    write_file(base / 'start_offline.bat', start_bat)
    write_file(base / 'desktop' / 'build_exe.bat', build_exe_bat)
    # Client-only launcher (optional connection to server via CHAT_URL)
    start_client_bat = r"""
@echo off
setlocal
cd /d %~dp0
REM Client-only launcher: no Docker required

REM Load CHAT_URL from .env if present
if exist .env (
  for /f "usebackq tokens=1,2 delims==" %%A in (".env") do (
    if /I "%%A"=="CHAT_URL" set CHAT_URL=%%B
  )
)

if "%CHAT_URL%"=="" (
  echo Optional: enter server URL (e.g. http://localhost:8000 for LinguaCafe), or leave blank for none:
  set /p CHAT_URL=
)

echo Installing desktop app dependencies (first run may take a while)...
python -m pip install -r desktop\requirements.txt

set CHAT_URL=%CHAT_URL%
set AUDIOBOOKS_API_URL=
python desktop\desktop_app.py
endlocal
"""
    write_file(base / 'start_client.bat', start_client_bat)


def main():
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    print(f"Output directory: {OUT_DIR}")

    # Try to clone LibreChat
    clone_librechat(OUT_DIR)

    # Create microservices and clients
    create_audiobooks_api(OUT_DIR)
    create_web_page(OUT_DIR)
    create_extension(OUT_DIR)
    create_desktop_app(OUT_DIR)
    create_compose(OUT_DIR)
    create_readme(OUT_DIR)

    print("\nAll done! See LibreAudioChat/ for details.")


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)
