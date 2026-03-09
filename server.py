import http.server
import socketserver
import json
import uuid
import time
import threading
import os
from typing import Dict, Any
from checkers_logic import CheckersGame

PORT = 8000
games: Dict[str, Dict[str, Any]] = {} # game_id -> {"game": CheckersGame, "last_activity": float}

# Cleanup thread
def cleanup_games():
    while True:
        time.sleep(60)
        current_time = time.time()
        to_delete = []
        for gid, gdata in games.items():
            if current_time - gdata["last_activity"] > 1800: # 30 mins
                to_delete.append(gid)
        for gid in to_delete:
            games.pop(gid, None)

thread = threading.Thread(target=cleanup_games, daemon=True)
thread.start()

class CheckersHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path.startswith("/api/state"):
            from urllib.parse import urlparse, parse_qs
            parsed = urlparse(self.path)
            query = parse_qs(parsed.query)
            game_id = query.get("game_id", [None])[0]
            
            if game_id and game_id in games:
                game_data = games[game_id]
                game_data["last_activity"] = time.time()
                state = game_data["game"].get_state()
                self._send_json(200, state)
            else:
                self._send_json(404, {"error": "Game not found"})
            return
            
        # Default behavior: serve static files. 
        # For security, restrict it to the current directory explicitly.
        if self.path == "/":
            self.path = "/index.html"
        return super().do_GET()

    def do_POST(self):
        if self.path == "/api/new_game":
            game_id = str(uuid.uuid4())
            session_id = str(uuid.uuid4())
            game = CheckersGame()
            game.player_r = session_id
            games[game_id] = {"game": game, "last_activity": time.time()}
            
            self._send_json(200, {
                "game_id": game_id,
                "session_id": session_id,
                "color": "r"
            })
            return
            
        elif self.path == "/api/join_game":
            data = self._read_json()
            game_id = str(data.get("game_id", ""))
            if not game_id or game_id not in games:
                self._send_json(404, {"error": "Game not found"})
                return
                
            game_data = games[game_id]
            game = game_data["game"]
            game_data["last_activity"] = time.time()
            
            if game.status != "waiting":
                self._send_json(400, {"error": "Game already started or finished"})
                return
                
            session_id = str(uuid.uuid4())
            game.player_b = session_id
            game.status = "playing"
            
            self._send_json(200, {
                "session_id": session_id,
                "color": "b"
            })
            return
            
        elif self.path == "/api/move":
            data = self._read_json()
            game_id = str(data.get("game_id", ""))
            session_id = data.get("session_id")
            path = data.get("path")
            
            if not game_id or game_id not in games:
                self._send_json(404, {"error": "Game not found"})
                return
                
            game_data = games[game_id]
            game = game_data["game"]
            game_data["last_activity"] = time.time()
            
            # make_move(session, path) returns True/False and an error string
            # wait, my logic file doesn't have the method signature that takes session and path...
            # ah, yes it does: def make_move(self, player_session, path)
            success, msg = game.make_move(session_id, path)
            if success:
                self._send_json(200, {"success": True})
            else:
                self._send_json(400, {"error": msg})
            return
            
        self._send_json(404, {"error": "Endpoint not found"})

    def _read_json(self):
        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length)
        try:
            return json.loads(body)
        except:
            return {}

    def _send_json(self, status_code, payload):
        self.send_response(status_code)
        self.send_header('Content-type', 'application/json')
        # Add CORS headers if needed
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(json.dumps(payload).encode('utf-8'))

if __name__ == "__main__":
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    Handler = CheckersHandler
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        print(f"Serving at port {PORT}")
        httpd.serve_forever()
