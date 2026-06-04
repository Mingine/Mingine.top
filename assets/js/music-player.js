// 音乐悬浮播放器展开/收起交互

document.addEventListener('DOMContentLoaded', () => {
  const widget = document.getElementById('music-widget');
  const toggleBtn = document.getElementById('music-toggle');
  const sourceButtons = document.querySelectorAll('.music-source-btn');
  const sourcePanels = document.querySelectorAll('.music-source-panel');

  if (!widget || !toggleBtn) {
    return;
  }

  const getPanelIframe = (source) => {
    const panel = document.getElementById(`panel-${source}`);
    return panel ? panel.querySelector('iframe') : null;
  };

  const loadPlayer = (source) => {
    const iframe = getPanelIframe(source);
    if (!iframe) {
      return;
    }

    if (!iframe.dataset.src && iframe.src) {
      iframe.dataset.src = iframe.src;
    }

    if (!iframe.src && iframe.dataset.src) {
      iframe.src = iframe.dataset.src;
    }
  };

  const unloadPlayer = (source) => {
    const iframe = getPanelIframe(source);
    if (!iframe || !iframe.src) {
      return;
    }

    if (!iframe.dataset.src) {
      iframe.dataset.src = iframe.src;
    }

    // 清空 src 触发 iframe 卸载，停止该播放器声音
    iframe.removeAttribute('src');
  };

  const switchSource = (source) => {
    sourceButtons.forEach((btn) => {
      const isActive = btn.dataset.source === source;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-selected', String(isActive));
    });

    sourcePanels.forEach((panel) => {
      const panelSource = panel.id.replace('panel-', '');
      const isActive = panelSource === source;
      panel.classList.toggle('active', isActive);

      if (isActive) {
        loadPlayer(panelSource);
      } else {
        unloadPlayer(panelSource);
      }
    });
  };

  const toggleWidget = () => {
    const isCollapsed = widget.classList.contains('collapsed');

    widget.classList.toggle('collapsed', !isCollapsed);
    widget.classList.toggle('expanded', isCollapsed);
    toggleBtn.setAttribute('aria-expanded', String(isCollapsed));
  };

  const expandWidget = () => {
    if (widget.classList.contains('collapsed')) {
      toggleWidget();
      if (typeof window.trackModule === 'function') window.trackModule('music');
    }
  };

  toggleBtn.addEventListener('click', toggleWidget);

  widget.addEventListener('click', (event) => {
    const clickedToggle = event.target.closest('#music-toggle');
    if (clickedToggle) {
      return;
    }

    if (widget.classList.contains('collapsed')) {
      toggleWidget();
    }
  });

  sourceButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      switchSource(btn.dataset.source);
    });
  });

  // 默认展示网易云
  switchSource('netease');

  // 提供给其他脚本调用
  window.expandMusicWidget = expandWidget;
});
