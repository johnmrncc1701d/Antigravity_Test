import copy
from typing import List, Optional, Tuple, Set

class CheckersGame:
    def __init__(self):
        self.board: List[List[Optional[str]]] = self._initial_board()
        self.turn = "r"
        self.status = "waiting" # waiting, playing, won_r, won_b
        self.player_r: Optional[str] = None
        self.player_b: Optional[str] = None
        
    def _initial_board(self) -> List[List[Optional[str]]]:
        return [
            [None, "r", None, "r", None, "r", None, "r"],
            ["r", None, "r", None, "r", None, "r", None],
            [None, "r", None, "r", None, "r", None, "r"],
            [None, None, None, None, None, None, None, None],
            [None, None, None, None, None, None, None, None],
            ["b", None, "b", None, "b", None, "b", None],
            [None, "b", None, "b", None, "b", None, "b"],
            ["b", None, "b", None, "b", None, "b", None],
        ]

    def get_piece(self, r: int, c: int) -> Optional[str]:
        if 0 <= r < 8 and 0 <= c < 8:
            return self.board[r][c]
        return None

    def _get_moves(self, player):
        """Returns a list of all valid move paths for a player.
           If jumps are available, only jumps are returned (mandatory jump rule)."""
        jumps = []
        moves = []
        for r in range(8):
            for c in range(8):
                piece = self.get_piece(r, c)
                if piece and piece.lower() == player:
                    piece_jumps = self._get_jumps_for_piece(self.board, r, c, piece)
                    if piece_jumps:
                        jumps.extend(piece_jumps)
                    elif not jumps: # Don't bother finding simple moves if we already have jumps
                        piece_moves = self._get_simple_moves_for_piece(self.board, r, c, piece)
                        moves.extend(piece_moves)
        
        if jumps:
            return jumps
        return moves

    def _get_simple_moves_for_piece(self, board, r, c, piece):
        moves = []
        dirs = self._get_directions(piece)
        for dr, dc in dirs:
            nr, nc = r + dr, c + dc
            if 0 <= nr < 8 and 0 <= nc < 8 and board[nr][nc] is None:
                moves.append([[r, c], [nr, nc]])
        return moves
        
    def _get_jumps_for_piece(
        self, 
        board: List[List[Optional[str]]], 
        r: int, 
        c: int, 
        piece: str, 
        current_path: Optional[List[List[int]]] = None, 
        jumped_positions: Optional[Set[Tuple[int, int]]] = None
    ) -> List[List[List[int]]]:
        if current_path is None:
            current_path = [[r, c]]
        if jumped_positions is None:
            jumped_positions = set()
            
        jumps = []
        dirs = self._get_directions(piece)
        found_jump = False
        
        for dr, dc in dirs:
            nr, nc = r + dr, c + dc
            jr, jc = r + 2*dr, c + 2*dc
            
            if 0 <= jr < 8 and 0 <= jc < 8:
                jump_target = board[jr][jc]
                mid_piece = board[nr][nc]
                if mid_piece and mid_piece.lower() != piece.lower() and jump_target is None and (nr, nc) not in jumped_positions:
                    # Found a jump!
                    found_jump = True
                    new_board = copy.deepcopy(board)
                    new_board[jr][jc] = piece
                    new_board[r][c] = None
                    new_board[nr][nc] = None # Capture it
                    
                    # If this piece kings during a jump, its turn stops.
                    is_king_now = False
                    if piece == "r" and jr == 7:
                        new_board[jr][jc] = "R"
                        is_king_now = True
                    elif piece == "b" and jr == 0:
                        new_board[jr][jc] = "B"
                        is_king_now = True
                    
                    new_path = current_path + [[jr, jc]]
                    new_jumped = set(jumped_positions)
                    new_jumped.add((nr, nc))
                    
                    if is_king_now:
                        jumps.append(new_path)
                    else:
                        further_jumps = self._get_jumps_for_piece(new_board, jr, jc, piece, new_path, new_jumped)
                        if further_jumps:
                            jumps.extend(further_jumps)
                        else:
                            jumps.append(new_path)
                            
        return jumps
        
    def _get_directions(self, piece):
        if piece == "R" or piece == "B":
            return [(1, 1), (1, -1), (-1, 1), (-1, -1)]
        elif piece == "r":
            return [(1, 1), (1, -1)]
        elif piece == "b":
            return [(-1, 1), (-1, -1)]
        return []

    def make_move(self, player_session, path):
        if self.status != "playing":
            return False, "Game is not active."
        if (self.turn == "r" and self.player_r != player_session) or (self.turn == "b" and self.player_b != player_session):
            return False, "Not your turn or invalid session."
            
        valid_paths = self._get_moves(self.turn)
        if path not in valid_paths:
            return False, "Invalid move."
            
        # Apply the move to the board
        start_r, start_c = path[0]
        end_r, end_c = path[-1]
        piece = self.board[start_r][start_c]
        
        # Remove jumped pieces
        for i in range(len(path) - 1):
            r1, c1 = path[i]
            r2, c2 = path[i+1]
            if abs(r2 - r1) == 2:
                mid_r, mid_c = (r1 + r2) // 2, (c1 + c2) // 2
                self.board[mid_r][mid_c] = None
                
        self.board[start_r][start_c] = None
        self.board[end_r][end_c] = piece
        
        # Kinging
        if piece == "r" and end_r == 7:
            self.board[end_r][end_c] = "R"
        elif piece == "b" and end_r == 0:
            self.board[end_r][end_c] = "B"
            
        # Switch turns
        self.turn = "b" if self.turn == "r" else "r"
        
        # Check for win conditions
        if len(self._get_moves(self.turn)) == 0:
            # Current turn player has no moves, they lose
            self.status = "won_r" if self.turn == "b" else "won_b"
            
        return True, ""

    def get_state(self):
        return {
            "board": self.board,
            "turn": self.turn,
            "status": self.status,
            "valid_moves": self._get_moves(self.turn) if self.status == "playing" else []
        }
