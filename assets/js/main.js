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

// ==========================================
// 仪表盘 — 追踪埋点 (PV / 在线心跳)
// ==========================================

(function() {
  const ANALYTICS_API = '../server/api/analytics.php';

  // 生成/获取会话 ID
  let sessionId = localStorage.getItem('_analytics_sid');
  if (!sessionId) {
    sessionId = 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    localStorage.setItem('_analytics_sid', sessionId);
  }

  // 当前页面路径
  const pagePath = window.location.pathname.replace(/^\/|\/$/g, '') || 'index';

  // 发送埋点 (不阻塞页面)
  function postAnalytics(action, data) {
    const body = JSON.stringify(Object.assign({ session_id: sessionId, page: pagePath }, data));
    try {
      navigator.sendBeacon
        ? navigator.sendBeacon(ANALYTICS_API + '?action=' + action, new Blob([body], { type: 'application/json' }))
        : fetch(ANALYTICS_API + '?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {});
    } catch (e) { /* 静默失败 */ }
  }

  // 页面加载时上报 PV
  document.addEventListener('DOMContentLoaded', () => {
    postAnalytics('track', {});
  });

  // 每 30 秒发送心跳
  setInterval(() => {
    postAnalytics('heartbeat', {});
  }, 30000);

  // 首次心跳立即发送
  postAnalytics('heartbeat', {});

  // 暴露模块追踪接口给其他 JS 使用
  window.trackModule = function(moduleName) {
    postAnalytics('module', { module: moduleName });
  };
})();