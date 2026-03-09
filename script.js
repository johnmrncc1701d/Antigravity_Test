const API_BASE = '/api';

let gameId = null;
let sessionId = null;
let myColor = null;
let gameState = null;
let pollInterval = null;

let currentPath = [];
let validMoves = [];

const screens = {
    menu: document.getElementById('menu-screen'),
    game: document.getElementById('game-screen')
};

const boardEl = document.getElementById('checkers-board');
const turnIndicator = document.getElementById('turn-indicator');
const invitePanel = document.getElementById('invite-panel');
const gameIdDisplay = document.getElementById('game-id-display');
const menuError = document.getElementById('menu-error');

function showScreen(screenName) {
    Object.values(screens).forEach(s => s.classList.remove('active'));
    screens[screenName].classList.add('active');
}

document.getElementById('btn-create').addEventListener('click', async () => {
    try {
        const res = await fetch(`${API_BASE}/new_game`, { method: 'POST' });
        const data = await res.json();
        
        gameId = data.game_id;
        sessionId = data.session_id;
        myColor = data.color;
        
        gameIdDisplay.textContent = gameId;
        invitePanel.style.display = 'block';
        
        startGame();
    } catch (e) {
        menuError.textContent = "Error creating game.";
    }
});

document.getElementById('btn-join').addEventListener('click', async () => {
    const input = document.getElementById('input-join').value.trim();
    if (!input) return;
    
    try {
        const res = await fetch(`${API_BASE}/join_game`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game_id: input })
        });
        const data = await res.json();
        
        if (data.error) {
            menuError.textContent = data.error;
            return;
        }
        
        gameId = input;
        sessionId = data.session_id;
        myColor = data.color;
        
        invitePanel.style.display = 'none';
        startGame();
    } catch (e) {
        menuError.textContent = "Error joining game.";
    }
});

document.getElementById('btn-leave').addEventListener('click', () => {
    clearInterval(pollInterval);
    gameId = null;
    sessionId = null;
    gameState = null;
    showScreen('menu');
});

function startGame() {
    showScreen('game');
    initBoard();
    pollState();
    pollInterval = setInterval(pollState, 1000);
}

function initBoard() {
    boardEl.innerHTML = '';
    
    for (let renderRow = 0; renderRow < 8; renderRow++) {
        for (let renderCol = 0; renderCol < 8; renderCol++) {
            const sq = document.createElement('div');
            // If player is red ("r"), we want row 0 at the bottom.
            // If player is black ("b"), we want row 7 at the bottom.
            const r = myColor === 'r' ? 7 - renderRow : renderRow;
            const c = myColor === 'r' ? 7 - renderCol : renderCol;
            
            sq.className = `square ${(renderRow + renderCol) % 2 === 0 ? 'light' : 'dark'}`;
            sq.dataset.r = r;
            sq.dataset.c = c;
            
            sq.addEventListener('click', () => handleSquareClick(r, c));
            boardEl.appendChild(sq);
        }
    }
}

function getSquareEl(r, c) {
    return document.querySelector(`.square[data-r="${r}"][data-c="${c}"]`);
}

async function pollState() {
    if (!gameId) return;
    
    try {
        const res = await fetch(`${API_BASE}/state?game_id=${gameId}`);
        const state = await res.json();
        
        if (state.error) {
            alert("Game ended or not found");
            clearInterval(pollInterval);
            showScreen('menu');
            return;
        }
        
        gameState = state;
        validMoves = state.valid_moves || [];
        
        if (state.status === 'playing') {
            invitePanel.style.display = 'none';
        }
        
        // Only update board if we aren't mid-move, or if it's not our turn
        if (state.turn !== myColor || currentPath.length === 0) {
            renderBoard(state.board);
        }
        
        updateUI(state);
    } catch (e) {
        console.error("Poll error", e);
    }
}

