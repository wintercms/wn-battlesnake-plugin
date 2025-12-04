<?php

use Illuminate\Support\Facades\Route;
use Winter\Battlesnake\Classes\APIController;
use Winter\Battlesnake\Classes\BoardDataTransformer;
use Winter\Battlesnake\Classes\LiveGame;
use Winter\Battlesnake\Models\GameLog;

Route::group(['prefix' => 'api/bs/{snake}/{password}'], function () {
    Route::get('/', [APIController::class, 'index']);
    Route::post('/start', [APIController::class, 'start']);
    Route::post('/move', [APIController::class, 'move']);
    Route::post('/end', [APIController::class, 'end']);
});

// Simple JSON polling endpoint for live game frames
Route::get('battlesnake/live/{gameId}/frames', function ($gameId) {
    $fromTurn = (int) request()->query('from', 0);

    $result = LiveGame::getFramesFrom($gameId, $fromTurn);

    return response()->json([
        'frames' => $result['frames'],
        'status' => $result['status'],
        'frame_count' => $result['frame_count'] ?? 0,
    ], 200, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Access-Control-Allow-Origin' => '*',
    ]);
});

// Serve live board viewer (query param version for CLI --board-url)
Route::get('battlesnake/live', function () {
    $gameId = request()->query('game');

    if (!$gameId) {
        abort(400, 'Missing game parameter');
    }

    return redirect('battlesnake/live/' . $gameId . '?autoplay=true');
});

