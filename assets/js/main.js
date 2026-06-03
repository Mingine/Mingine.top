// ==========================================
// 主题切换逻辑
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
    const themeBtn = document.getElementById('theme-btn');
  const musicTag = document.getElementById('tag-music');
  const aiChatTag = document.getElementById('tag-ai-chat');
    const localTheme = localStorage.getItem('theme');
    
    // 初始化主题
    if (localTheme) {
      document.body.setAttribute('data-theme', localTheme);
      updateThemeIcon(localTheme);
    } else {
      // 检查系统偏好
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.body.setAttribute('data-theme', 'dark');
        updateThemeIcon('dark');
      }
    }
    
    // 监听按钮点击
    themeBtn.addEventListener('click', () => {
      let currentTheme = document.body.getAttribute('data-theme');
      let targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      document.body.setAttribute('data-theme', targetTheme);
      localStorage.setItem('theme', targetTheme);
      updateThemeIcon(targetTheme);
    });
    
    function updateThemeIcon(theme) {
      if (themeBtn) {
        themeBtn.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
      }
    }

    if (musicTag) {
      musicTag.addEventListener('click', () => {
        if (typeof window.expandMusicWidget === 'function') {
          window.expandMusicWidget();
        }
      });
    }

    if (aiChatTag) {
      aiChatTag.addEventListener('click', () => {
        if (typeof window.openMingineChat === 'function') {
          window.openMingineChat();
        }
      });
    }
  });