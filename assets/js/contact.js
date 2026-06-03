// ==========================================
// B站风格评论区 — 前端逻辑
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
    const resolveApiUrl = () => {
        if (window.location.protocol === 'file:') return '../server/api/guestbook.php';
        return new URL('../server/api/guestbook.php', window.location.href).toString();
    };
    const API = resolveApiUrl();

    const AVATAR_COLORS = ['#FB7299','#00AEEC','#F25D8E','#6DC781','#FFB347','#8B5CF6','#06B6D4','#F97316'];

    const $ = (s) => document.querySelector(s);
    const postText = $('#bili-post-text');
    const postName = $('#bili-post-name');
    const postEmail = $('#bili-post-email');
    const postSubmit = $('#bili-post-submit');
    const charCount = $('#bili-char-count');
    const commentList = $('#bili-comment-list');
    const commentTotal = $('#comment-total');
    const pagination = $('#bili-pagination');
    const sortTabs = $('#sort-tabs');
    const postAvatar = $('#post-avatar');

    if (!postText || !commentList) return;

    let currentPage = 1;
    let currentSort = 'newest';
    let totalPages = 1;
    let totalComments = 0;

    function avatarColor(name) {
        let hash = 0;
        for (let i = 0; i < (name || '?').length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
        return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
    }
    function avatarEl(name, cls = 'bili-avatar') {
        const el = document.createElement('div');
        el.className = cls;
        el.style.background = avatarColor(name);
        el.textContent = (name || '匿')[0].toUpperCase();
        return el;
    }
    const randomNames = ['你','M','U','Hi','Yo','A','X'];
    function randomAvatar() {
        const n = randomNames[Math.floor(Math.random() * randomNames.length)];
        postAvatar.style.background = avatarColor(n);
        postAvatar.textContent = n;
    }
    randomAvatar();

    function toast(msg, type = 'success') {
        const old = document.querySelector('.bili-toast');
        if (old) old.remove();
        const t = document.createElement('div');
        t.className = 'bili-toast ' + type;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => { t.classList.add('fadeout'); setTimeout(() => t.remove(), 400); }, 2500);
    }

    function relativeTime(iso) {
        const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
        if (diff < 604800) return Math.floor(diff / 86400) + '天前';
        const d = new Date(iso);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    async function toggleLike(commentId, btnEl) {
        try {
            const res = await fetch(API + '?action=like', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({comment_id:commentId}) });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.error || '点赞失败');
            const countEl = btnEl.querySelector('.count');
            if (countEl) countEl.textContent = data.likes || 0;
            if (data.action === 'liked') { btnEl.classList.add('liked'); toast('已点赞 ❤️'); }
            else { btnEl.classList.remove('liked'); }
        } catch (e) { toast(e.message, 'error'); }
    }

    function buildReplyItem(reply) {
        const div = document.createElement('div');
        div.className = 'bili-reply-item';
        div.appendChild(avatarEl(reply.name, 'bili-avatar bili-avatar-sm'));
        const c = document.createElement('div');
        c.className = 'bili-reply-content';
        const top = document.createElement('div');
        top.className = 'bili-reply-top';
        const n = document.createElement('span');
        n.className = 'bili-reply-name';
        n.textContent = reply.name || '匿名';
        const t = document.createElement('span');
        t.className = 'bili-reply-time';
        t.textContent = relativeTime(reply.createdAt);
        top.appendChild(n); top.appendChild(t);
        const txt = document.createElement('div');
        txt.className = 'bili-reply-text';
        txt.textContent = reply.message || '';
        const acts = document.createElement('div');
        const lb = document.createElement('button');
        lb.className = 'bili-reply-like' + (reply.liked ? ' liked' : '');
        lb.innerHTML = '<i class="fas fa-thumbs-up"></i> <span class="count">' + (reply.likes||0) + '</span>';
        lb.addEventListener('click', (e) => { e.stopPropagation(); toggleLike(reply.id, lb); });
        acts.appendChild(lb);
        c.appendChild(top); c.appendChild(txt); c.appendChild(acts);
        div.appendChild(c);
        return div;
    }

    function buildCommentItem(item) {
        const wrap = document.createElement('div');
        wrap.className = 'bili-comment-item';
        const main = document.createElement('div');
        main.className = 'bili-comment-main';
        main.appendChild(avatarEl(item.name));
        const content = document.createElement('div');
        content.className = 'bili-comment-content';
        const top = document.createElement('div');
        top.className = 'bili-comment-top';
        const nameEl = document.createElement('span');
        nameEl.className = 'bili-comment-name';
        nameEl.textContent = item.name || '匿名';
        const timeEl = document.createElement('span');
        timeEl.className = 'bili-comment-time';
        timeEl.textContent = relativeTime(item.createdAt);
        top.appendChild(nameEl); top.appendChild(timeEl);
        const text = document.createElement('div');
        text.className = 'bili-comment-text';
        text.textContent = item.message || '';
        const actions = document.createElement('div');
        actions.className = 'bili-comment-actions';
        const likeBtn = document.createElement('button');
        likeBtn.className = 'bili-action-btn' + (item.liked ? ' liked' : '');
        likeBtn.innerHTML = '<i class="far fa-thumbs-up"></i> <span class="count">' + (item.likes||0) + '</span>';
        likeBtn.title = '点赞';
        likeBtn.addEventListener('click', () => toggleLike(item.id, likeBtn));
        const replyBtn = document.createElement('button');
        replyBtn.className = 'bili-action-btn';
        replyBtn.innerHTML = '<i class="far fa-comment-dots"></i> 回复';
        replyBtn.title = '回复';
        actions.appendChild(likeBtn); actions.appendChild(replyBtn);
        content.appendChild(top); content.appendChild(text); content.appendChild(actions);
        main.appendChild(content);
        wrap.appendChild(main);

        const repliesWrap = document.createElement('div');
        repliesWrap.className = 'bili-replies-wrap';
        const replies = Array.isArray(item.replies) ? item.replies : [];
        replies.forEach(r => repliesWrap.appendChild(buildReplyItem(r)));
        if (item.hasMoreReplies) {
            const moreBtn = document.createElement('button');
            moreBtn.className = 'bili-more-replies';
            moreBtn.textContent = '查看全部 ' + item.replyCount + ' 条回复';
            moreBtn.addEventListener('click', () => { moreBtn.textContent = '加载中...'; loadAllReplies(item.id, repliesWrap); });
            repliesWrap.appendChild(moreBtn);
        }

        const inlineReply = document.createElement('div');
        inlineReply.className = 'bili-inline-reply hidden';
        inlineReply.innerHTML = '<div style="display:flex;gap:8px;"><textarea rows="2" placeholder="回复 ' + escapeHtml(item.name||'匿名') + '..." maxlength="500" style="flex:1;"></textarea></div><div class="bili-inline-actions"><input type="text" class="inline-reply-name" placeholder="你的昵称" maxlength="40" /><button class="bili-btn-sm cancel">取消</button><button class="bili-btn-sm primary">发送</button></div>';
        const inlineTextarea = inlineReply.querySelector('textarea');
        const inlineName = inlineReply.querySelector('.inline-reply-name');
        const cancelBtn = inlineReply.querySelector('.cancel');
        const sendBtn = inlineReply.querySelector('.primary');
        replyBtn.addEventListener('click', () => { inlineReply.classList.toggle('hidden'); if (!inlineReply.classList.contains('hidden')) inlineTextarea.focus(); });
        cancelBtn.addEventListener('click', () => { inlineReply.classList.add('hidden'); inlineTextarea.value = ''; });
        sendBtn.addEventListener('click', async () => {
            const msg = inlineTextarea.value.trim();
            const rname = inlineName.value.trim() || postName.value.trim() || '匿名';
            if (!msg) return;
            sendBtn.disabled = true;
            try {
                const res = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:rname,email:'',message:msg,parent_id:item.id}) });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || '回复失败');
                toast('回复成功');
                inlineReply.classList.add('hidden');
                inlineTextarea.value = '';
                loadComments(currentPage, currentSort);
            } catch (e) { toast(e.message, 'error'); }
            finally { sendBtn.disabled = false; }
        });
        wrap.appendChild(repliesWrap);
        wrap.appendChild(inlineReply);
        return wrap;
    }

    async function loadAllReplies(rootId, container) {
        try {
            const res = await fetch(API + '?limit=100&page=1&sort=oldest', { headers:{Accept:'application/json'} });
            const data = await res.json();
            const root = (data.items||[]).find(it => it.id == rootId);
            if (!root) return;
            container.querySelectorAll('.bili-reply-item,.bili-more-replies').forEach(el=>el.remove());
            (root.replies||[]).forEach(r=>container.appendChild(buildReplyItem(r)));
        } catch (e) { toast('加载回复失败', 'error'); }
    }

    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

    function buildPagination(page, total) {
        pagination.innerHTML = '';
        if (total <= 1) return;
        const prev = document.createElement('button');
        prev.className = 'bili-page-btn';
        prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prev.disabled = page <= 1;
        prev.addEventListener('click', () => loadComments(page-1, currentSort));
        pagination.appendChild(prev);
        const maxShow = 5;
        let start = Math.max(1, page - Math.floor(maxShow/2));
        let end = Math.min(total, start + maxShow - 1);
        if (end - start < maxShow - 1) start = Math.max(1, end - maxShow + 1);
        for (let i = start; i <= end; i++) {
            const btn = document.createElement('button');
            btn.className = 'bili-page-btn' + (i===page?' active':'');
            btn.textContent = i;
            btn.addEventListener('click', () => loadComments(i, currentSort));
            pagination.appendChild(btn);
        }
        const next = document.createElement('button');
        next.className = 'bili-page-btn';
        next.innerHTML = '<i class="fas fa-chevron-right"></i>';
        next.disabled = page >= total;
        next.addEventListener('click', () => loadComments(page+1, currentSort));
        pagination.appendChild(next);
    }

    async function loadComments(page = 1, sort = 'newest') {
        currentPage = page;
        currentSort = sort;
        commentList.innerHTML = '<div class="bili-loading">加载中...</div>';
        try {
            const res = await fetch(API + '?page=' + page + '&limit=20&sort=' + sort, { headers:{Accept:'application/json'} });
            const data = await res.json();
            const items = data.items || [];
            const pg = data.pagination || {};
            totalPages = pg.totalPages || 1;
            totalComments = pg.total || 0;
            commentTotal.textContent = totalComments + ' 条评论';
            commentList.innerHTML = '';
            if (items.length === 0) {
                commentList.innerHTML = '<div class="bili-loading">还没有评论，来发表第一条吧 🎉</div>';
            } else {
                items.forEach(item => commentList.appendChild(buildCommentItem(item)));
            }
            buildPagination(currentPage, totalPages);
        } catch (e) {
            commentList.innerHTML = '<div class="bili-loading" style="color:#ef4444;">加载失败，请刷新重试</div>';
        }
    }

    sortTabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.bili-sort-btn');
        if (!btn) return;
        sortTabs.querySelectorAll('.bili-sort-btn').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        loadComments(1, btn.dataset.sort);
    });

    postSubmit.addEventListener('click', async () => {
        const msg = postText.value.trim();
        const name = postName.value.trim();
        if (!msg || !name) { toast('请填写昵称和评论内容', 'error'); return; }
        postSubmit.disabled = true;
        try {
            const res = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name,email:postEmail.value.trim(),message:msg}) });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || '发布失败');
            toast('评论发布成功！');
            postText.value = '';
            postEmail.value = '';
            randomAvatar();
            loadComments(1, currentSort);
        } catch (e) { toast(e.message, 'error'); }
        finally { postSubmit.disabled = false; }
    });

    postText.addEventListener('input', () => {
        const len = postText.value.length;
        charCount.textContent = len;
        charCount.style.color = len > 450 ? '#ef4444' : len > 400 ? '#f59e0b' : 'var(--bili-text-light)';
    });

    postText.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); postSubmit.click(); }
    });

    loadComments(1, 'newest');
});