// Serve live board viewer with polling-based frame fetching
Route::get('battlesnake/live/{gameId}', function ($gameId) {
    // Read the board index.html
    $indexPath = plugins_path('winter/battlesnake/assets/board/index.html');
    if (!file_exists($indexPath)) {
        abort(500, 'Board assets not built');
    }

    $html = file_get_contents($indexPath);
    $boardBase = url('battlesnake/board');
    $pollUrl = url('battlesnake/live/' . $gameId . '/frames');

    // Check if autoplay is requested via URL
    $autoplay = request()->query('autoplay', 'false') === 'true';

    // Create the live game script that uses simple polling
    $liveScript = '
    <script>
    // Prevent SvelteKit routing
    history.replaceState(null, "", "' . $boardBase . '/?game=' . $gameId . '&engine=live' . ($autoplay ? '&autoplay=true' : '') . '");

    // Enable autoplay via localStorage only if requested
    ' . ($autoplay ? 'localStorage.setItem("setting.autoplay", "true");' : '// autoplay not enabled') . '

    (function() {
        var gameId = "' . $gameId . '";
        var pollUrl = "' . $pollUrl . '";
        var frames = [];
        var gameInfo = null;
        var gameEnded = false;
        var mockWsInstance = null;
        var lastTurn = -1;
        var wsReady = false;
        var pollInterval = null;

        // Convert frame format to engine event format
        function frameToEngineEvent(frame) {
            return {
                Type: "frame",
                Data: {
                    Turn: frame.turn,
                    Snakes: frame.snakes.map(function(s) {
                        return {
                            ID: s.id, Name: s.name, Author: s.author || "",
                            Color: s.color, HeadType: s.headType, TailType: s.tailType,
                            Health: s.health, Latency: parseInt(s.latency) || 0,
                            Body: s.body.map(function(p) { return { X: p.x, Y: p.y }; }),
                            Death: s.elimination ? { Turn: s.elimination.turn, Cause: s.elimination.cause, EliminatedBy: s.elimination.by } : null
                        };
                    }),
                    Food: frame.food.map(function(p) { return { X: p.x, Y: p.y }; }),
                    Hazards: (frame.hazards || []).map(function(p) { return { X: p.x, Y: p.y }; })
                }
            };
        }

        // Send a frame to the mock WebSocket
        function sendFrameToWs(frame) {
            if (!mockWsInstance) return;
            var msgEvent = { type: "message", data: JSON.stringify(frameToEngineEvent(frame)) };
            if (mockWsInstance.onmessage) mockWsInstance.onmessage(msgEvent);
            mockWsInstance._listeners.message.forEach(function(fn) { fn(msgEvent); });
        }

        // Send game_end to the mock WebSocket
        function sendGameEndToWs() {
            if (!mockWsInstance) return;
            var endEvent = { type: "message", data: JSON.stringify({ Type: "game_end" }) };
            if (mockWsInstance.onmessage) mockWsInstance.onmessage(endEvent);
            mockWsInstance._listeners.message.forEach(function(fn) { fn(endEvent); });
        }

        // Poll for new frames
        function pollFrames() {
            var url = pollUrl + "?from=" + (lastTurn + 1);

            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    // Process new frames
                    if (data.frames && data.frames.length > 0) {
                        data.frames.forEach(function(frame) {
                            if (frame.turn > lastTurn) {
                                frames.push(frame);
                                lastTurn = frame.turn;

                                // Build game info from first frame
                                if (!gameInfo) {
                                    gameInfo = {
                                        Game: {
                                            ID: gameId,
                                            Width: frame.width,
                                            Height: frame.height,
                                            Ruleset: { name: "standard" },
                                            Map: "standard",
                                            Timeout: 500,
                                            Source: "local"
                                        }
                                    };
                                }

                                // Send to WebSocket if ready
                                if (wsReady) {
                                    sendFrameToWs(frame);
                                }
                            }
                        });
                    }

                    // Check if game ended
                    if (data.status === "ended" && !gameEnded) {
                        gameEnded = true;
                        if (pollInterval) {
                            clearInterval(pollInterval);
                            pollInterval = null;
                        }
                        if (wsReady) {
                            sendGameEndToWs();
                        }
                        console.log("Game ended at turn " + lastTurn);
                    }
                })
                .catch(function(err) {
                    console.error("Poll error:", err);
                });
        }

        // Start polling every 100ms
        pollInterval = setInterval(pollFrames, 100);
        pollFrames(); // Initial poll

        // Mock WebSocket
        var OrigWS = window.WebSocket;
        window.WebSocket = function(url) {
            var self = this;
            this.url = url;
            this.readyState = 0;
            this.onopen = null;
            this.onmessage = null;
            this.onclose = null;
            this.onerror = null;
            this._listeners = { open: [], message: [], close: [], error: [] };

            if (url.includes("/games/") && url.includes("/events")) {
                mockWsInstance = self;
                setTimeout(function() {
                    self.readyState = 1;
                    var openEvent = { type: "open" };
                    if (self.onopen) self.onopen(openEvent);
                    self._listeners.open.forEach(function(fn) { fn(openEvent); });

                    wsReady = true;

                    // Send any cached frames
                    frames.forEach(function(frame) {
                        sendFrameToWs(frame);
                    });

                    // If game already ended, send end event
                    if (gameEnded) {
                        sendGameEndToWs();
                    }
                }, 50);
            } else {
                return new OrigWS(url);
            }
        };
        window.WebSocket.prototype = {
            close: function() {
                this.readyState = 3;
                var closeEvent = { type: "close" };
                if (this.onclose) this.onclose(closeEvent);
                this._listeners.close.forEach(function(fn) { fn(closeEvent); });
            },
            send: function() {},
            addEventListener: function(type, fn) {
                if (this._listeners[type]) this._listeners[type].push(fn);
            },
            removeEventListener: function(type, fn) {
                if (this._listeners[type]) {
                    this._listeners[type] = this._listeners[type].filter(function(f) { return f !== fn; });
                }
            }
        };
        window.WebSocket.CONNECTING = 0;
        window.WebSocket.OPEN = 1;
        window.WebSocket.CLOSING = 2;
        window.WebSocket.CLOSED = 3;

        // Mock fetch for game info
        var origFetch = window.fetch;
        window.fetch = function(url, opts) {
            if (typeof url === "string" && url.includes("/games/") && !url.includes("/events")) {
                return new Promise(function(resolve) {
                    var checkInfo = function() {
                        if (gameInfo) {
                            resolve({ ok: true, status: 200, json: function() { return Promise.resolve(gameInfo); } });
                        } else {
                            setTimeout(checkInfo, 100);
                        }
                    };
                    checkInfo();
                });
            }
            return origFetch.apply(this, arguments);
        };
    })();
    </script>';

    // Inject the live script right after <head>
    $html = str_replace('<head>', '<head>' . $liveScript, $html);

    // Rewrite relative asset paths to absolute
    $html = str_replace('./_app/', $boardBase . '/_app/', $html);
    $html = str_replace('./favicon', $boardBase . '/favicon', $html);
    $html = str_replace('./apple-touch-icon', $boardBase . '/apple-touch-icon', $html);

    // Fix SvelteKit base path - replace dynamic calculation with static value
    // This ensures settings link points to /battlesnake/board/settings instead of /battlesnake/live/.../settings
    // Note: SvelteKit expects a path, not a full URL, so we extract just the path portion
    $html = str_replace(
        'base: new URL(".", location).pathname.slice(0, -1)',
        'base: "/battlesnake/board"',
        $html
    );

    // Intercept clicks on /settings links and redirect to the correct path
    // This is needed because SvelteKit's compiled JS has hardcoded /settings links
    $settingsInterceptor = '
    <script>
    document.addEventListener("click", function(e) {
        var link = e.target.closest("a[href=\'/settings\']");
        if (link) {
            e.preventDefault();
            window.location.href = "/battlesnake/board/settings";
        }
    }, true);
    </script>';
    $html = str_replace('</head>', $settingsInterceptor . '</head>', $html);

    return response($html)->header('Content-Type', 'text/html');
});

