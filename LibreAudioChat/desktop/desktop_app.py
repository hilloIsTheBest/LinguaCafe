
import os
import sys
from pathlib import Path

from PyQt5.QtCore import Qt, QUrl, QTimer, QSize
from PyQt5.QtGui import QPixmap
from PyQt5.QtWidgets import (
    QApplication, QWidget, QHBoxLayout, QVBoxLayout, QListWidget, QPushButton, QLabel,
    QLineEdit, QScrollArea, QFrame, QGridLayout, QStackedLayout, QSplitter, QSlider,
    QStyle, QListWidgetItem, QInputDialog
)
from PyQt5.QtMultimedia import QMediaPlayer, QMediaContent
from PyQt5.QtWebEngineWidgets import QWebEngineView

import requests
import json

API_URL = os.environ.get('AUDIOBOOKS_API_URL', '')
CHAT_URL = os.environ.get('CHAT_URL') or os.environ.get('LIBRECHAT_URL') or 'http://localhost:3080'


def make_card(title: str, subtitle: str = '') -> QFrame:
    card = QFrame()
    card.setFrameShape(QFrame.StyledPanel)
    card.setStyleSheet("QFrame{background:#1f1f1f;border-radius:10px;border:1px solid #2a2a2a;} QLabel{color:#ddd;}")
    v = QVBoxLayout(card)
    thumb = QLabel(" ")
    thumb.setFixedHeight(120)
    thumb.setStyleSheet("background:#2a2a2a;border-top-left-radius:10px;border-top-right-radius:10px;")
    v.addWidget(thumb)
    t = QLabel(title)
    t.setStyleSheet("font-weight:600;padding:6px 10px 0 10px;")
    v.addWidget(t)
    s = QLabel(subtitle)
    s.setStyleSheet("padding:0 10px 10px 10px;color:#aaa;font-size:11px;")
    v.addWidget(s)
    return card


