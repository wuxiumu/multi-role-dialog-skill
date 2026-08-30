import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'

/**
 * 对话气泡 Markdown 渲染（用户输入 / AI 输出共用）。
 * 默认不解析 raw HTML，降低 XSS 风险。
 */
export default function MarkdownBubble({ content, className = '', pending = false }) {
  const text = content == null ? '' : String(content)
  return (
    <div className={`msg__bubble msg__md ${pending ? 'caret' : ''} ${className}`.trim()}>
      {text ? (
        <ReactMarkdown remarkPlugins={[remarkGfm]}>{text}</ReactMarkdown>
      ) : pending ? (
        '\u00a0'
      ) : null}
    </div>
  )
}