// Serve board replay with injected game data
Route::get('battlesnake/replay/{gameId}', function ($gameId) {
    $game = GameLog::where('game_id', $gameId)->first();
    if (!$game) {
        abort(404, 'Game not found');
    }

    $frames = BoardDataTransformer::gameToFrames($game);
    $gameMeta = BoardDataTransformer::getGameMetadata($game);

    if (empty($frames)) {
        return response('No frame data available', 404);
    }

    $gameData = [
        'Game' => $gameMeta,
        'frames' => $frames,
    ];

    // Build game info for the mock API
    $gameInfo = [
        'Game' => [
            'ID' => $gameMeta['ID'],
            'Width' => $frames[0]['width'],
            'Height' => $frames[0]['height'],
            'Ruleset' => $gameMeta['Ruleset'],
            'Map' => $gameMeta['Map'],
            'Timeout' => $gameMeta['Timeout'],
        ]
    ];

    // Read the board's index.html
    $indexPath = plugins_path('winter/battlesnake/assets/board/index.html');
    if (!file_exists($indexPath)) {
        abort(500, 'Board assets not built');
    }

    $html = file_get_contents($indexPath);

    $boardBase = url('battlesnake/board');

    // Create the mock injection script
    $mockScript = '
    <script>
    // Prevent SvelteKit from trying to route - we are serving a static replay
    history.replaceState(null, "", "' . $boardBase . '/?game=' . $gameId . '&engine=mock");

    // Disable autoplay for gamelog replays (reset any previous --browser setting)
    localStorage.setItem("setting.autoplay", "false");

    window.__MOCK_GAME_DATA__ = ' . json_encode($gameData) . ';
    window.__MOCK_GAME_INFO__ = ' . json_encode($gameInfo) . ';

    (function() {
        var gameData = window.__MOCK_GAME_DATA__;
        var gameInfo = window.__MOCK_GAME_INFO__;

        function frameToEngineEvent(frame) {
            return {
                Type: "frame",
                Data: {
                    Turn: frame.turn,
                    Snakes: frame.snakes.map(function(s) {
                        return {
                            ID: s.id, Name: s.name, Author: s.author || "",
                            Color: s.color, HeadType: s.head, TailType: s.tail,
                            Health: s.health, Latency: parseInt(s.latency) || 0,
                            Body: s.body.map(function(p) { return { X: p.x, Y: p.y }; }),
                            Death: s.elimination ? { Turn: s.elimination.turn, Cause: s.elimination.cause, EliminatedBy: s.elimination.by } : null
                        };
                    }),
                    Food: frame.food.map(function(p) { return { X: p.x, Y: p.y }; }),
                    Hazards: (frame.hazards || []).map(function(p) { return { X: p.x, Y: p.y }; })
                }
            };
        }

        // Mock WebSocket with full EventTarget-like interface
        var OrigWS = window.WebSocket;
        window.WebSocket = function(url) {
            var self = this;
            this.url = url;
            this.readyState = 0;
            this.onopen = null;
            this.onmessage = null;
            this.onclose = null;
            this.onerror = null;
            this._listeners = { open: [], message: [], close: [], error: [] };

            if (url.includes("/games/") && url.includes("/events")) {
                setTimeout(function() {
                    self.readyState = 1;
                    var openEvent = { type: "open" };
                    if (self.onopen) self.onopen(openEvent);
                    self._listeners.open.forEach(function(fn) { fn(openEvent); });

                    gameData.frames.forEach(function(frame, i) {
                        setTimeout(function() {
                            var msgEvent = { type: "message", data: JSON.stringify(frameToEngineEvent(frame)) };
                            if (self.onmessage) self.onmessage(msgEvent);
                            self._listeners.message.forEach(function(fn) { fn(msgEvent); });

                            if (i === gameData.frames.length - 1) {
                                setTimeout(function() {
                                    var endEvent = { type: "message", data: JSON.stringify({ Type: "game_end" }) };
                                    if (self.onmessage) self.onmessage(endEvent);
                                    self._listeners.message.forEach(function(fn) { fn(endEvent); });
                                }, 10);
                            }
                        }, i * 2);
                    });
                }, 50);
            } else {
                return new OrigWS(url);
            }
        };
        window.WebSocket.prototype = {
            close: function() {
                this.readyState = 3;
                var closeEvent = { type: "close" };
                if (this.onclose) this.onclose(closeEvent);
                this._listeners.close.forEach(function(fn) { fn(closeEvent); });
            },
            send: function() {},
            addEventListener: function(type, fn) {
                if (this._listeners[type]) this._listeners[type].push(fn);
            },
            removeEventListener: function(type, fn) {
                if (this._listeners[type]) {
                    this._listeners[type] = this._listeners[type].filter(function(f) { return f !== fn; });
                }
            }
        };
        window.WebSocket.CONNECTING = 0;
        window.WebSocket.OPEN = 1;
        window.WebSocket.CLOSING = 2;
        window.WebSocket.CLOSED = 3;

        // Mock fetch for game info
        var origFetch = window.fetch;
        window.fetch = function(url, opts) {
            if (typeof url === "string" && url.includes("/games/") && !url.includes("/events")) {
                return Promise.resolve({ ok: true, status: 200, json: function() { return Promise.resolve(gameInfo); } });
            }
            return origFetch.apply(this, arguments);
        };
    })();
    </script>';

    // Inject the mock script right after <head>
    $html = str_replace('<head>', '<head>' . $mockScript, $html);

    // Rewrite relative asset paths to absolute
    $html = str_replace('./_app/', $boardBase . '/_app/', $html);
    $html = str_replace('./favicon', $boardBase . '/favicon', $html);
    $html = str_replace('./apple-touch-icon', $boardBase . '/apple-touch-icon', $html);

    // Fix SvelteKit base path - replace dynamic calculation with static value
    // This ensures settings link points to /battlesnake/board/settings instead of /battlesnake/replay/.../settings
    // Note: SvelteKit expects a path, not a full URL, so we use a hardcoded path
    $html = str_replace(
        'base: new URL(".", location).pathname.slice(0, -1)',
        'base: "/battlesnake/board"',
        $html
    );

    // Intercept clicks on /settings links and redirect to the correct path
    // This is needed because SvelteKit's compiled JS has hardcoded /settings links
    $settingsInterceptor = '
    <script>
    document.addEventListener("click", function(e) {
        var link = e.target.closest("a[href=\'/settings\']");
        if (link) {
            e.preventDefault();
            window.location.href = "/battlesnake/board/settings";
        }
    }, true);
    </script>';
    $html = str_replace('</head>', $settingsInterceptor . '</head>', $html);

    return response($html)->header('Content-Type', 'text/html');
});