class DesktopApp(QWidget):
    def __init__(self):
        super().__init__()
        self.setWindowTitle('LibreAudioChat')
        self.resize(1320, 860)

        # Top bar (navigation)
        root = QVBoxLayout(self)
        top = QHBoxLayout()
        self.btnLibrary = QPushButton('Library')
        self.btnPlaylists = QPushButton('Playlists')
        self.btnVocabulary = QPushButton('Vocabulary')
        self.search = QLineEdit()
        self.search.setPlaceholderText('Search Library')
        self.level = QSlider(Qt.Horizontal)
        self.level.setMinimum(1); self.level.setMaximum(5); self.level.setValue(3)
        self.btnToggleUi = QPushButton('OG UI')
        self.btnSetServer = QPushButton('Set Server')
        for b in (self.btnLibrary, self.btnPlaylists, self.btnVocabulary, self.btnToggleUi, self.btnSetServer):
            b.setCursor(Qt.PointingHandCursor)
        self.search.setFixedWidth(280)
        top.addWidget(self.btnLibrary); top.addWidget(self.btnPlaylists); top.addWidget(self.btnVocabulary)
        top.addSpacing(12); top.addWidget(self.search); top.addSpacing(12)
        top.addWidget(QLabel('Beginner')); top.addWidget(self.level); top.addWidget(QLabel('Advanced'))
        top.addStretch(1)
        top.addWidget(self.btnToggleUi)
        top.addWidget(self.btnSetServer)
        root.addLayout(top)

        # Stacked layouts: modern dashboard vs classic layout
        self.stack = QStackedLayout()
        root.addLayout(self.stack, 1)

        # Modern dashboard view
        modern = QWidget(); modernL = QHBoxLayout(modern)
        # Left: dashboard scroll
        dashScroll = QScrollArea(); dashScroll.setWidgetResizable(True)
        dashWrap = QWidget(); dashLayout = QVBoxLayout(dashWrap)
        dashWrap.setStyleSheet("background:#121212;")

        # Provider row
        providers = QHBoxLayout()
        for name in ['LingQ', 'Netflix', 'TED', 'YouTube']:
            btn = QPushButton(name)
            btn.setFixedHeight(64)
            btn.setStyleSheet("QPushButton{background:#1e1e1e;color:#e0e0e0;border:1px solid #2a2a2a;border-radius:12px;font-weight:700;}")
            providers.addWidget(btn)
        dashLayout.addLayout(providers)

        # Sections
        self.contGrid = self._make_grid_section('Continue Studying')
        self.booksGrid = self._make_grid_section('Books')
        self.forYouGrid = self._make_grid_section('For You')
        dashLayout.addLayout(self.contGrid['layout'])
        dashLayout.addLayout(self.booksGrid['layout'])
        dashLayout.addLayout(self.forYouGrid['layout'])
        dashLayout.addStretch(1)
        dashScroll.setWidget(dashWrap)

        # Right: chat
        # load persisted config
        try:
            cfg_path = (Path(sys.executable).parent if getattr(sys, 'frozen', False) else Path(__file__).resolve().parent) / 'client_config.json'
            cfg = json.loads(cfg_path.read_text(encoding='utf-8')) if cfg_path.exists() else {}
        except Exception:
            cfg = {}
        self.chat_url = cfg.get('chat_url', CHAT_URL)
        self.api_url = cfg.get('api_url', API_URL)

        self.web = QWebEngineView(); self.web.setUrl(QUrl(self.chat_url))
        split = QSplitter(); split.setChildrenCollapsible(False); split.addWidget(dashScroll); split.addWidget(self.web); split.setSizes([900, 420])
        modernL.addWidget(split)

        # Classic view (original)
        classic = QWidget(); classicL = QHBoxLayout(classic)
        left = QVBoxLayout()
        self.books = QListWidget(); self.tracks = QListWidget(); self.status = QLabel('Ready')
        self.btnTranscribe = QPushButton('Transcribe Book')
        self.player = QMediaPlayer(self)
        left.addWidget(QLabel('Audiobooks')); left.addWidget(self.books)
        left.addWidget(QLabel('Tracks')); left.addWidget(self.tracks)
        left.addWidget(self.btnTranscribe); left.addWidget(self.status)
        classicL.addLayout(left, 1)
        self.webClassic = QWebEngineView(); self.webClassic.setUrl(QUrl(self.chat_url))
        classicL.addWidget(self.webClassic, 1)

        self.stack.addWidget(modern)
        self.stack.addWidget(classic)
        self.stack.setCurrentIndex(0)

        # Signals
        self.btnToggleUi.clicked.connect(self._toggle_ui)
        self.btnSetServer.clicked.connect(self._set_server)
        self.books.itemSelectionChanged.connect(self.on_book_select)
        self.tracks.itemDoubleClicked.connect(self.on_track_play)
        self.btnTranscribe.clicked.connect(self.on_transcribe)

        # Data
        self._load_books_into_dashboard()
        self.load_books()

    def _toggle_ui(self):
        idx = 1 if self.stack.currentIndex() == 0 else 0
        self.stack.setCurrentIndex(idx)
        self.btnToggleUi.setText('OG UI' if idx == 1 else 'Modern UI')

    def _set_server(self):
        global CHAT_URL, API_URL
        chat, ok = QInputDialog.getText(self, 'Server URL', 'Chat URL:', text=self.chat_url)
        if ok and chat.strip():
            self.chat_url = chat.strip()
            self.web.setUrl(QUrl(self.chat_url))
            self.webClassic.setUrl(QUrl(self.chat_url))
        api, ok2 = QInputDialog.getText(self, 'Audiobooks API', 'API URL (leave blank to disable):', text=self.api_url)
        if ok2:
            self.api_url = api.strip()
        try:
            cfg_path = (Path(sys.executable).parent if getattr(sys, 'frozen', False) else Path(__file__).resolve().parent) / 'client_config.json'
            cfg_path.write_text(json.dumps({'chat_url': self.chat_url, 'api_url': self.api_url}, indent=2), encoding='utf-8')
        except Exception:
            pass
        self._load_books_into_dashboard()
        self.load_books()

    def _make_grid_section(self, title):
        titleLbl = QLabel(title); titleLbl.setStyleSheet("color:#e0e0e0;font-weight:700;font-size:16px;margin-top:12px;")
        grid = QGridLayout(); grid.setHorizontalSpacing(12); grid.setVerticalSpacing(12)
        wrap = QVBoxLayout(); wrap.addWidget(titleLbl); wrap.addLayout(grid)
        return { 'layout': wrap, 'grid': grid }

    def _load_books_into_dashboard(self):
        # Clear grids
        for grid in [self.contGrid['grid'], self.booksGrid['grid'], self.forYouGrid['grid']]:
            while grid.count():
                w = grid.itemAt(0).widget()
                grid.removeItem(grid.itemAt(0))
                if w:
                    w.setParent(None)
        # Populate from API if available, else placeholders
        items = []
        if self.api_url:
            try:
                r = requests.get(f"{self.api_url}/api/audiobooks", timeout=10); r.raise_for_status(); items = r.json()
            except Exception:
                items = []
        # Use first 12 as books; continue studying is first 4; for you next 8
        def add_cards(grid, books):
            row = col = 0
            for b in books:
                card = make_card(b.get('title') or b.get('id','Book'), b.get('author',''))
                grid.addWidget(card, row, col)
                col += 1
                if col >= 4:
                    col = 0; row += 1
        add_cards(self.contGrid['grid'], items[:4])
        add_cards(self.booksGrid['grid'], items[:12])
        add_cards(self.forYouGrid['grid'], items[4:12])

    # Classic view functions
    def load_books(self):
        self.books.clear()
        if not self.api_url:
            self.status.setText('Audiobooks API not configured (client-only mode).')
            return
        try:
            r = requests.get(f"{self.api_url}/api/audiobooks", timeout=10)
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
        if not self.api_url:
            self.status.setText('Audiobooks API not configured.')
            return
        try:
            r = requests.get(f"{self.api_url}/api/audiobooks/{book_id}/tracks", timeout=10)
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
        if not self.api_url:
            self.status.setText('Audiobooks API not configured.')
            return
        try:
            requests.post(f"{self.api_url}/api/audiobooks/{book_id}/transcribe", timeout=10)
            self.status.setText('Transcription started. Polling status...')
            self.poll_status(book_id)
        except Exception as e:
            self.status.setText(f"Failed to start transcription: {e}")

    def poll_status(self, book_id):
        try:
            r = requests.get(f"{self.api_url}/api/audiobooks/{book_id}/transcribe/status", timeout=10)
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
