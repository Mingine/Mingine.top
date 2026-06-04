// ==========================================
// AI 聊天机器人交互逻辑
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
    const chatFab = document.getElementById('chat-fab');
    const chatWindow = document.getElementById('chat-window');
    const closeChat = document.getElementById('close-chat');
    const chatBody = document.getElementById('chat-body');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
  
    const openChatWindow = () => {
      chatWindow.style.display = 'flex';
      chatInput.focus();
    };

    // 切换聊天窗显隐
    chatFab.addEventListener('click', () => {
      chatWindow.style.display = chatWindow.style.display === 'flex' ? 'none' : 'flex';
      if(chatWindow.style.display === 'flex') {
        chatInput.focus();
        if (typeof window.trackModule === 'function') window.trackModule('ai_chat');
      }
    });
  
    closeChat.addEventListener('click', () => {
      chatWindow.style.display = 'none';
    });
  
    // 发送消息
    const sendMessage = async () => {
      const msg = chatInput.value.trim();
      if (!msg) return;
  
      // 用户消息渲染
      appendMessage(msg, 'msg-user');
      chatInput.value = '';
      
      // 显示正在输入提示
      const loadingId = appendMessage('正在思考中...', 'msg-bot', true);
  
      // 发送请求到 PHP 后端
      try {
        const response = await fetch('server/api/chat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ message: msg })
        });
  
        if (!response.ok) {
           throw new Error(`请求失败: ${response.status}`);
        }
        
        // 由于需要支持流式输出 (Stream) 或普通输出
        // 这里提供普通的完整响应获取示范，如果改成流式，可以使用 FileReader / fetch streaming api
        const data = await response.json();
        
        // 移除 loading
        removeMessage(loadingId);
        
        if (data && data.reply) {
          appendMessage(data.reply, 'msg-bot');
        } else {
          appendMessage('抱歉，我现在有点忙，稍后再试吧。', 'msg-bot');
        }
      } catch (error) {
        console.error('AI回复错误:', error);
        removeMessage(loadingId);
        appendMessage('网络错误，请稍后再试。', 'msg-bot');
      }
    };
  
    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        sendMessage();
      }
    });
  
    // 工具函数
    function appendMessage(text, className, isLoading = false) {
      const div = document.createElement('div');
      div.className = `chat-msg ${className}`;
      div.innerText = text;
      
      const id = 'msg-' + Date.now();
      if (isLoading) div.id = id;
      
      chatBody.appendChild(div);
      chatBody.scrollTop = chatBody.scrollHeight; // 滚动到底部
      return id;
    }
  
    function removeMessage(id) {
      const el = document.getElementById(id);
      if (el) el.remove();
    }

    // 提供给其他脚本调用
    window.openMingineChat = openChatWindow;
  });