// Serve board viewer static assets
Route::get('battlesnake/board/{path?}', function ($path = '') {
    $basePath = plugins_path('winter/battlesnake/assets/board');

    // Default to index.html for root/empty path
    if (empty($path) || $path === '/') {
        $path = 'index.html';
    }

    // Handle SvelteKit routes - serve corresponding .html files
    if ($path === 'settings') {
        $path = 'settings.html';
    }

    // Handle game API paths - these are normally mocked in JS, but if accessed directly, return 404 JSON
    // This prevents the file_exists check from failing with a generic 404
    if (str_starts_with($path, 'games/')) {
        return response()->json(['error' => 'Game not found. Use /battlesnake/live/ or /battlesnake/replay/ routes.'], 404);
    }

    $filePath = $basePath . '/' . $path;

    if (!file_exists($filePath)) {
        abort(404);
    }

    $mimeTypes = [
        'html' => 'text/html',
        'js' => 'application/javascript',
        'css' => 'text/css',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
    ];

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

    // For JS files, apply path fixes at serve time (don't edit compiled files)
    if ($ext === 'js') {
        $content = file_get_contents($filePath);
        // Fix settings link from /settings to /battlesnake/board/settings
        $content = str_replace('"href","/settings"', '"href","/battlesnake/board/settings"', $content);
        $content = str_replace('("/settings")', '("/battlesnake/board/settings")', $content);
        return response($content)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    return response()->file($filePath, ['Content-Type' => $contentType]);
})->where('path', '.*');
