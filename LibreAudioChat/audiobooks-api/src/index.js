
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