function renderBoard(board) {
    // Clear all pieces and highlights
    document.querySelectorAll('.square').forEach(sq => {
        sq.innerHTML = '';
        sq.classList.remove('highlight', 'selected');
    });
    
    for (let r = 0; r < 8; r++) {
        for (let c = 0; c < 8; c++) {
            const piece = board[r][c];
            if (piece) {
                const sq = getSquareEl(r, c);
                if (!sq) continue;
                
                const pEl = document.createElement('div');
                pEl.className = 'piece';
                
                if (piece.toLowerCase() === 'r') pEl.classList.add('red');
                else if (piece.toLowerCase() === 'b') pEl.classList.add('cyan');
                
                if (piece === 'R' || piece === 'B') pEl.classList.add('king');
                
                sq.appendChild(pEl);
            }
        }
    }
    
    // Re-apply path highlights if we have a current path
    if (currentPath.length > 0) {
        currentPath.forEach(([pr, pc]) => {
            getSquareEl(pr, pc)?.classList.add('selected');
        });
        
        // Highlight next possible moves
        validMoves.forEach(move => {
            // Check if move starts with currentPath
            let match = true;
            for (let i = 0; i < currentPath.length; i++) {
                if (move[i][0] !== currentPath[i][0] || move[i][1] !== currentPath[i][1]) {
                    match = false;
                    break;
                }
            }
            if (match && move.length > currentPath.length) {
                const [nr, nc] = move[currentPath.length];
                getSquareEl(nr, nc)?.classList.add('highlight');
            }
        });
    }
}

function updateUI(state) {
    if (state.status === 'waiting') {
        turnIndicator.textContent = 'Waiting...';
    } else if (state.status === 'playing') {
        if (state.turn === myColor) {
            turnIndicator.textContent = 'YOUR TURN';
            turnIndicator.style.color = 'var(--valid-color)';
        } else {
            turnIndicator.textContent = "OPPONENT'S TURN";
            turnIndicator.style.color = 'var(--text-muted)';
        }
    } else if (state.status === 'won_r') {
        if(myColor === 'r') { turnIndicator.textContent = 'YOU WON!'; turnIndicator.style.color = 'var(--valid-color)'; }
        else { turnIndicator.textContent = 'YOU LOST'; turnIndicator.style.color = 'var(--p1-color)'; }
    } else if (state.status === 'won_b') {
        if(myColor === 'b') { turnIndicator.textContent = 'YOU WON!'; turnIndicator.style.color = 'var(--valid-color)'; }
        else { turnIndicator.textContent = 'YOU LOST'; turnIndicator.style.color = 'var(--p1-color)'; }
    }
}

function handleSquareClick(r, c) {
    if (!gameState || gameState.status !== 'playing' || gameState.turn !== myColor) return;
    
    // Clicking our own piece starts/restarts a move
    const piece = gameState.board[r][c];
    if (piece && piece.toLowerCase() === myColor) {
        currentPath = [[r, c]];
        renderBoard(gameState.board); // re-render to show new highlights
        return;
    }
    
    // Clicking an empty square when we have a path started
    if (currentPath.length > 0) {
        const potentialNext = [r, c];
        const newPath = [...currentPath, potentialNext];
        
        let isPrefix = false;
        let isExactMatch = false;
        
        for (const move of validMoves) {
            let matchesSoFar = true;
            for (let i = 0; i < newPath.length; i++) {
                if (!move[i] || move[i][0] !== newPath[i][0] || move[i][1] !== newPath[i][1]) {
                    matchesSoFar = false; break;
                }
            }
            if (matchesSoFar) {
                if (move.length === newPath.length) {
                    isExactMatch = true;
                } else {
                    isPrefix = true;
                }
            }
        }
        
        if (isExactMatch) {
            currentPath = newPath;
            submitMove(currentPath);
        } else if (isPrefix) {
            currentPath = newPath;
            renderBoard(gameState.board);
        }
    }
}

async function submitMove(path) {
    try {
        const res = await fetch(`${API_BASE}/move`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game_id: gameId, session_id: sessionId, path })
        });
        const data = await res.json();
        if (data.success) {
            currentPath = [];
            pollState(); // Get latest board immediately
        } else {
            console.error("Move error:", data.error);
            currentPath = [];
            renderBoard(gameState.board);
        }
    } catch (e) {
        console.error("Failed to submit move", e);
    }
}
