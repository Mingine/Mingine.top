<?php
declare(strict_types=1);

/**
 * Markdown → HTML 解析器
 * 支持: 标题/加粗/斜体/删除线/链接/图片/锚点/表格/列表/代码/引用/分割线/换行
 */
class Markdown
{
    public function parse(string $text): string
    {
        // 1. 保护代码块和行内代码
        $codeBlocks = [];
        $text = preg_replace_callback('/```(\w*)\n([\s\S]*?)```/', function ($m) use (&$codeBlocks) {
            $codeBlocks[] = '<pre><code class="language-' . ($m[1] ?: 'plaintext') . '">' . htmlspecialchars(rtrim($m[2]), ENT_QUOTES, 'UTF-8') . '</code></pre>';
            return "\x00CODE" . (count($codeBlocks) - 1) . "\x00";
        }, $text);

        $inlineCodes = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$inlineCodes) {
            $inlineCodes[] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            return "\x00IC" . (count($inlineCodes) - 1) . "\x00";
        }, $text);

        // 2. 分割为段落（按双空行）
        $blocks = preg_split('/\n\s*\n/', $text);
        $result = [];
        $i = 0;

        while ($i < count($blocks)) {
            $block = trim($blocks[$i]);
            if ($block === '') { $i++; continue; }

            // 表格检测
            $tableHtml = $this->tryTable($block);
            if ($tableHtml !== null) {
                $result[] = $tableHtml;
                $i++;
                continue;
            }

            // 无序列表分组
            if (preg_match('/^[\-\*]\s+/', $block)) {
                $items = [];
                while ($i < count($blocks) && preg_match('/^[\-\*]\s+/', trim($blocks[$i]))) {
                    $items[] = preg_replace('/^[\-\*]\s+/', '', trim($blocks[$i]));
                    $i++;
                }
                $result[] = '<ul><li>' . implode('</li><li>', array_map([$this, 'parseInline'], $items)) . '</li></ul>';
                continue;
            }

            // 有序列表分组
            if (preg_match('/^\d+\.\s+/', $block)) {
                $items = [];
                while ($i < count($blocks) && preg_match('/^\d+\.\s+/', trim($blocks[$i]))) {
                    $items[] = preg_replace('/^\d+\.\s+/', '', trim($blocks[$i]));
                    $i++;
                }
                $result[] = '<ol><li>' . implode('</li><li>', array_map([$this, 'parseInline'], $items)) . '</li></ol>';
                continue;
            }

            $result[] = $this->parseBlock($block);
            $i++;
        }

        $html = implode("\n", $result);

        // 还原代码
        foreach ($codeBlocks as $k => $v) { $html = str_replace("\x00CODE{$k}\x00", $v, $html); }
        foreach ($inlineCodes as $k => $v) { $html = str_replace("\x00IC{$k}\x00", $v, $html); }

        return $html;
    }

    // ── 表格 ──
    private function tryTable(string $block): ?string
    {
        $lines = explode("\n", $block);
        if (count($lines) < 2) return null;
        $headerLine = trim($lines[0]);
        $sepLine = trim($lines[1] ?? '');
        if (!preg_match('/^\|.+\|$/', $headerLine)) return null;
        if (!preg_match('/^\|[\s\-:]+\|$/', $sepLine)) return null;
        if (substr_count($headerLine, '|') !== substr_count($sepLine, '|')) return null;

        // 对齐
        $aligns = [];
        foreach (explode('|', trim($sepLine, '|')) as $s) {
            $s = trim($s);
            $l = ($s[0] ?? '') === ':'; $r = ($s[strlen($s)-1] ?? '') === ':';
            $aligns[] = $l && $r ? 'center' : ($r ? 'right' : ($l ? 'left' : ''));
        }

        $headers = array_map('trim', explode('|', trim($headerLine, '|')));
        $html = '<table style="width:100%;border-collapse:collapse;margin:1em 0;"><thead><tr>';
        foreach ($headers as $j => $h) {
            $a = $aligns[$j] ?? '';
            $html .= '<th style="border:1px solid var(--glass-border,#ddd);padding:8px 12px;text-align:' . ($a ?: 'left') . '">' . $this->parseInline($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        for ($k = 2; $k < count($lines); $k++) {
            $row = trim($lines[$k]);
            if (!preg_match('/^\|.+\|$/', $row)) continue;
            $cells = array_map('trim', explode('|', trim($row, '|')));
            $html .= '<tr>';
            foreach ($cells as $j => $cell) {
                $a = $aligns[$j] ?? '';
                $html .= '<td style="border:1px solid var(--glass-border,#ddd);padding:8px 12px;text-align:' . ($a ?: 'left') . '">' . $this->parseInline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    // ── 块级元素 ──
    private function parseBlock(string $line): string
    {
        // 标题（生成 id 用于锚点跳转）
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            $level = strlen($m[1]);
            $content = $this->parseInline($m[2]);
            $id = $this->headingId(strip_tags($m[2]));
            return "<h{$level} id=\"{$id}\">{$content}</h{$level}>";
        }

        // 分割线
        if (preg_match('/^[-*_]{3,}\s*$/', $line)) {
            return '<hr>';
        }

        // 引用
        if (preg_match('/^>\s?(.+)$/', $line, $m)) {
            return '<blockquote><p>' . $this->parseInline($m[1]) . '</p></blockquote>';
        }

        // 图片单独一行
        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $line, $m)) {
            $alt = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<p><img src="' . $src . '" alt="' . $alt . '" style="max-width:100%;border-radius:8px;"></p>';
        }

        // 普通段落：单换行 → <br>
        $parts = explode("\n", $line);
        $html = '';
        foreach ($parts as $idx => $part) {
            if ($idx > 0) $html .= '<br>';
            $html .= $this->parseInline($part);
        }
        return '<p>' . $html . '</p>';
    }

    // ── 行内元素 ──
    private function parseInline(string $text): string
    {
        // 图片
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;border-radius:8px;">', $text);
        // 链接（锚点 # 不加 target="_blank"）
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = $m[2];
            $isAnchor = (strlen($url) > 0 && $url[0] === '#');
            $target = $isAnchor ? '' : ' target="_blank" rel="noopener"';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $target . '>' . $m[1] . '</a>';
        }, $text);
        // 加粗
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        // 斜体
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        // 删除线
        $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);
        return $text;
    }

    // ── 标题 ID 生成（用于锚点） ──
    private function headingId(string $text): string
    {
        $id = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s-]/u', '', $text);
        $id = preg_replace('/\s+/', '-', trim($id));
        return strtolower($id) ?: 'heading';
    }
}
