// 2048 游戏逻辑 (Vanilla JS)

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.trackModule === 'function') window.trackModule('game2048');

    const grid = document.getElementById('grid');
    const scoreDisplay = document.getElementById('score');
    const restartBtn = document.getElementById('restart-btn');
    
    let board = [];
    let score = 0;
    
    // 初始化网格
    function init() {
      board = [
        [0, 0, 0, 0],
        [0, 0, 0, 0],
        [0, 0, 0, 0],
        [0, 0, 0, 0]
      ];
      score = 0;
      updateScore();
      addRandomTile();
      addRandomTile();
      updateBoard();
    }
    
    // 随机生成2或4
    function addRandomTile() {
      let emptyCells = [];
      for (let r = 0; r < 4; r++) {
        for (let c = 0; c < 4; c++) {
          if (board[r][c] === 0) {
            emptyCells.push({r, c});
          }
        }
      }
      if (emptyCells.length > 0) {
        let randomCell = emptyCells[Math.floor(Math.random() * emptyCells.length)];
        board[randomCell.r][randomCell.c] = Math.random() < 0.9 ? 2 : 4;
      }
    }
  
    // 界面更新
    function updateBoard() {
      // 移除旧的 tile
      const tiles = document.querySelectorAll('.tile');
      tiles.forEach(t => t.remove());
  
      for (let r = 0; r < 4; r++) {
        for (let c = 0; c < 4; c++) {
          const val = board[r][c];
          if (val > 0) {
            const tile = document.createElement('div');
            tile.classList.add('tile');
            tile.innerText = val;
            
            // 位置设置
            tile.style.top = `${r * 25 + 0.5}%`;
            tile.style.left = `${c * 25 + 0.5}%`;
            tile.style.width = '23%';
            tile.style.height = '23%';
  
            // 颜色（简化逻辑）
            tile.style.backgroundColor = getTileColor(val);
            tile.style.color = val > 4 ? '#f9f6f2' : '#776e65';
  
            grid.appendChild(tile);
          }
        }
      }
    }
  
    // 获取各种分数的颜色
    function getTileColor(val) {
      const colors = {
        2: '#eee4da',
        4: '#ede0c8',
        8: '#f2b179',
        16: '#f59563',
        32: '#f67c5f',
        64: '#f65e3b',
        128: '#edcf72',
        256: '#edcc61',
        512: '#edc850',
        1024: '#edc53f',
        2048: '#edc22e'
      };
      return colors[val] || '#3c3a32';
    }
  
    // 更新分数
    function updateScore() {
      scoreDisplay.innerText = score;
    }
  
    // 移动逻辑（核心算法）
    function move(direction) {
      let moved = false;
      
      // 合并助手函数
      const slide = (row) => {
        let b = row.filter(val => val);
        let missing = 4 - b.length;
        let zeros = Array(missing).fill(0);
        return b.concat(zeros);
      };
      
      const combine = (row) => {
        for (let i = 0; i < 3; i++) {
          if (row[i] !== 0 && row[i] === row[i+1]) {
            row[i] = row[i] * 2;
            row[i+1] = 0;
            score += row[i];
            moved = true;
          }
        }
        return row;
      };
  
      // 按方向翻转、滑动合并再复原
      for (let i = 0; i < 4; i++) {
        let row = [];
        if (direction === 'left' || direction === 'right') {
          row = [board[i][0], board[i][1], board[i][2], board[i][3]];
        } else {
          row = [board[0][i], board[1][i], board[2][i], board[3][i]];
        }
  
        if (direction === 'right' || direction === 'down') {
          row = row.reverse();
        }
  
        let original = [...row];
        row = slide(row);
        row = combine(row);
        row = slide(row);
        
        if (direction === 'right' || direction === 'down') {
          row = row.reverse();
        }
  
        if (original.toString() !== row.toString()) moved = true;
  
        if (direction === 'left' || direction === 'right') {
          board[i] = row;
        } else {
          for (let j = 0; j < 4; j++) {
            board[j][i] = row[j];
          }
        }
      }
  
      if (moved) {
        addRandomTile();
        updateBoard();
        updateScore();
        checkGameOver();
      }
    }
  
    // 判断游戏是否结束
    function checkGameOver() {
      // 检查是否有空位
      for (let r = 0; r < 4; r++) {
        for (let c = 0; c < 4; c++) {
          if (board[r][c] === 0) return false;
        }
      }
      // 检查是否能合并
      for (let r = 0; r < 4; r++) {
        for (let c = 0; c < 4; c++) {
          if (c < 3 && board[r][c] === board[r][c+1]) return false;
          if (r < 3 && board[r][c] === board[r+1][c]) return false;
        }
      }
      
      alert(`游戏结束！得分：${score}`);
      return true;
    }
  
    // 监听键盘按键
    document.addEventListener('keydown', (e) => {
      // 阻止上下左右滚屏
      if(["ArrowUp","ArrowDown","ArrowLeft","ArrowRight"].indexOf(e.code) > -1) {
          e.preventDefault();
      }
      if (e.key === 'ArrowUp') move('up');
      if (e.key === 'ArrowDown') move('down');
      if (e.key === 'ArrowLeft') move('left');
      if (e.key === 'ArrowRight') move('right');
    });
  
    // 触控支持 (滑动)
    let touchStartX = 0;
    let touchStartY = 0;
    
    grid.addEventListener('touchstart', (e) => {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
      e.preventDefault();
    }, { passive: false });
  
    grid.addEventListener('touchend', (e) => {
      if(!touchStartX || !touchStartY) return;
      let touchEndX = e.changedTouches[0].clientX;
      let touchEndY = e.changedTouches[0].clientY;
      
      let deltaX = touchEndX - touchStartX;
      let deltaY = touchEndY - touchStartY;
      
      if (Math.abs(deltaX) > Math.abs(deltaY)) {
        // 左右
        if (Math.abs(deltaX) > 30) {
          if (deltaX > 0) move('right');
          else move('left');
        }
      } else {
        // 上下
        if (Math.abs(deltaY) > 30) {
          if (deltaY > 0) move('down');
          else move('up');
        }
      }
      touchStartX = null;
      touchStartY = null;
    });
  
    restartBtn.addEventListener('click', init);
    
    // 初始化启动
    init();
  });