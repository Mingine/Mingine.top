<?php
declare(strict_types=1);

/**
 * 轻量 Markdown → HTML 解析器
 * 支持: 标题、加粗、斜体、链接、图片、代码块、行内代码、列表、引用、分割线
 */
class Markdown
{
    public function parse(string $text): string
    {
        $text = $this->escapeHtml($text);
        $text = $this->parseBlocks($text);
        return $text;
    }

    private function escapeHtml(string $text): string
    {
        // 保护代码块
        $blocks = [];
        $text = preg_replace_callback('/```(\w*)\n([\s\S]*?)```/', function ($m) use (&$blocks) {
            $blocks[] = '<pre><code class="language-' . ($m[1] ?: 'plaintext') . '">' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</code></pre>';
            return "\x00CODE" . (count($blocks) - 1) . "\x00";
        }, $text);

        // 保护行内代码
        $inlines = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$inlines) {
            $inlines[] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            return "\x00INLINE" . (count($inlines) - 1) . "\x00";
        }, $text);

        // 分割段落
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $result = [];

        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $result[] = $this->parseBlock($p, $inlines);
        }

        $html = implode("\n", $result);

        // 还原代码块
        foreach ($blocks as $i => $block) {
            $html = str_replace("\x00CODE{$i}\x00", $block, $html);
        }
        // 还原行内代码
        foreach ($inlines as $i => $inline) {
            $html = str_replace("\x00INLINE{$i}\x00", $inline, $html);
        }

        return $html;
    }

    private function parseBlock(string $line, array &$inlines): string
    {
        // 标题
        if (preg_match('/^#{1,6}\s+(.+)$/', $line, $m)) {
            $level = strlen($m[1]) - strlen(ltrim($m[1])) + 1;
            // count # signs
            $level = 0;
            for ($i = 0; $i < strlen($line); $i++) {
                if ($line[$i] === '#') $level++;
                else break;
            }
            $level = min(6, $level);
            return "<h{$level}>" . $this->parseInline(ltrim(substr($line, $level), ' ')) . "</h{$level}>";
        }

        // 分割线
        if (preg_match('/^[-*_]{3,}\s*$/', $line)) {
            return '<hr>';
        }

        // 引用
        if (preg_match('/^>\s?(.+)$/', $line, $m)) {
            return '<blockquote><p>' . $this->parseInline($m[1]) . '</p></blockquote>';
        }

        // 无序列表
        if (preg_match('/^[\-\*]\s+(.+)$/', $line, $m)) {
            return '<ul><li>' . $this->parseInline($m[1]) . '</li></ul>';
        }

        // 有序列表
        if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
            return '<ol><li>' . $this->parseInline($m[1]) . '</li></ol>';
        }

        // 图片单独一行
        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $line, $m)) {
            $alt = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<p><img src="' . $src . '" alt="' . $alt . '" style="max-width:100%;border-radius:8px;"></p>';
        }

        // 普通段落
        return '<p>' . $this->parseInline($line) . '</p>';
    }

    private function parseInline(string $text): string
    {
        // 加粗
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        // 斜体
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        // 图片
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;border-radius:8px;">', $text);
        // 链接
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        return $text;
    }
}
